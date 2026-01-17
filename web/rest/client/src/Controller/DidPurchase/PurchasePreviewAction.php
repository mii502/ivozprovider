<?php

declare(strict_types=1);

/**
 * DID Purchase Preview Action - Preview costs before purchase
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/DidPurchase/PurchasePreviewAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

namespace Controller\DidPurchase;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\Ddi\DidInventoryService;
use Ivoz\Provider\Domain\Service\Ddi\DidPurchaseServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /dids/purchase/preview
 *
 * Preview the costs of purchasing a DID before committing.
 * Returns prorated costs, balance check, and next renewal info.
 *
 * Request Body:
 * {
 *   "ddiId": 123
 * }
 *
 * Response:
 * {
 *   "ddi": "+34912345678",
 *   "ddiId": 123,
 *   "country": "Spain",
 *   "setupPrice": 5.00,
 *   "monthlyPrice": 2.00,
 *   "proratedFirstMonth": 1.50,
 *   "totalDueNow": 6.50,
 *   "nextRenewalDate": "2026-02-01",
 *   "nextRenewalAmount": 2.00,
 *   "currentBalance": 100.00,
 *   "balanceAfterPurchase": 93.50,
 *   "canPurchase": true,
 *   "breakdown": [
 *     { "description": "Setup fee", "amount": 5.00 },
 *     { "description": "Monthly fee (prorated: 15 of 31 days)", "amount": 1.50 }
 *   ]
 * }
 */
class PurchasePreviewAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private DidInventoryService $didInventoryService,
        private DidPurchaseServiceInterface $didPurchaseService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            throw new ResourceClassNotFoundException('User not found');
        }

        /** @var AdministratorInterface $admin */
        $admin = $token->getUser();
        $company = $admin->getCompany();

        if (!$company) {
            throw new NotFoundHttpException('Company not found');
        }

        $brand = $company->getBrand();

        // Parse request body
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        if (!isset($data['ddiId'])) {
            throw new BadRequestHttpException('ddiId is required');
        }

        $ddiId = filter_var($data['ddiId'], FILTER_VALIDATE_INT);

        if ($ddiId === false || $ddiId <= 0) {
            throw new BadRequestHttpException('ddiId must be a positive integer');
        }

        // Get the DID from inventory (ensures it's available and in the same brand)
        $ddi = $this->didInventoryService->getAvailableDdiById($brand, $ddiId);

        if (!$ddi) {
            throw new NotFoundHttpException(sprintf(
                'DID %d not found or not available for purchase',
                $ddiId
            ));
        }

        try {
            // Get purchase preview
            $preview = $this->didPurchaseService->preview($company, $ddi);

            $this->logger->debug('DID purchase preview generated', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'total_due' => $preview['totalDueNow'],
                'can_purchase' => $preview['canPurchase'],
            ]);

            return new JsonResponse($preview);
        } catch (\Exception $e) {
            $this->logger->error('DID purchase preview failed', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'error' => 'Failed to generate purchase preview',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
