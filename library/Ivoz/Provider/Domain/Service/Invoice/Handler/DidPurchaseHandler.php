<?php

namespace Ivoz\Provider\Domain\Service\Invoice\Handler;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for did_purchase invoice type
 *
 * When a DID purchase invoice is paid, this handler:
 * 1. Verifies the DDI is linked to the invoice
 * 2. Provisions the DDI (marks as assigned, enabled, sets assignedAt)
 * 3. Associates the DDI with the company if not already done
 *
 * Note: DDI inventory fields (inventoryStatus, assignedAt) will be added by
 * the ivozprovider-did-marketplace module. This handler is designed to work
 * with the current DDI entity and will be updated when those fields are available.
 *
 * @see integration/modules/ivozprovider-did-marketplace for DID marketplace feature
 * @see integration/modules/ivozprovider-did-purchase for the service that creates these invoices
 */
class DidPurchaseHandler implements InvoicePaidHandlerInterface
{
    public function __construct(
        private EntityTools $entityTools,
        private LoggerInterface $logger
    ) {
    }

    public function supports(string $invoiceType): bool
    {
        return $invoiceType === InvoiceInterface::INVOICE_TYPE_DID_PURCHASE;
    }

    public function handle(InvoiceInterface $invoice, array $webhookData): array
    {
        $ddi = $invoice->getDdi();
        $company = $invoice->getCompany();

        // Validate DDI is linked to invoice
        if (!$ddi) {
            $this->logger->error('DID purchase handler: Invoice has no DDI linked', [
                'invoice_id' => $invoice->getId(),
                'company_id' => $company->getId(),
            ]);

            throw new \DomainException(
                'DID purchase invoice has no DDI linked - cannot provision'
            );
        }

        $this->logger->info('DID purchase handler: Provisioning DDI', [
            'invoice_id' => $invoice->getId(),
            'ddi_id' => $ddi->getId(),
            'ddi_number' => $ddi->getDdie164(),
            'company_id' => $company->getId(),
            'company_name' => $company->getName(),
        ]);

        try {
            // Provision the DDI
            $this->provisionDdi($ddi, $company);

            // Persist changes
            $this->entityTools->persist($ddi, true);

            $this->logger->info('DID purchase handler: DDI provisioned successfully', [
                'invoice_id' => $invoice->getId(),
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
            ]);

            return [
                'action' => 'did_purchase_completed',
                'message' => 'DDI provisioned successfully',
                'ddi_id' => $ddi->getId(),
                'ddi_number' => $ddi->getDdie164(),
                'company_id' => $company->getId(),
            ];
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('DID purchase handler: Failed to provision DDI', [
                'invoice_id' => $invoice->getId(),
                'ddi_id' => $ddi->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \DomainException(
                sprintf('Failed to provision DDI: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Provision the DDI after purchase payment
     *
     * Current implementation: Verifies DDI is assigned to company
     *
     * When ivozprovider-did-marketplace adds inventory fields, this will:
     * - Set DDI.inventoryStatus = 'assigned'
     * - Set DDI.enabled = true
     * - Set DDI.assignedAt = now
     *
     * @param DdiInterface $ddi
     * @param \Ivoz\Provider\Domain\Model\Company\CompanyInterface $company
     */
    private function provisionDdi(DdiInterface $ddi, $company): void
    {
        // Verify DDI is assigned to the correct company
        $ddiCompany = $ddi->getCompany();

        if ($ddiCompany && $ddiCompany->getId() !== $company->getId()) {
            throw new \DomainException(sprintf(
                'DDI %s is assigned to different company (expected: %d, actual: %d)',
                $ddi->getDdie164(),
                $company->getId(),
                $ddiCompany->getId()
            ));
        }

        // If DDI not yet assigned to company, assign it now
        // Note: In normal flow, DDI should already be assigned when invoice was created
        if (!$ddiCompany) {
            $this->logger->info('DID purchase handler: Assigning DDI to company', [
                'ddi_id' => $ddi->getId(),
                'company_id' => $company->getId(),
            ]);
            $ddi->setCompany($company);
        }

        // TODO: When ivozprovider-did-marketplace adds these fields, uncomment:
        // $ddi->setInventoryStatus('assigned');
        // $ddi->setAssignedAt(new \DateTime());
        //
        // Note: DDI should be enabled by default in IvozProvider, but we could
        // add an explicit enable step here if needed:
        // $ddi->setEnabled(true);

        $this->logger->debug('DID purchase handler: DDI provisioning complete', [
            'ddi_id' => $ddi->getId(),
            'ddi_number' => $ddi->getDdie164(),
            'company_id' => $company->getId(),
        ]);
    }
}
