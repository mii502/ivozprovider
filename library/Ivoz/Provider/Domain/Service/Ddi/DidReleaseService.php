<?php

declare(strict_types=1);

/**
 * DID Release Service
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidReleaseService.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for customer-initiated DID release
 *
 * Allows customers to voluntarily release their purchased DIDs back to
 * the marketplace. Uses the UnlinkDdi pattern to cleanly remove company
 * association while preserving the phone number for resale.
 *
 * Key behaviors:
 * - No refund (prepaid model - customer paid for current period)
 * - No minimum holding period
 * - DDI returns to marketplace as 'available'
 * - Invoice.ddiE164 preserved for historical queries
 *
 * @see UnlinkDdi For the underlying delete/recreate pattern
 * @see DidRenewalOverdueHandler For similar usage pattern (non-payment release)
 */
class DidReleaseService implements DidReleaseServiceInterface
{
    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly UnlinkDdi $unlinkDdiService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function release(CompanyInterface $company, DdiInterface $ddi): ReleaseResult
    {
        // 1. Validate ownership
        $ddiCompany = $ddi->getCompany();
        if ($ddiCompany === null || $ddiCompany->getId() !== $company->getId()) {
            $this->logger->warning('DID release rejected: not owned by company', [
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
                'ddi_company_id' => $ddiCompany?->getId(),
                'requesting_company_id' => $company->getId(),
            ]);
            return ReleaseResult::ddiNotOwned();
        }

        // 2. Block BYON release (customer cannot release - only brand admin)
        if ($ddi->getIsByon()) {
            $this->logger->warning('DID release rejected: BYON DDI cannot be released by customer', [
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
                'company_id' => $company->getId(),
            ]);
            return ReleaseResult::byonCannotRelease();
        }

        // 3. Validate status
        if ($ddi->getInventoryStatus() !== DdiInterface::INVENTORYSTATUS_ASSIGNED) {
            $this->logger->warning('DID release rejected: not assigned', [
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
                'inventory_status' => $ddi->getInventoryStatus(),
            ]);
            return ReleaseResult::ddiNotAssigned();
        }

        // 4. Capture data before UnlinkDdi (it deletes the entity)
        $setupPrice = $ddi->getSetupPrice();
        $monthlyPrice = $ddi->getMonthlyPrice();
        $ddiNumber = $ddi->getDdie164();
        $oldDdiId = $ddi->getId();

        try {
            // 5. Execute UnlinkDdi (deletes DDI, creates new with no company)
            // Invoice.ddiId FK will become NULL (ON DELETE SET NULL)
            // Invoice.ddiE164 remains intact for historical reference
            $newDdi = $this->unlinkDdiService->execute($ddi);

            // 6. Set inventory fields on new DDI
            $newDdiDto = $newDdi->toDto();
            $newDdiDto
                ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_AVAILABLE)
                ->setAssignedAt(null)
                ->setNextRenewalAt(null)
                ->setSetupPrice($setupPrice)
                ->setMonthlyPrice($monthlyPrice);
            $this->entityTools->persistDto($newDdiDto, $newDdi, true);

            // 7. Log the release
            $this->logger->info('Customer released DID voluntarily', [
                'old_ddi_id' => $oldDdiId,
                'new_ddi_id' => $newDdi->getId(),
                'ddi_number' => $ddiNumber,
                'company_id' => $company->getId(),
                'company_name' => $company->getName(),
            ]);

            return ReleaseResult::success($newDdi->getId(), $ddiNumber);
        } catch (\Throwable $e) {
            $this->logger->error('DID release failed', [
                'ddi_id' => $oldDdiId,
                'ddi_number' => $ddiNumber,
                'company_id' => $company->getId(),
                'error' => $e->getMessage(),
            ]);
            return ReleaseResult::releaseFailed($e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function canRelease(CompanyInterface $company, DdiInterface $ddi): bool
    {
        // Must be owned by this company
        $ddiCompany = $ddi->getCompany();
        if ($ddiCompany === null || $ddiCompany->getId() !== $company->getId()) {
            return false;
        }

        // BYON DDIs cannot be released by customer (only brand admin)
        if ($ddi->getIsByon()) {
            return false;
        }

        // Must be in assigned status
        if ($ddi->getInventoryStatus() !== DdiInterface::INVENTORYSTATUS_ASSIGNED) {
            return false;
        }

        return true;
    }
}
