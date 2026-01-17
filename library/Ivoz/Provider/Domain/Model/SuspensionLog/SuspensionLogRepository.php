<?php

namespace Ivoz\Provider\Domain\Model\SuspensionLog;

use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;

interface SuspensionLogRepository extends ObjectRepository, Selectable
{
    /**
     * Get suspension logs for a company
     *
     * @param CompanyInterface $company
     * @param int $limit
     * @return SuspensionLogInterface[]
     */
    public function findByCompany(CompanyInterface $company, int $limit = 10): array;

    /**
     * Get the most recent suspension log for a company
     *
     * @param CompanyInterface $company
     * @return SuspensionLogInterface|null
     */
    public function findMostRecentByCompany(CompanyInterface $company): ?SuspensionLogInterface;
}
