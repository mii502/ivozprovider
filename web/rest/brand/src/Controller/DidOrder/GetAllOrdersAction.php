<?php

declare(strict_types=1);

/**
 * DID Order Get All Action - List all orders for brand admin
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/DidOrder/GetAllOrdersAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-order
 */

namespace Controller\DidOrder;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /did-orders
 *
 * List all DID orders for the brand (all statuses).
 * Used by brand admins to see order history.
 *
 * Query Parameters:
 * - _page: Page number (default: 1)
 * - _itemsPerPage: Items per page (default: 20, max: 100)
 * - status: Filter by status (pending_approval, approved, rejected, expired)
 * - company: Filter by company ID
 * - orderBy: Sort field (requestedAt, status)
 * - orderDir: Sort direction (ASC, DESC)
 *
 * Response (plain array for ivoz-ui):
 * [
 *   {
 *     "id": 456,
 *     "company": { "id": 1, "name": "Acme Corp" },
 *     "ddi": "+34912345678",
 *     "ddiId": 123,
 *     "country": "ES",
 *     "status": "approved",
 *     "statusLabel": "Approved",
 *     "setupFee": "5.00",
 *     "monthlyFee": "2.50",
 *     "requestedAt": "2026-01-18T10:30:00+00:00",
 *     "approvedAt": "2026-01-18T11:00:00+00:00",
 *     "approvedBy": "Admin User"
 *   }
 * ]
 *
 * Headers:
 * - X-Total-Count: Total number of orders
 * - X-Page: Current page
 * - X-Items-Per-Page: Items per page
 */
class GetAllOrdersAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $entityManager
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
        $brand = $admin->getBrand();

        if (!$brand) {
            throw new NotFoundHttpException('Brand not found');
        }

        // Extract query parameters
        $page = max(1, (int) $request->query->get('_page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('_itemsPerPage', 20)));
        $status = $request->query->get('status');
        $companyId = $request->query->get('company');
        $orderBy = $request->query->get('orderBy', 'requestedAt');
        $orderDir = strtoupper($request->query->get('orderDir', 'DESC'));

        // Validate order fields
        $allowedOrderBy = ['requestedAt', 'status', 'setupFee', 'monthlyFee', 'approvedAt', 'rejectedAt'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'requestedAt';
        }
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        // Build query for brand's orders
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from('Ivoz\Provider\Domain\Model\DidOrder\DidOrder', 'o')
            ->leftJoin('o.company', 'company')
            ->leftJoin('o.ddi', 'd')
            ->leftJoin('d.country', 'c')
            ->leftJoin('o.approvedBy', 'approver')
            ->where('company.brand = :brand')
            ->setParameter('brand', $brand)
            ->orderBy('o.' . $orderBy, $orderDir);

        // Filter by status if provided
        if ($status !== null) {
            $validStatuses = [
                DidOrderInterface::STATUS_PENDING_APPROVAL,
                DidOrderInterface::STATUS_APPROVED,
                DidOrderInterface::STATUS_REJECTED,
                DidOrderInterface::STATUS_EXPIRED,
            ];
            if (in_array($status, $validStatuses, true)) {
                $qb->andWhere('o.status = :status')
                    ->setParameter('status', $status);
            }
        }

        // Filter by company if provided
        if ($companyId !== null) {
            $companyIdInt = filter_var($companyId, FILTER_VALIDATE_INT);
            if ($companyIdInt !== false && $companyIdInt > 0) {
                $qb->andWhere('company.id = :companyId')
                    ->setParameter('companyId', $companyIdInt);
            }
        }

        // Get total count
        $countQb = clone $qb;
        $countQb->select('COUNT(o.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $qb->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        /** @var DidOrderInterface[] $orders */
        $orders = $qb->getQuery()->getResult();

        // Transform to response format
        $members = array_map(
            fn(DidOrderInterface $order) => $this->transformOrder($order),
            $orders
        );

        // Return plain array with pagination headers (ivoz-ui format - Rule 8)
        $response = new JsonResponse($members);
        $response->headers->set('X-Total-Count', (string) $total);
        $response->headers->set('X-Page', (string) $page);
        $response->headers->set('X-Items-Per-Page', (string) $itemsPerPage);

        return $response;
    }

    /**
     * Transform a DidOrder entity to the API response format
     *
     * @param DidOrderInterface $order
     * @return array<string, mixed>
     */
    private function transformOrder(DidOrderInterface $order): array
    {
        $company = $order->getCompany();
        $ddi = $order->getDdi();
        $country = $ddi?->getCountry();
        $approvedBy = $order->getApprovedBy();

        $countryName = null;
        if ($country !== null) {
            $nameObj = $country->getName();
            if ($nameObj !== null && method_exists($nameObj, 'getEn')) {
                $countryName = $nameObj->getEn();
            }
        }

        return [
            '@id' => '/api/brand/did-orders/' . $order->getId(),
            'id' => $order->getId(),
            'company' => $company->getId(),
            'companyName' => $company->getName(),
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
