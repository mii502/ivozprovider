<?php

namespace Ivoz\Provider\Domain\Service\Invoice\Handler;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for did_renewal invoice type
 *
 * When a DID renewal invoice is paid, this handler:
 * 1. Verifies the DDI is linked to the invoice
 * 2. Advances the DDI.nextRenewalAt date by 1 month
 * 3. Ensures the DDI remains active
 *
 * Note: DDI renewal fields (nextRenewalAt) will be added by the
 * ivozprovider-did-renewal module. This handler is designed to work
 * with the current DDI entity and will be updated when those fields are available.
 *
 * Renewal invoices are created by DailyDidRenewalCommand which runs daily
 * to check for DDIs due for renewal.
 *
 * @see integration/modules/ivozprovider-did-renewal for the renewal cron and service
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
     * Current implementation: Logs renewal (nextRenewalAt field not yet available)
     *
     * When ivozprovider-did-renewal adds the nextRenewalAt field, this will:
     * - Get current DDI.nextRenewalAt
     * - Add 1 month to the date
     * - Update DDI.nextRenewalAt with new date
     *
     * @param DdiInterface $ddi
     * @return array Result with renewal date information
     */
    private function advanceRenewalDate(DdiInterface $ddi): array
    {
        // TODO: When ivozprovider-did-renewal adds nextRenewalAt field:
        //
        // $currentRenewalAt = $ddi->getNextRenewalAt();
        // if (!$currentRenewalAt) {
        //     // If no renewal date set, use current date
        //     $currentRenewalAt = new \DateTime();
        // }
        //
        // $newRenewalAt = (clone $currentRenewalAt)->modify('+1 month');
        // $ddi->setNextRenewalAt($newRenewalAt);
        //
        // return [
        //     'previous_renewal_at' => $currentRenewalAt->format('c'),
        //     'next_renewal_at' => $newRenewalAt->format('c'),
        // ];

        // For now, just log that renewal was processed
        // The DDI remains active as long as invoices are paid
        $this->logger->debug('DID renewal handler: Renewal date advancement pending (field not yet available)', [
            'ddi_id' => $ddi->getId(),
            'ddi_number' => $ddi->getDdie164(),
        ]);

        return [
            'note' => 'DDI renewal recorded - nextRenewalAt field will be updated when available',
        ];
    }
}
