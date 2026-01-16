<?php

namespace Ivoz\Provider\Domain\Service\Invoice\Handler;

use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for standard invoice type (postpaid monthly invoices)
 *
 * When a standard (postpaid monthly) invoice is paid:
 * 1. The invoice is already marked as paid by the webhook controller
 * 2. No additional provisioning is needed (DIDs already active for postpaid)
 * 3. This handler logs the payment for auditing
 *
 * Standard invoices are created by InvoiceScheduler for postpaid companies.
 * They are synced to WHMCS for payment collection, and when paid, this
 * handler simply confirms the payment was recorded.
 *
 * This handler is intentionally minimal - postpaid customers have active
 * services regardless of payment status. The payment notification is logged
 * for business reporting and reconciliation purposes.
 *
 * @see InvoiceScheduler for how standard invoices are generated
 */
class StandardInvoiceHandler implements InvoicePaidHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function supports(string $invoiceType): bool
    {
        return $invoiceType === InvoiceInterface::INVOICE_TYPE_STANDARD;
    }

    public function handle(InvoiceInterface $invoice, array $webhookData): array
    {
        $company = $invoice->getCompany();
        $amount = $invoice->getTotalWithTax();

        $this->logger->info('Standard invoice handler: Payment recorded', [
            'invoice_id' => $invoice->getId(),
            'invoice_number' => $invoice->getNumber(),
            'company_id' => $company->getId(),
            'company_name' => $company->getName(),
            'amount' => $amount,
            'in_date' => $invoice->getInDate()?->format('Y-m-d'),
            'out_date' => $invoice->getOutDate()?->format('Y-m-d'),
            'whmcs_invoice_id' => $webhookData['whmcs_invoice_id'] ?? null,
        ]);

        // For postpaid invoices, no provisioning is needed
        // The invoice is already marked as paid by the webhook controller
        // Services remain active regardless (postpaid model)

        return [
            'action' => 'standard_invoice_paid',
            'message' => 'Standard invoice payment recorded',
            'invoice_number' => $invoice->getNumber(),
            'company_id' => $company->getId(),
            'amount' => $amount,
            'period' => sprintf(
                '%s to %s',
                $invoice->getInDate()?->format('Y-m-d') ?? 'unknown',
                $invoice->getOutDate()?->format('Y-m-d') ?? 'unknown'
            ),
        ];
    }
}
