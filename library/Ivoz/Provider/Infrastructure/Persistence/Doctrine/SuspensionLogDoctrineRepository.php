<?php

namespace Ivoz\Provider\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Ivoz\Provider\Domain\Model\SuspensionLog\SuspensionLog;
use Ivoz\Provider\Domain\Model\SuspensionLog\SuspensionLogInterface;
use Ivoz\Provider\Domain\Model\SuspensionLog\SuspensionLogRepository;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * SuspensionLogDoctrineRepository
 *
 * @template-extends ServiceEntityRepository<SuspensionLog>
 */
class SuspensionLogDoctrineRepository extends ServiceEntityRepository implements SuspensionLogRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuspensionLog::class);
    }

    /**
     * Get suspension logs for a company
     *
     * @param CompanyInterface $company
     * @param int $limit
     * @return SuspensionLogInterface[]
     */
    public function findByCompany(CompanyInterface $company, int $limit = 10): array
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.company = :company')
            ->setParameter('company', $company)
            ->orderBy('sl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the most recent suspension log for a company
     *
     * @param CompanyInterface $company
     * @return SuspensionLogInterface|null
     */
    public function findMostRecentByCompany(CompanyInterface $company): ?SuspensionLogInterface
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.company = :company')
            ->setParameter('company', $company)
            ->orderBy('sl.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
