<?php

declare(strict_types=1);

/**
 * BYON Validate Action - Pre-validate phone number before sending SMS
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Byon/ValidateAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Controller\Byon;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\Byon\ByonServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /byon/validate
 *
 * Validate phone number format and availability before sending OTP.
 * This allows instant feedback without consuming an SMS credit.
 *
 * Request Body:
 * {
 *   "phoneNumber": "+34612345678"
 * }
 *
 * Success Response (200):
 * {
 *   "valid": true,
 *   "country": {
 *     "id": 68,
 *     "name": "Spain",
 *     "code": "+34"
 *   },
 *   "nationalNumber": "612345678",
 *   "e164Number": "+34612345678"
 * }
 *
 * Error Response (200 with valid=false):
 * {
 *   "valid": false,
 *   "errorCode": "DUPLICATE_NUMBER",
 *   "error": "This number is already registered by another account"
 * }
 */
class ValidateAction
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
            // Validate number
            $result = $this->byonService->validate($company, $phoneNumber);

            $this->logger->debug('BYON validation', [
                'company_id' => $company->getId(),
                'phone_number' => substr($phoneNumber, 0, -4) . '****',
                'valid' => $result->isValid(),
            ]);

            return new JsonResponse($result->toArray());

        } catch (\Exception $e) {
            $this->logger->error('BYON validation failed unexpectedly', [
                'company_id' => $company->getId(),
                'phone_number' => substr($phoneNumber, 0, -4) . '****',
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'valid' => false,
                'errorCode' => 'INTERNAL_ERROR',
                'error' => 'Validation failed. Please try again.',
            ], 500);
        }
    }
}
