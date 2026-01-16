<?php

namespace Ivoz\Provider\Domain\Service\Invoice\Handler;

use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;

/**
 * Interface for handling WHMCS invoice paid webhooks
 *
 * Each invoice type has a specific handler that performs the required action
 * after payment is confirmed:
 * - balance_topup: Add credits to Company.balance
 * - did_purchase: Provision the DID (assign to company, enable)
 * - did_renewal: Advance DDI.nextRenewalAt date
 * - standard: Mark invoice paid (postpaid monthly - no provisioning)
 *
 * Handlers are called by WhmcsPaidWebhookController after signature verification
 * and invoice lookup. The invoice is already marked as paid before the handler
 * is invoked.
 *
 * @see integration/modules/ivozprovider-invoice-infrastructure/IMPLEMENTATION-PLAN.md Phase E
 */
interface InvoicePaidHandlerInterface
{
    /**
     * Check if this handler supports the given invoice type
     *
     * @param string $invoiceType One of InvoiceInterface::INVOICE_TYPE_* constants
     * @return bool
     */
    public function supports(string $invoiceType): bool;

    /**
     * Handle the paid invoice
     *
     * Called after the invoice has been marked as paid. The handler should
     * perform any provisioning or balance updates required for the invoice type.
     *
     * @param InvoiceInterface $invoice The invoice that was paid
     * @param array $webhookData Raw webhook payload data from WHMCS
     * @return array Handler result for response (will be merged into webhook response)
     * @throws \DomainException If the handler cannot process the invoice
     */
    public function handle(InvoiceInterface $invoice, array $webhookData): array;
}
