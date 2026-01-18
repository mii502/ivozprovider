<?php

declare(strict_types=1);

/**
 * DID Order Create Action - Create postpaid DID order
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/DidOrder/CreateOrderAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-order
 */

namespace Controller\DidOrder;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\Ddi\DidInventoryService;
use Ivoz\Provider\Domain\Service\DidOrder\DidOrderServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /did-orders
 *
 * Create a new DID order for a postpaid company.
 * The DID will be reserved for 24 hours pending admin approval.
 *
 * Request Body:
 * {
 *   "ddiId": 123
 * }
 *
 * Success Response (201):
 * {
 *   "success": true,
 *   "orderId": 456,
 *   "status": "pending_approval",
 *   "ddiId": 123,
 *   "ddi": "+34912345678",
 *   "message": "Your DID order has been submitted for approval."
 * }
 *
 * Error Response (400/403):
 * {
 *   "success": false,
 *   "error": "company_not_postpaid",
 *   "message": "DID ordering is only available for postpaid accounts."
 * }
 */
class CreateOrderAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private DidInventoryService $didInventoryService,
        private DidOrderServiceInterface $didOrderService,
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
                'error' => 'ddi_not_available',
                'message' => sprintf('DID %d not found or not available for ordering', $ddiId),
            ], 404);
        }

        try {
            // Create order
            $result = $this->didOrderService->createOrder($company, $ddi);

            if ($result->isSuccess()) {
                $this->logger->info('DID order created successfully', [
                    'company_id' => $company->getId(),
                    'ddi_id' => $ddiId,
                    'order_id' => $result->getOrder()?->getId(),
                ]);

                $response = $result->toArray();
                $response['message'] = 'Your DID order has been submitted for approval.';

                return new JsonResponse($response, 201);
            }

            // Handle specific error codes with appropriate HTTP status
            $statusCode = match ($result->getErrorCode()) {
                'company_not_postpaid' => 403, // Forbidden
                'ddi_not_available' => 409,    // Conflict (someone else got it)
                default => 400,                 // Bad Request
            };

            $this->logger->warning('DID order creation failed', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'error_code' => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);

            return new JsonResponse($result->toArray(), $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('DID order creation failed unexpectedly', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}
