<?php

declare(strict_types=1);

/**
 * DID Purchase Action - Execute DID purchase with balance billing
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/DidPurchase/PurchaseAction.php
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
 * POST /dids/purchase
 *
 * Execute DID purchase with balance-first billing.
 * Requires sufficient company balance - no WHMCS fallback.
 *
 * Request Body:
 * {
 *   "ddiId": 123
 * }
 *
 * Success Response (200):
 * {
 *   "success": true,
 *   "ddiId": 123,
 *   "ddi": "+34912345678",
 *   "invoiceId": 456,
 *   "invoiceNumber": "DID-1-20260117123456",
 *   "totalCharged": 6.50,
 *   "currentBalance": 93.50
 * }
 *
 * Error Response (400/402):
 * {
 *   "success": false,
 *   "errorCode": "INSUFFICIENT_BALANCE",
 *   "errorMessage": "Insufficient balance. Required: 6.50, Available: 5.00",
 *   "currentBalance": 5.00,
 *   "requiredAmount": 6.50
 * }
 */
class PurchaseAction
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
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'DDI_NOT_AVAILABLE',
                'errorMessage' => sprintf('DID %d not found or not available for purchase', $ddiId),
            ], 404);
        }

        try {
            // Execute purchase
            $result = $this->didPurchaseService->purchase($company, $ddi);

            if ($result->isSuccess()) {
                $this->logger->info('DID purchased successfully', [
                    'company_id' => $company->getId(),
                    'ddi_id' => $ddiId,
                    'invoice_id' => $result->getInvoice()?->getId(),
                    'total_charged' => $result->getTotalCharged(),
                ]);

                return new JsonResponse($result->toArray());
            }

            // Handle specific error codes with appropriate HTTP status
            $statusCode = match ($result->getErrorCode()) {
                'INSUFFICIENT_BALANCE' => 402, // Payment Required
                'DDI_NOT_AVAILABLE' => 409,    // Conflict (someone else got it)
                'DDI_NOT_FOUND' => 404,        // Not Found
                default => 400,                 // Bad Request
            };

            $this->logger->warning('DID purchase failed', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'error_code' => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);

            return new JsonResponse($result->toArray(), $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('DID purchase failed unexpectedly', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'errorCode' => 'INTERNAL_ERROR',
                'errorMessage' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}
