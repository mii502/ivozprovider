<?php

declare(strict_types=1);

/**
 * DID Renewal Overdue Handler
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidRenewalOverdueHandler.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-renewal (Phase 5)
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for releasing DIDs back to inventory on WHMCS overdue webhook
 *
 * When a DID renewal invoice is marked overdue (non-payment), this handler:
 * 1. Uses UnlinkDdi pattern to delete and recreate DDI (preserves E.164 number)
 * 2. Sets inventory status to 'available' on the new DDI
 * 3. Clears dates (assignedAt, nextRenewalAt)
 *
 * The Invoice.ddiE164 field preserves the historical phone number reference
 * even after the original DDI entity is deleted.
 *
 * This is a permanent release - the customer must repurchase the DID if available.
 *
 * @see DidRenewalService For the daily renewal cron that creates these invoices
 * @see WhmcsOverdueWebhookController For the webhook that calls this handler
 * @see UnlinkDdi For the DDI unlink pattern used here
 */
class DidRenewalOverdueHandler implements DidRenewalOverdueHandlerInterface
{
    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly DdiRepository $ddiRepository,
        private readonly LoggerInterface $logger,
        private readonly UnlinkDdi $unlinkDdiService
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(string $invoiceType): bool
    {
        return $invoiceType === InvoiceInterface::INVOICE_TYPE_DID_RENEWAL;
    }

    /**
     * @inheritDoc
     */
    public function handle(InvoiceInterface $invoice): array
    {
        $invoiceType = $invoice->getInvoiceType();

        if ($invoiceType !== InvoiceInterface::INVOICE_TYPE_DID_RENEWAL) {
            throw new \DomainException(sprintf(
                'DidRenewalOverdueHandler cannot handle invoice type: %s',
                $invoiceType
            ));
        }

        $this->logger->info('Processing overdue DID renewal invoice', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumber(),
            'company_id' => $invoice->getCompany()->getId(),
            'company_name' => $invoice->getCompany()->getName(),
        ]);

        $releasedDdis = [];

        // Strategy 1: Single DDI invoice - get DDI directly from invoice
        $linkedDdi = $invoice->getDdi();
        if ($linkedDdi !== null) {
            $releaseResult = $this->releaseDdi($linkedDdi, $invoice);
            if ($releaseResult !== null) {
                $releasedDdis[] = $releaseResult;
            }
        } else {
            // Strategy 2: Multi-DDI invoice - find DIDs by company that are overdue
            // Note: When invoice went to WHMCS, the DDIs were NOT advanced in nextRenewalAt
            // So we can find them by looking for assigned DDIs due for renewal
            $this->logger->info('Multi-DDI invoice detected, finding overdue DIDs for company', [
                'invoice_id' => $invoice->getId(),
                'company_id' => $invoice->getCompany()->getId(),
            ]);

            $overdueDdis = $this->findOverdueDdisForCompany($invoice);

            foreach ($overdueDdis as $ddi) {
                $releaseResult = $this->releaseDdi($ddi, $invoice);
                if ($releaseResult !== null) {
                    $releasedDdis[] = $releaseResult;
                }
            }
        }

        $this->logger->warning('DID renewal overdue: Released DIDs back to inventory', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumber(),
            'company_id' => $invoice->getCompany()->getId(),
            'released_count' => count($releasedDdis),
            'released_ddis' => array_column($releasedDdis, 'ddi_number'),
        ]);

        return [
            'action' => 'ddi_released',
            'released_ddis' => $releasedDdis,
            'count' => count($releasedDdis),
        ];
    }

    /**
     * Release a single DDI back to inventory using UnlinkDdi pattern
     *
     * Uses IvozProvider's standard UnlinkDdi service which:
     * 1. Deletes the existing DDI entity
     * 2. Creates a new DDI with same number but no company assignment
     *
     * The Invoice.ddiE164 field preserves the historical phone number reference
     * even though Invoice.ddi FK will become NULL after deletion.
     *
     * @param DdiInterface $ddi
     * @param InvoiceInterface $invoice For logging context
     * @return array{ddi_number: string, previous_company_id: int, new_ddi_id: int}|null
     */
    private function releaseDdi(DdiInterface $ddi, InvoiceInterface $invoice): ?array
    {
        // Skip if already available (idempotency)
        if ($ddi->getInventoryStatus() === DdiInterface::INVENTORYSTATUS_AVAILABLE) {
            $this->logger->debug('DDI already available, skipping', [
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
            ]);
            return null;
        }

        $previousCompanyId = $ddi->getCompany()?->getId();
        $ddiNumber = $ddi->getDdie164();
        $oldDdiId = $ddi->getId();

        // Preserve pricing info for inventory (UnlinkDdi doesn't preserve these)
        $setupPrice = $ddi->getSetupPrice();
        $monthlyPrice = $ddi->getMonthlyPrice();

        // Use UnlinkDdi pattern - deletes DDI and recreates with no company
        // Invoice.ddiId FK will become NULL (ON DELETE SET NULL)
        // Invoice.ddiE164 remains intact for historical reference
        $newDdi = $this->unlinkDdiService->execute($ddi);

        // Set inventory fields on the new DDI
        $newDdiDto = $newDdi->toDto();
        $newDdiDto
            ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setAssignedAt(null)
            ->setNextRenewalAt(null)
            ->setSetupPrice($setupPrice)
            ->setMonthlyPrice($monthlyPrice);
        $this->entityTools->persistDto($newDdiDto, $newDdi, true);

        $this->logger->warning('Released DID via UnlinkDdi due to non-payment', [
            'old_ddi_id' => $oldDdiId,
            'new_ddi_id' => $newDdi->getId(),
            'ddi_number' => $ddiNumber,
            'previous_company_id' => $previousCompanyId,
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumber(),
            'invoice_ddi_e164' => $invoice->getDdiE164(),
        ]);

        return [
            'ddi_number' => $ddiNumber,
            'previous_company_id' => (int) $previousCompanyId,
            'new_ddi_id' => (int) $newDdi->getId(),
        ];
    }

    /**
     * Find overdue DIDs for a company
     *
     * For multi-DDI renewal invoices, we need to find which DIDs were due.
     * Since the DDIs were NOT advanced when the WHMCS invoice was created,
     * they still have their original nextRenewalAt dates.
     *
     * We find DIDs where:
     * - Company matches the invoice company
     * - inventoryStatus is 'assigned'
     * - nextRenewalAt <= invoice period end date
     * - monthlyPrice > 0 (excludes BYON)
     *
     * @param InvoiceInterface $invoice
     * @return DdiInterface[]
     */
    private function findOverdueDdisForCompany(InvoiceInterface $invoice): array
    {
        $company = $invoice->getCompany();
        $periodEndDate = $invoice->getOutDate();

        if (!$periodEndDate) {
            $this->logger->warning('Invoice has no outDate, using current date', [
                'invoice_id' => $invoice->getId(),
            ]);
            $periodEndDate = new \DateTime();
        }

        // Query DDIs assigned to this company that are overdue
        // Using DQL via the repository
        $qb = $this->ddiRepository->createQueryBuilder('ddi');

        $qb->where('ddi.company = :company')
            ->andWhere('ddi.inventoryStatus = :assigned')
            ->andWhere('ddi.nextRenewalAt <= :periodEndDate')
            ->andWhere('ddi.monthlyPrice > 0')
            ->setParameter('company', $company)
            ->setParameter('assigned', DdiInterface::INVENTORYSTATUS_ASSIGNED)
            ->setParameter('periodEndDate', $periodEndDate);

        /** @var DdiInterface[] $ddis */
        $ddis = $qb->getQuery()->getResult();

        $this->logger->debug('Found overdue DIDs for company', [
            'company_id' => $company->getId(),
            'period_end_date' => $periodEndDate->format('Y-m-d'),
            'ddi_count' => count($ddis),
        ]);

        return $ddis;
    }
}
