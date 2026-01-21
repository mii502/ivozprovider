<?php

declare(strict_types=1);

/**
 * DID Release Service Interface
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidReleaseServiceInterface.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;

/**
 * Interface for customer-initiated DID release service
 */
interface DidReleaseServiceInterface
{
    /**
     * Release a DID back to the marketplace
     *
     * Validates ownership and status, then uses UnlinkDdi pattern to
     * delete the DDI and recreate it with no company assignment.
     *
     * @param CompanyInterface $company The company releasing the DID
     * @param DdiInterface $ddi The DID to release
     * @return ReleaseResult Success/failure result with details
     */
    public function release(CompanyInterface $company, DdiInterface $ddi): ReleaseResult;

    /**
     * Check if a DID can be released by a company
     *
     * @param CompanyInterface $company The company requesting release
     * @param DdiInterface $ddi The DID to check
     * @return bool True if the DID can be released
     */
    public function canRelease(CompanyInterface $company, DdiInterface $ddi): bool;
}
