<?php

namespace Ivoz\Provider\Domain\Model\DidOrder;

use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

interface DidOrderRepository extends ObjectRepository, Selectable
{
    /**
     * Find pending orders for a specific company
     *
     * @param int $companyId
     * @return DidOrderInterface[]
     */
    public function findPendingByCompany(int $companyId): array;

    /**
     * Find pending orders for a specific brand
     *
     * @param int $brandId
     * @return DidOrderInterface[]
     */
    public function findPendingByBrand(int $brandId): array;

    /**
     * Find expired orders (pending orders with DDI reservation past reservedUntil)
     *
     * @return DidOrderInterface[]
     */
    public function findExpiredOrders(): array;

    /**
     * Count pending orders for a brand (for dashboard widget)
     *
     * @param int $brandId
     * @return int
     */
    public function countPendingByBrand(int $brandId): int;

    /**
     * Find order by DDI (for checking existing pending orders)
     *
     * @param int $ddiId
     * @return DidOrderInterface|null
     */
    public function findPendingByDdi(int $ddiId): ?DidOrderInterface;
}
