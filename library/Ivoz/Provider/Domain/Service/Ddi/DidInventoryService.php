<?php

declare(strict_types=1);

/**
 * DID Inventory Service - Marketplace inventory management
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidInventoryService.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Model\Brand\BrandInterface;

/**
 * Service for querying and managing DID marketplace inventory
 *
 * Provides methods for:
 * - Browsing available DIDs with filtering
 * - Getting available countries with counts
 * - Getting single DID details
 */
class DidInventoryService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DdiRepository $ddiRepository
    ) {
    }

    /**
     * Get available DIDs for the marketplace
     *
     * @param BrandInterface $brand Brand context for DID visibility
     * @param array<string, mixed> $filters Optional filters (country, type, priceMin, priceMax)
     * @param int $page Page number (1-indexed)
     * @param int $itemsPerPage Items per page
     * @return array{items: DdiInterface[], total: int, page: int, itemsPerPage: int}
     */
    public function getAvailableDdis(
        BrandInterface $brand,
        array $filters = [],
        int $page = 1,
        int $itemsPerPage = 20
    ): array {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('d')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->join('d.brand', 'b')
            ->leftJoin('d.country', 'c')
            ->where('d.inventoryStatus = :status')
            ->andWhere('b.id = :brandId')
            ->andWhere('d.company IS NULL')  // Only unassigned DDIs
            ->setParameter('status', DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setParameter('brandId', $brand->getId());

        // Apply filters
        if (!empty($filters['country'])) {
            $qb->andWhere('c.code = :countryCode')
                ->setParameter('countryCode', $filters['country']);
        }

        if (!empty($filters['countryId'])) {
            $qb->andWhere('c.id = :countryId')
                ->setParameter('countryId', $filters['countryId']);
        }

        if (!empty($filters['type'])) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (isset($filters['priceMin'])) {
            $qb->andWhere('d.monthlyPrice >= :priceMin')
                ->setParameter('priceMin', (float) $filters['priceMin']);
        }

        if (isset($filters['priceMax'])) {
            $qb->andWhere('d.monthlyPrice <= :priceMax')
                ->setParameter('priceMax', (float) $filters['priceMax']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('d.ddi LIKE :search')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        // Get total count before pagination
        $countQb = clone $qb;
        $countQb->select('COUNT(d.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply sorting
        $orderBy = $filters['orderBy'] ?? 'monthlyPrice';
        $orderDir = $filters['orderDir'] ?? 'ASC';

        $allowedOrderBy = ['ddi', 'monthlyPrice', 'setupPrice', 'countryName'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'monthlyPrice';
        }

        if ($orderBy === 'countryName') {
            $qb->orderBy('c.name->' . "'en'", $orderDir);
        } else {
            $qb->orderBy('d.' . $orderBy, $orderDir);
        }

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $qb->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
        ];
    }

    /**
     * Get countries that have available DIDs with counts
     *
     * @param BrandInterface $brand Brand context
     * @return array<int, array{code: string, name: string, availableCount: int}>
     */
    public function getAvailableCountries(BrandInterface $brand): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('c.code', 'c.name', 'COUNT(d.id) as availableCount')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->join('d.brand', 'b')
            ->join('d.country', 'c')
            ->where('d.inventoryStatus = :status')
            ->andWhere('b.id = :brandId')
            ->andWhere('d.company IS NULL')
            ->setParameter('status', DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setParameter('brandId', $brand->getId())
            ->groupBy('c.id')
            ->having('COUNT(d.id) > 0')
            ->orderBy('c.name->' . "'en'", 'ASC');

        $results = $qb->getQuery()->getResult();

        // Transform results to include only English name
        return array_map(function ($row) {
            $nameData = $row['name'];
            $name = is_array($nameData) ? ($nameData['en'] ?? reset($nameData)) : (string) $nameData;

            return [
                'code' => $row['code'],
                'name' => $name,
                'availableCount' => (int) $row['availableCount'],
            ];
        }, $results);
    }

    /**
     * Get a single available DID by ID
     *
     * @param BrandInterface $brand Brand context
     * @param int $ddiId DID ID
     * @return DdiInterface|null
     */
    public function getAvailableDdiById(BrandInterface $brand, int $ddiId): ?DdiInterface
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('d')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->join('d.brand', 'b')
            ->where('d.id = :id')
            ->andWhere('d.inventoryStatus = :status')
            ->andWhere('b.id = :brandId')
            ->andWhere('d.company IS NULL')
            ->setParameter('id', $ddiId)
            ->setParameter('status', DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setParameter('brandId', $brand->getId());

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result instanceof DdiInterface ? $result : null;
    }

    /**
     * Get DID types available in the marketplace
     *
     * @param BrandInterface $brand Brand context
     * @return array<int, array{type: string, availableCount: int}>
     */
    public function getAvailableTypes(BrandInterface $brand): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('d.type', 'COUNT(d.id) as availableCount')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->join('d.brand', 'b')
            ->where('d.inventoryStatus = :status')
            ->andWhere('b.id = :brandId')
            ->andWhere('d.company IS NULL')
            ->setParameter('status', DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setParameter('brandId', $brand->getId())
            ->groupBy('d.type')
            ->having('COUNT(d.id) > 0')
            ->orderBy('d.type', 'ASC');

        $results = $qb->getQuery()->getResult();

        return array_map(function ($row) {
            return [
                'type' => $row['type'],
                'availableCount' => (int) $row['availableCount'],
            ];
        }, $results);
    }

    /**
     * Get price range of available DIDs
     *
     * @param BrandInterface $brand Brand context
     * @return array{minSetup: float, maxSetup: float, minMonthly: float, maxMonthly: float}
     */
    public function getPriceRange(BrandInterface $brand): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select(
            'MIN(d.setupPrice) as minSetup',
            'MAX(d.setupPrice) as maxSetup',
            'MIN(d.monthlyPrice) as minMonthly',
            'MAX(d.monthlyPrice) as maxMonthly'
        )
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->join('d.brand', 'b')
            ->where('d.inventoryStatus = :status')
            ->andWhere('b.id = :brandId')
            ->andWhere('d.company IS NULL')
            ->setParameter('status', DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setParameter('brandId', $brand->getId());

        $result = $qb->getQuery()->getSingleResult();

        return [
            'minSetup' => (float) ($result['minSetup'] ?? 0),
            'maxSetup' => (float) ($result['maxSetup'] ?? 0),
            'minMonthly' => (float) ($result['minMonthly'] ?? 0),
            'maxMonthly' => (float) ($result['maxMonthly'] ?? 0),
        ];
    }
}
