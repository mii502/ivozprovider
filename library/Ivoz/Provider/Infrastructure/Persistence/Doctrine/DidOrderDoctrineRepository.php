<?php

namespace Ivoz\Provider\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrder;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * DidOrderDoctrineRepository
 *
 * @template-extends ServiceEntityRepository<DidOrder>
 */
class DidOrderDoctrineRepository extends ServiceEntityRepository implements DidOrderRepository
{
    public const ENTITY_ALIAS = 'didOrder';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DidOrder::class);
    }

    /**
     * @inheritDoc
     */
    public function findPendingByCompany(int $companyId): array
    {
        $qb = $this->createQueryBuilder(self::ENTITY_ALIAS);
        $qb
            ->where(self::ENTITY_ALIAS . '.company = :companyId')
            ->andWhere(self::ENTITY_ALIAS . '.status = :status')
            ->setParameter('companyId', $companyId)
            ->setParameter('status', DidOrderInterface::STATUS_PENDING_APPROVAL)
            ->orderBy(self::ENTITY_ALIAS . '.requestedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritDoc
     */
    public function findPendingByBrand(int $brandId): array
    {
        $qb = $this->createQueryBuilder(self::ENTITY_ALIAS);
        $qb
            ->innerJoin(self::ENTITY_ALIAS . '.company', 'company')
            ->where('company.brand = :brandId')
            ->andWhere(self::ENTITY_ALIAS . '.status = :status')
            ->setParameter('brandId', $brandId)
            ->setParameter('status', DidOrderInterface::STATUS_PENDING_APPROVAL)
            ->orderBy(self::ENTITY_ALIAS . '.requestedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritDoc
     */
    public function findExpiredOrders(): array
    {
        $qb = $this->createQueryBuilder(self::ENTITY_ALIAS);
        $qb
            ->innerJoin(self::ENTITY_ALIAS . '.ddi', 'ddi')
            ->where(self::ENTITY_ALIAS . '.status = :status')
            ->andWhere('ddi.reservedUntil < :now')
            ->setParameter('status', DidOrderInterface::STATUS_PENDING_APPROVAL)
            ->setParameter('now', new \DateTime());

        return $qb->getQuery()->getResult();
    }

    /**
     * @inheritDoc
     */
    public function countPendingByBrand(int $brandId): int
    {
        $qb = $this->createQueryBuilder(self::ENTITY_ALIAS);
        $qb
            ->select('count(' . self::ENTITY_ALIAS . ')')
            ->innerJoin(self::ENTITY_ALIAS . '.company', 'company')
            ->where('company.brand = :brandId')
            ->andWhere(self::ENTITY_ALIAS . '.status = :status')
            ->setParameter('brandId', $brandId)
            ->setParameter('status', DidOrderInterface::STATUS_PENDING_APPROVAL);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function findPendingByDdi(int $ddiId): ?DidOrderInterface
    {
        $qb = $this->createQueryBuilder(self::ENTITY_ALIAS);
        $qb
            ->where(self::ENTITY_ALIAS . '.ddi = :ddiId')
            ->andWhere(self::ENTITY_ALIAS . '.status = :status')
            ->setParameter('ddiId', $ddiId)
            ->setParameter('status', DidOrderInterface::STATUS_PENDING_APPROVAL)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
