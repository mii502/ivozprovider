<?php

namespace Controller\Webhook;

use Ivoz\Core\Infrastructure\Persistence\Doctrine\ORM\EntityManager;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyRepository;
use Ivoz\Provider\Domain\Model\SuspensionLog\SuspensionLog;
use Ivoz\Provider\Domain\Model\SuspensionLog\SuspensionLogDto;
use Ivoz\Provider\Domain\Model\SuspensionLog\SuspensionLogInterface;
use Ivoz\Core\Domain\Service\EntityTools;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook controller for WHMCS Company Suspension notifications
 *
 * Handles two endpoints:
 * - POST /webhooks/whmcs-suspend: Suspend a company (block all calls)
 * - POST /webhooks/whmcs-unsuspend: Unsuspend a company (restore service)
 *
 * Both endpoints:
 * - Verify HMAC-SHA256 signature
 * - Validate timestamp (5-minute window)
 * - Look up company by whmcs_client_id
 * - Update Company.enabled field
 * - Create SuspensionLog entry
 *
 * @see integration/modules/ivozprovider-suspension/CONTEXT.md
 */
class WhmcsSuspensionWebhookController
{
    /** Maximum allowed timestamp skew in seconds (5 minutes) */
    private const MAX_TIMESTAMP_SKEW = 300;

    public function __construct(
        private CompanyRepository $companyRepository,
        private EntityManager $entityManager,
        private EntityTools $entityTools,
        private LoggerInterface $logger,
        private string $webhookSecret
    ) {
    }

    /**
     * Handle suspend webhook
     */
    public function suspend(Request $request): Response
    {
        return $this->processWebhook($request, true);
    }

    /**
     * Handle unsuspend webhook
     */
    public function unsuspend(Request $request): Response
    {
        return $this->processWebhook($request, false);
    }

    private function processWebhook(Request $request, bool $isSuspend): JsonResponse
    {
        $action = $isSuspend ? 'suspend' : 'unsuspend';

        try {
            // 1. Get signature headers
            $signature = $request->headers->get('X-Webhook-Signature');
            $timestamp = $request->headers->get('X-Webhook-Timestamp');
            $payload = $request->getContent();

            // 2. Verify required headers are present
            if (empty($signature) || empty($timestamp)) {
                $this->logger->warning("WHMCS {$action} webhook missing required headers");
                return new JsonResponse(
                    ['error' => 'Missing signature or timestamp header'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // 3. Verify HMAC-SHA256 signature
            $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

            if (!hash_equals($expectedSignature, $signature)) {
                $this->logger->warning("WHMCS {$action} webhook signature mismatch", [
                    'received' => substr($signature, 0, 16) . '...',
                ]);
                return new JsonResponse(
                    ['error' => 'Invalid signature'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // 4. Validate timestamp (5-minute window to prevent replay attacks)
            $timestampInt = (int) $timestamp;
            $timeDiff = abs(time() - $timestampInt);

            if ($timeDiff > self::MAX_TIMESTAMP_SKEW) {
                $this->logger->warning("WHMCS {$action} webhook timestamp expired", [
                    'timestamp' => $timestampInt,
                    'diff_seconds' => $timeDiff,
                ]);
                return new JsonResponse(
                    ['error' => 'Timestamp expired'],
                    Response::HTTP_UNAUTHORIZED
                );
            }

            // 5. Parse payload
            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning("WHMCS {$action} webhook invalid JSON", [
                    'error' => json_last_error_msg(),
                ]);
                return new JsonResponse(
                    ['error' => 'Invalid JSON payload'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 6. Get WHMCS client ID from payload
            $whmcsClientId = $data['whmcs_client_id'] ?? null;
            if (!$whmcsClientId) {
                $this->logger->warning("WHMCS {$action} webhook missing whmcs_client_id");
                return new JsonResponse(
                    ['error' => 'Missing whmcs_client_id'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // 7. Find company by WHMCS client ID
            /** @var CompanyInterface|null $company */
            $company = $this->companyRepository->findOneBy(['whmcsClientId' => $whmcsClientId]);

            if (!$company) {
                $this->logger->warning("WHMCS {$action} webhook company not found", [
                    'whmcs_client_id' => $whmcsClientId,
                ]);
                return new JsonResponse(
                    ['error' => 'Company not found'],
                    Response::HTTP_NOT_FOUND
                );
            }

            // 8. Idempotency check
            $currentEnabled = $company->getEnabled();
            $targetEnabled = !$isSuspend;

            if ($currentEnabled === $targetEnabled) {
                $status = $isSuspend ? 'already_suspended' : 'already_active';
                $this->logger->info("WHMCS {$action} webhook: Company already in target state", [
                    'company_id' => $company->getId(),
                    'whmcs_client_id' => $whmcsClientId,
                    'enabled' => $currentEnabled,
                ]);
                return new JsonResponse([
                    'status' => $status,
                    'company_id' => $company->getId(),
                    'enabled' => $currentEnabled,
                ]);
            }

            // 9. Update company enabled status
            $companyDto = $company->toDto();
            $companyDto->setEnabled($targetEnabled);
            $this->entityTools->persistDto($companyDto, $company);

            // 10. Create suspension log entry
            $reason = $data['reason'] ?? ($isSuspend ? 'whmcs_suspension' : 'whmcs_unsuspension');
            $logDto = new SuspensionLogDto();
            $logDto->setAction($isSuspend ? SuspensionLogInterface::ACTION_SUSPEND : SuspensionLogInterface::ACTION_UNSUSPEND);
            $logDto->setReason($reason);
            $logDto->setCompanyId($company->getId());
            $logDto->setCreatedAt(new \DateTime());

            $this->entityTools->persistDto($logDto, null, true);

            // 11. Flush changes
            $this->entityManager->flush();

            $this->logger->info("WHMCS {$action} webhook processed successfully", [
                'company_id' => $company->getId(),
                'whmcs_client_id' => $whmcsClientId,
                'enabled' => $targetEnabled,
                'reason' => $reason,
            ]);

            return new JsonResponse([
                'status' => 'ok',
                'company_id' => $company->getId(),
                'action' => $action,
                'enabled' => $targetEnabled,
            ]);

        } catch (\Exception $e) {
            $this->logger->error("WHMCS {$action} webhook processing failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                ['error' => $e->getMessage()],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
}
