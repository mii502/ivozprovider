<?php

declare(strict_types=1);

/**
 * DID Order Approve Action - Approve a pending order
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/DidOrder/ApproveOrderAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-order
 */

namespace Controller\DidOrder;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderRepository;
use Ivoz\Provider\Domain\Service\DidOrder\DidOrderApprovalServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /did-orders/{id}/approve
 *
 * Approve a pending DID order.
 * This provisions the DID to the company and creates an invoice.
 *
 * Success Response (200):
 * {
 *   "success": true,
 *   "orderId": 456,
 *   "status": "approved",
 *   "ddiId": 123,
 *   "ddi": "+34912345678",
 *   "invoiceId": 789,
 *   "invoiceNumber": "DID-1-20260118123456",
 *   "message": "Order approved. DID has been provisioned to the company."
 * }
 *
 * Error Response (400/404):
 * {
 *   "success": false,
 *   "error": "order_not_pending",
 *   "message": "Order #456 cannot be modified (current status: approved)"
 * }
 */
class ApproveOrderAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private DidOrderRepository $didOrderRepository,
        private DidOrderApprovalServiceInterface $approvalService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            throw new ResourceClassNotFoundException('User not found');
        }

        /** @var AdministratorInterface $admin */
        $admin = $token->getUser();
        $brand = $admin->getBrand();

        if (!$brand) {
            throw new NotFoundHttpException('Brand not found');
        }

        // Find the order
        /** @var DidOrderInterface|null $order */
        $order = $this->didOrderRepository->find($id);

        if (!$order) {
            throw new NotFoundHttpException(sprintf('Order #%d not found', $id));
        }

        // Ensure the order belongs to the brand
        $orderCompany = $order->getCompany();
        if ($orderCompany->getBrand()->getId() !== $brand->getId()) {
            throw new NotFoundHttpException(sprintf('Order #%d not found', $id));
        }

        try {
            // Approve the order
            $result = $this->approvalService->approve($order, $admin);

            if ($result->isSuccess()) {
                $this->logger->info('DID order approved successfully', [
                    'order_id' => $id,
                    'admin_id' => $admin->getId(),
                    'company_id' => $orderCompany->getId(),
                    'ddi_id' => $result->getDdi()?->getId(),
                    'invoice_id' => $result->getInvoice()?->getId(),
                ]);

                $response = $result->toArray();
                $response['message'] = 'Order approved. DID has been provisioned to the company.';

                return new JsonResponse($response);
            }

            // Handle specific error codes with appropriate HTTP status
            $statusCode = match ($result->getErrorCode()) {
                'order_not_pending' => 409,           // Conflict
                'ddi_provision_failed' => 500,        // Internal error
                'invoice_creation_failed' => 500,     // Internal error
                default => 400,                        // Bad Request
            };

            $this->logger->warning('DID order approval failed', [
                'order_id' => $id,
                'admin_id' => $admin->getId(),
                'error_code' => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);

            return new JsonResponse($result->toArray(), $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('DID order approval failed unexpectedly', [
                'order_id' => $id,
                'admin_id' => $admin->getId(),
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
