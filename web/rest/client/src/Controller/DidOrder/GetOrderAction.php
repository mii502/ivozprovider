<?php

declare(strict_types=1);

/**
 * DID Order Get Action - Get single order details
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/DidOrder/GetOrderAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-order
 */

namespace Controller\DidOrder;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /did-orders/{id}
 *
 * Get details of a specific DID order.
 * Only returns orders belonging to the authenticated company.
 *
 * Response:
 * {
 *   "id": 456,
 *   "ddi": "+34912345678",
 *   "ddiId": 123,
 *   "country": "ES",
 *   "countryName": "Spain",
 *   "status": "pending_approval",
 *   "statusLabel": "Pending Approval",
 *   "setupFee": "5.00",
 *   "monthlyFee": "2.50",
 *   "requestedAt": "2026-01-18T10:30:00+00:00",
 *   "approvedAt": null,
 *   "rejectedAt": null,
 *   "rejectionReason": null,
 *   "reservedUntil": "2026-01-19T10:30:00+00:00"
 * }
 */
class GetOrderAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private DidOrderRepository $didOrderRepository
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
        $company = $admin->getCompany();

        if (!$company) {
            throw new NotFoundHttpException('Company not found');
        }

        // Find the order
        /** @var DidOrderInterface|null $order */
        $order = $this->didOrderRepository->find($id);

        if (!$order) {
            throw new NotFoundHttpException(sprintf('Order #%d not found', $id));
        }

        // Ensure the order belongs to the authenticated company
        if ($order->getCompany()->getId() !== $company->getId()) {
            throw new NotFoundHttpException(sprintf('Order #%d not found', $id));
        }

        return new JsonResponse($this->transformOrder($order));
    }

    /**
     * Transform a DidOrder entity to the API response format
     *
     * @param DidOrderInterface $order
     * @return array<string, mixed>
     */
    private function transformOrder(DidOrderInterface $order): array
    {
        $ddi = $order->getDdi();
        $country = $ddi?->getCountry();

        $countryName = null;
        if ($country !== null) {
            $nameObj = $country->getName();
            if ($nameObj !== null && method_exists($nameObj, 'getEn')) {
                $countryName = $nameObj->getEn();
            }
        }

        $approvedBy = $order->getApprovedBy();

        return [
            '@id' => '/api/client/did-orders/' . $order->getId(),
            'id' => $order->getId(),
            'ddi' => $ddi?->getDdie164(),
            'ddiNumber' => $ddi?->getDdi(),
            'ddiId' => $ddi?->getId(),
            'country' => $country?->getCode(),
            'countryName' => $countryName,
            'status' => $order->getStatus(),
            'statusLabel' => $this->getStatusLabel($order->getStatus()),
            'setupFee' => number_format((float) $order->getSetupFee(), 2, '.', ''),
            'monthlyFee' => number_format((float) $order->getMonthlyFee(), 2, '.', ''),
            'requestedAt' => $order->getRequestedAt()?->format('c'),
            'approvedAt' => $order->getApprovedAt()?->format('c'),
            'approvedBy' => $approvedBy?->getName(),
            'rejectedAt' => $order->getRejectedAt()?->format('c'),
            'rejectionReason' => $order->getRejectionReason(),
            'reservedUntil' => $ddi?->getReservedUntil()?->format('c'),
        ];
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            DidOrderInterface::STATUS_PENDING_APPROVAL => 'Pending Approval',
            DidOrderInterface::STATUS_APPROVED => 'Approved',
            DidOrderInterface::STATUS_REJECTED => 'Rejected',
            DidOrderInterface::STATUS_EXPIRED => 'Expired',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
