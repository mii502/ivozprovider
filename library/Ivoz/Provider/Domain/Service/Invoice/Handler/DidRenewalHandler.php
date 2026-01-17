<?php

namespace Ivoz\Provider\Domain\Service\Invoice\Handler;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for did_renewal invoice type
 *
 * When a DID renewal invoice is paid (via WHMCS webhook), this handler:
 * 1. Verifies the DDI is linked to the invoice
 * 2. Validates the DDI belongs to the invoice company
 * 3. Advances the DDI.nextRenewalAt date by 1 month
 *
 * Renewal invoices are created by DailyDidRenewalCommand which runs daily
 * to check for DDIs due for renewal. DIDs with sufficient company balance
 * are renewed silently; those without balance get WHMCS invoices.
 *
 * @see DailyDidRenewalCommand for the cron job that creates renewal invoices
 * @see DidRenewalService for the balance-first renewal logic
 */
class DidRenewalHandler implements InvoicePaidHandlerInterface
{
    public function __construct(
        private EntityTools $entityTools,
        private LoggerInterface $logger
    ) {
    }

    public function supports(string $invoiceType): bool
    {
        return $invoiceType === InvoiceInterface::INVOICE_TYPE_DID_RENEWAL;
    }

    public function handle(InvoiceInterface $invoice, array $webhookData): array
    {
        $ddi = $invoice->getDdi();
        $company = $invoice->getCompany();

        // Validate DDI is linked to invoice
        if (!$ddi) {
            $this->logger->error('DID renewal handler: Invoice has no DDI linked', [
                'invoice_id' => $invoice->getId(),
                'company_id' => $company->getId(),
            ]);

            throw new \DomainException(
                'DID renewal invoice has no DDI linked - cannot process renewal'
            );
        }

        // Verify DDI belongs to the invoice company
        $ddiCompany = $ddi->getCompany();
        if (!$ddiCompany || $ddiCompany->getId() !== $company->getId()) {
            $this->logger->error('DID renewal handler: DDI company mismatch', [
                'invoice_id' => $invoice->getId(),
                'ddi_id' => $ddi->getId(),
                'expected_company_id' => $company->getId(),
                'actual_company_id' => $ddiCompany?->getId(),
            ]);

            throw new \DomainException(sprintf(
                'DDI %s does not belong to company %d',
                $ddi->getDdie164(),
                $company->getId()
            ));
        }

        $this->logger->info('DID renewal handler: Processing DDI renewal', [
            'invoice_id' => $invoice->getId(),
            'ddi_id' => $ddi->getId(),
            'ddi_number' => $ddi->getDdie164(),
            'company_id' => $company->getId(),
            'company_name' => $company->getName(),
        ]);

        try {
            // Advance the renewal date
            $result = $this->advanceRenewalDate($ddi);

            // Persist changes
            $this->entityTools->persist($ddi, true);

            $this->logger->info('DID renewal handler: DDI renewal processed successfully', [
                'invoice_id' => $invoice->getId(),
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
                'next_renewal_at' => $result['next_renewal_at'] ?? null,
            ]);

            return [
                'action' => 'did_renewal_completed',
                'message' => 'DDI renewal processed successfully',
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
                'company_id' => $company->getId(),
                ...$result,
            ];
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('DID renewal handler: Failed to process DDI renewal', [
                'invoice_id' => $invoice->getId(),
                'ddi_id' => $ddi->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \DomainException(
                sprintf('Failed to process DDI renewal: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Advance the DDI renewal date by 1 month
     *
     * Called when a DID renewal invoice is paid via WHMCS webhook.
     * Gets the current nextRenewalAt date and advances it by 1 month.
     *
     * @param DdiInterface $ddi
     * @return array Result with previous and new renewal dates
     */
    private function advanceRenewalDate(DdiInterface $ddi): array
    {
        $currentRenewalAt = $ddi->getNextRenewalAt();
        if (!$currentRenewalAt) {
            // If no renewal date set, use current date as base
            $currentRenewalAt = new \DateTime();
        }

        // Convert to DateTime if needed (Rule 10: DTO setters expect DateTime)
        if ($currentRenewalAt instanceof \DateTimeImmutable) {
            $currentRenewalAt = \DateTime::createFromInterface($currentRenewalAt);
        }

        $newRenewalAt = (clone $currentRenewalAt)->modify('+1 month');

        // Use DTO for consistency with IvozProvider patterns
        $ddiDto = $ddi->toDto();
        $ddiDto->setNextRenewalAt($newRenewalAt);
        $this->entityTools->persistDto($ddiDto, $ddi, false); // Don't flush - caller will persist

        $this->logger->debug('DID renewal handler: nextRenewalAt advanced', [
            'ddi_id' => $ddi->getId(),
            'ddi_number' => $ddi->getDdie164(),
            'previous_renewal_at' => $currentRenewalAt->format('Y-m-d'),
            'next_renewal_at' => $newRenewalAt->format('Y-m-d'),
        ]);

        return [
            'previous_renewal_at' => $currentRenewalAt->format('c'),
            'next_renewal_at' => $newRenewalAt->format('c'),
        ];
    }
}
