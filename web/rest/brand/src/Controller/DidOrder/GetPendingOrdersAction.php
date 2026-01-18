<?php

declare(strict_types=1);

/**
 * DID Order Get Pending Action - List pending orders for brand admin
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/DidOrder/GetPendingOrdersAction.php
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
 * GET /did-orders/pending
 *
 * List all pending DID orders for the brand.
 * Used by brand admins to see orders awaiting approval.
 *
 * Query Parameters:
 * - _page: Page number (default: 1)
 * - _itemsPerPage: Items per page (default: 20, max: 100)
 * - company: Filter by company ID
 * - orderBy: Sort field (requestedAt)
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
 *     "setupFee": "5.00",
 *     "monthlyFee": "2.50",
 *     "requestedAt": "2026-01-18T10:30:00+00:00",
 *     "reservedUntil": "2026-01-19T10:30:00+00:00"
 *   }
 * ]
 *
 * Headers:
 * - X-Total-Count: Total number of pending orders
 * - X-Page: Current page
 * - X-Items-Per-Page: Items per page
 */
class GetPendingOrdersAction
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
        $companyId = $request->query->get('company');
        $orderBy = $request->query->get('orderBy', 'requestedAt');
        $orderDir = strtoupper($request->query->get('orderDir', 'ASC'));

        // Validate order fields
        $allowedOrderBy = ['requestedAt', 'setupFee', 'monthlyFee'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'requestedAt';
        }
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        // Build query for brand's pending orders
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from('Ivoz\Provider\Domain\Model\DidOrder\DidOrder', 'o')
            ->leftJoin('o.company', 'company')
            ->leftJoin('o.ddi', 'd')
            ->leftJoin('d.country', 'c')
            ->where('company.brand = :brand')
            ->andWhere('o.status = :status')
            ->setParameter('brand', $brand)
            ->setParameter('status', DidOrderInterface::STATUS_PENDING_APPROVAL)
            ->orderBy('o.' . $orderBy, $orderDir);

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
            'company' => [
                'id' => $company->getId(),
                'name' => $company->getName(),
            ],
            'ddi' => $ddi?->getDdie164(),
            'ddiNumber' => $ddi?->getDdi(),
            'ddiId' => $ddi?->getId(),
            'country' => $country?->getCode(),
            'countryName' => $countryName,
            'status' => $order->getStatus(),
            'setupFee' => number_format((float) $order->getSetupFee(), 2, '.', ''),
            'monthlyFee' => number_format((float) $order->getMonthlyFee(), 2, '.', ''),
            'requestedAt' => $order->getRequestedAt()?->format('c'),
            'reservedUntil' => $ddi?->getReservedUntil()?->format('c'),
        ];
    }
}
