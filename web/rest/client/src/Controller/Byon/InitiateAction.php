<?php

declare(strict_types=1);

/**
 * BYON Initiate Action - Send OTP verification code
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Byon/InitiateAction.php
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
 * POST /byon/initiate
 *
 * Initiate BYON verification by sending an OTP code via SMS.
 *
 * Request Body:
 * {
 *   "phoneNumber": "+34612345678"
 * }
 *
 * Success Response (200):
 * {
 *   "success": true,
 *   "message": "Verification code sent",
 *   "phoneNumber": "+34612345678",
 *   "verificationId": 123
 * }
 *
 * Error Response (400/429):
 * {
 *   "success": false,
 *   "errorCode": "DAILY_LIMIT_EXCEEDED",
 *   "message": "Daily verification limit exceeded. Try again tomorrow."
 * }
 */
class InitiateAction
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

        $phoneNumber = trim($data['phoneNumber']);

        if (empty($phoneNumber)) {
            throw new BadRequestHttpException('phoneNumber cannot be empty');
        }

        try {
            // Initiate verification
            // ByonService returns ByonInitiateResult::success() on success
            // or throws ByonException on failure
            $result = $this->byonService->initiate($company, $phoneNumber);

            $this->logger->info('BYON verification initiated', [
                'company_id' => $company->getId(),
                'phone_number' => substr($phoneNumber, 0, -4) . '****',
            ]);

            return new JsonResponse($result->toArray());

        } catch (ByonException $e) {
            $this->logger->warning('BYON initiation rejected', [
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
            $this->logger->error('BYON initiation failed unexpectedly', [
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
