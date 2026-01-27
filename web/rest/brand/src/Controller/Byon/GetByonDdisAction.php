<?php

declare(strict_types=1);

/**
 * BYON DDIs List Controller (Brand API)
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/Byon/GetByonDdisAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon (Phase 4)
 */

namespace Controller\Byon;

use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * List all BYON DDIs within the brand
 *
 * GET /byon/ddis
 * Query params:
 *   - company (optional): Filter by company ID
 *   - page (optional): Page number (default 1)
 *   - itemsPerPage (optional): Items per page (default 25)
 */
class GetByonDdisAction
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var TokenInterface $token */
        $token = $this->tokenStorage->getToken();

        /** @var AdministratorInterface $admin */
        $admin = $token->getUser();
        $brand = $admin->getBrand();

        if ($brand === null) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'BRAND_NOT_FOUND',
                'message' => 'Brand not found for admin',
            ], 404);
        }

        $brandId = $brand->getId();
        $companyId = $request->query->get('company');
        $page = max(1, (int) ($request->query->get('page', 1)));
        $itemsPerPage = max(1, min(100, (int) ($request->query->get('itemsPerPage', 25))));
        $offset = ($page - 1) * $itemsPerPage;

        // Build query for BYON DDIs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select([
            'd.id',
            'd.ddie164',
            'd.ddi',
            'd.description',
            'd.isByon',
            'c.id as companyId',
            'c.name as companyName',
            'bv.verifiedAt',
        ])
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->leftJoin('d.company', 'c')
            ->leftJoin('d.byonVerification', 'bv')
            ->where('d.brand = :brandId')
            ->andWhere('d.isByon = :isByon')
            ->setParameter('brandId', $brandId)
            ->setParameter('isByon', true)
            ->orderBy('bv.verifiedAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        // Optional company filter
        if ($companyId !== null) {
            $qb->andWhere('d.company = :companyId')
                ->setParameter('companyId', (int) $companyId);
        }

        $results = $qb->getQuery()->getArrayResult();

        // Get total count
        $countQb = $this->entityManager->createQueryBuilder();
        $countQb->select('COUNT(d.id)')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->where('d.brand = :brandId')
            ->andWhere('d.isByon = :isByon')
            ->setParameter('brandId', $brandId)
            ->setParameter('isByon', true);

        if ($companyId !== null) {
            $countQb->andWhere('d.company = :companyId')
                ->setParameter('companyId', (int) $companyId);
        }

        $totalCount = (int) $countQb->getQuery()->getSingleScalarResult();

        // Format response
        $items = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'ddiE164' => $row['ddie164'],
                'ddi' => $row['ddi'],
                'description' => $row['description'],
                'isByon' => $row['isByon'],
                'companyId' => $row['companyId'],
                'companyName' => $row['companyName'],
                'verifiedAt' => $row['verifiedAt'] instanceof \DateTimeInterface
                    ? $row['verifiedAt']->format(\DateTimeInterface::ATOM)
                    : null,
            ];
        }, $results);

        $response = new JsonResponse($items);
        $response->headers->set('X-Total-Count', (string) $totalCount);
        $response->headers->set('X-Page', (string) $page);
        $response->headers->set('X-Items-Per-Page', (string) $itemsPerPage);

        return $response;
    }
}
