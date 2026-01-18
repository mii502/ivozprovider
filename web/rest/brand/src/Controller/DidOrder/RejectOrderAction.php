<?php

declare(strict_types=1);

/**
 * DID Order Reject Action - Reject a pending order
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/DidOrder/RejectOrderAction.php
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /did-orders/{id}/reject
 *
 * Reject a pending DID order with a reason.
 * This releases the DID reservation.
 *
 * Request Body:
 * {
 *   "reason": "DID not available in this region for your account type."
 * }
 *
 * Success Response (200):
 * {
 *   "success": true,
 *   "orderId": 456,
 *   "status": "rejected",
 *   "message": "Order rejected. The DID reservation has been released."
 * }
 *
 * Error Response (400/404):
 * {
 *   "success": false,
 *   "error": "order_not_pending",
 *   "message": "Order #456 cannot be modified (current status: rejected)"
 * }
 */
class RejectOrderAction
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

        // Parse request body
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        // Reason is required
        $reason = trim($data['reason'] ?? '');
        if (empty($reason)) {
            throw new BadRequestHttpException('reason is required');
        }

        // Limit reason length
        if (strlen($reason) > 1000) {
            throw new BadRequestHttpException('reason must be 1000 characters or less');
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
            // Reject the order
            $result = $this->approvalService->reject($order, $reason);

            if ($result->isSuccess()) {
                $this->logger->info('DID order rejected', [
                    'order_id' => $id,
                    'admin_id' => $admin->getId(),
                    'company_id' => $orderCompany->getId(),
                    'reason' => $reason,
                ]);

                $response = $result->toArray();
                $response['message'] = 'Order rejected. The DID reservation has been released.';

                return new JsonResponse($response);
            }

            // Handle specific error codes with appropriate HTTP status
            $statusCode = match ($result->getErrorCode()) {
                'order_not_pending' => 409, // Conflict
                default => 400,              // Bad Request
            };

            $this->logger->warning('DID order rejection failed', [
                'order_id' => $id,
                'admin_id' => $admin->getId(),
                'error_code' => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);

            return new JsonResponse($result->toArray(), $statusCode);

        } catch (\Exception $e) {
            $this->logger->error('DID order rejection failed unexpectedly', [
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
