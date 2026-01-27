<?php

declare(strict_types=1);

/**
 * BYON Verify Action - Verify OTP code and create DDI
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Byon/VerifyAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Controller\Byon;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\Byon\ByonException;
use Ivoz\Provider\Domain\Service\Byon\ByonServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /byon/verify
 *
 * Verify OTP code and create DDI on success.
 *
 * Request Body:
 * {
 *   "phoneNumber": "+34612345678",
 *   "code": "123456"
 * }
 *
 * Success Response (200):
 * {
 *   "success": true,
 *   "message": "Phone number verified and added as DDI",
 *   "ddiId": 456,
 *   "ddi": "+34612345678"
 * }
 *
 * Error Response (400/401):
 * {
 *   "success": false,
 *   "errorCode": "INVALID_CODE",
 *   "message": "Invalid verification code",
 *   "attemptsRemaining": 2
 * }
 */
class VerifyAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ByonServiceInterface $byonService,
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

        // Parse request body
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        if (!isset($data['phoneNumber'])) {
            throw new BadRequestHttpException('phoneNumber is required');
        }

        if (!isset($data['code'])) {
            throw new BadRequestHttpException('code is required');
        }

        $phoneNumber = trim($data['phoneNumber']);
        $code = trim($data['code']);

        if (empty($phoneNumber)) {
            throw new BadRequestHttpException('phoneNumber cannot be empty');
        }

        if (empty($code)) {
            throw new BadRequestHttpException('code cannot be empty');
        }

        // Validate code format (6 digits)
        if (!preg_match('/^\d{4,8}$/', $code)) {
            throw new BadRequestHttpException('code must be 4-8 digits');
        }

        try {
            // Verify code
            // ByonService returns ByonVerifyResult::success($ddi) on success
            // or throws ByonException on failure
            $result = $this->byonService->verify($company, $phoneNumber, $code);

            $ddi = $result->getDdi();
            $this->logger->info('BYON verification successful', [
                'company_id' => $company->getId(),
                'phone_number' => substr($phoneNumber, 0, -4) . '****',
                'ddi_id' => $ddi?->getId(),
            ]);

            return new JsonResponse($result->toArray());

        } catch (ByonException $e) {
            $this->logger->warning('BYON verification rejected', [
                'company_id' => $company->getId(),
                'phone_number' => substr($phoneNumber, 0, -4) . '****',
                'error_code' => $e->getErrorCode(),
                'error_message' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'errorCode' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getHttpStatusCode());

        } catch (\Exception $e) {
            // Write to file for debugging
            $logMsg = sprintf(
                "[%s] BYON VERIFY ERROR\nCompany: %d\nPhone: %s\nError: %s\nFile: %s:%d\nTrace:\n%s\n\n",
                date('Y-m-d H:i:s'),
                $company->getId(),
                $phoneNumber,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            );
            file_put_contents('/tmp/byon_debug.log', $logMsg, FILE_APPEND);

            $this->logger->error('BYON verification failed unexpectedly', [
                'company_id' => $company->getId(),
                'phone_number' => substr($phoneNumber, 0, -4) . '****',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'errorCode' => 'INTERNAL_ERROR',
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}
