<?php

declare(strict_types=1);

/**
 * BYON Verification Doctrine Repository
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Infrastructure/Persistence/Doctrine/ByonVerificationDoctrineRepository.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ivoz\Provider\Domain\Model\ByonVerification\ByonVerification;
use Ivoz\Provider\Domain\Model\ByonVerification\ByonVerificationInterface;
use Ivoz\Provider\Domain\Model\ByonVerification\ByonVerificationRepository;

/**
 * Doctrine implementation of ByonVerificationRepository
 *
 * @template-extends ServiceEntityRepository<ByonVerification>
 */
class ByonVerificationDoctrineRepository extends ServiceEntityRepository implements ByonVerificationRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ByonVerification::class);
    }

    /**
     * @inheritDoc
     */
    public function countByCompanyToday(int $companyId, \DateTimeInterface $today): int
    {
        $qb = $this->createQueryBuilder('self');

        return (int) $qb
            ->select('COUNT(self.id)')
            ->where('self.company = :companyId')
            ->andWhere('self.createdAt >= :today')
            ->setParameter('companyId', $companyId)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @inheritDoc
     */
    public function findPendingByPhoneNumber(string $phoneNumber): ?ByonVerificationInterface
    {
        $qb = $this->createQueryBuilder('self');

        /** @var ByonVerificationInterface|null $result */
        $result = $qb
            ->where('self.phoneNumber = :phoneNumber')
            ->andWhere('self.status = :status')
            ->setParameter('phoneNumber', $phoneNumber)
            ->setParameter('status', ByonVerificationInterface::STATUS_PENDING)
            ->orderBy('self.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function findApprovedByPhoneNumber(string $phoneNumber): ?ByonVerificationInterface
    {
        $qb = $this->createQueryBuilder('self');

        /** @var ByonVerificationInterface|null $result */
        $result = $qb
            ->where('self.phoneNumber = :phoneNumber')
            ->andWhere('self.status = :status')
            ->setParameter('phoneNumber', $phoneNumber)
            ->setParameter('status', ByonVerificationInterface::STATUS_APPROVED)
            ->orderBy('self.verifiedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function findByCompany(int $companyId): array
    {
        $qb = $this->createQueryBuilder('self');

        /** @var ByonVerificationInterface[] $result */
        $result = $qb
            ->where('self.company = :companyId')
            ->setParameter('companyId', $companyId)
            ->orderBy('self.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
