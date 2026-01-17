<?php

namespace Ivoz\Provider\Domain\Model\Ddi;

use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

interface DdiRepository extends ObjectRepository, Selectable
{
    /**
     * @param string $ddiE164
     * @return DdiInterface | null
     */
    public function findOneByDdiE164($ddiE164);

    /**
     * @return \Ivoz\Provider\Domain\Model\Ddi\DdiInterface | null
     */
    public function findOneByDdiAndCountry(string $ddi, int $countryId);

    /**
     * @return int Number of DDIs of the given Company
     */
    public function countByCompany(int $companyId): int;

    /**
     * @return int Number of DDIs of the given Company and Country as prefix
     */
    public function countByCompanyAndCountry(int $companyId, int $countryId): int;

    /**
     * @return int Number of DDIs of the given Company and not Country as prefix
     */
    public function countByCompanyAndNotCountry(int $companyId, int $countryId): int;

    public function countByBrand(int $brandId): int;

    /**
     * Find DIDs due for renewal grouped by company
     *
     * Returns DIDs where:
     * - nextRenewalAt <= given date
     * - Company.billingMethod is prepaid or pseudoprepaid
     * - Company.whmcsClientId is set (linked to WHMCS)
     * - inventoryStatus = 'assigned'
     * - monthlyPrice > 0 (excludes free/BYON DIDs)
     *
     * @param \DateTimeInterface $date Date to check renewals against
     * @return array<int, DdiInterface[]> Array of DDIs keyed by company ID
     */
    public function findDdisForRenewalGroupedByCompany(\DateTimeInterface $date): array;
}
