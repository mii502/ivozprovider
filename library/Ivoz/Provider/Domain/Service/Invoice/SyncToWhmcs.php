<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\Invoice;

use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Invoice lifecycle service that triggers WHMCS sync on commit
 *
 * This service listens to the on_commit event for Invoice entities and
 * triggers synchronization to WHMCS for invoices that meet the criteria:
 * - Company is linked to WHMCS (has whmcsClientId)
 * - Invoice type matches billing method (prepaid vs postpaid)
 * - Invoice hasn't already been synced
 *
 * Sync logic by billing method:
 * - Prepaid: sync did_purchase, did_renewal, balance_topup invoices
 * - Postpaid: sync standard invoices (monthly from InvoiceScheduler)
 */
class SyncToWhmcs implements InvoiceLifecycleEventHandlerInterface
{
    public const ON_COMMIT_PRIORITY = 200;

    public function __construct(
        private SyncInvoiceToWhmcs $syncInvoiceToWhmcs,
        private LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::EVENT_ON_COMMIT => self::ON_COMMIT_PRIORITY
        ];
    }

    /**
     * Execute WHMCS sync for eligible invoices
     *
     * Only triggers sync for newly created invoices or invoices
     * that haven't been synced yet.
     */
    public function execute(InvoiceInterface $invoice): void
    {
        // Skip if invoice is not new and sync status hasn't changed
        // This prevents re-syncing on every invoice update
        if (!$this->shouldTriggerSync($invoice)) {
            return;
        }

        // Check if invoice should sync to WHMCS
        if (!$invoice->shouldSyncToWhmcs()) {
            $this->logger->debug(sprintf(
                'Invoice #%d: WHMCS sync not applicable (type: %s)',
                $invoice->getId(),
                $invoice->getInvoiceType()
            ));
            return;
        }

        // Check sync status - only sync pending or not yet attempted
        $syncStatus = $invoice->getSyncStatus();
        if ($syncStatus === InvoiceInterface::SYNC_STATUS_SYNCED) {
            $this->logger->debug(sprintf(
                'Invoice #%d: Already synced to WHMCS as #%d',
                $invoice->getId(),
                $invoice->getWhmcsInvoiceId()
            ));
            return;
        }

        if ($syncStatus === InvoiceInterface::SYNC_STATUS_FAILED) {
            $this->logger->debug(sprintf(
                'Invoice #%d: Previous sync failed, skipping automatic retry (manual retry available)',
                $invoice->getId()
            ));
            return;
        }

        $this->logger->info(sprintf(
            'Invoice #%d: Triggering WHMCS sync (type: %s)',
            $invoice->getId(),
            $invoice->getInvoiceType()
        ));

        // Execute the sync
        $this->syncInvoiceToWhmcs->execute($invoice);
    }

    /**
     * Determine if sync should be triggered for this invoice
     *
     * Triggers sync when:
     * - Invoice is new (just created)
     * - Invoice status changed to 'created' (for scheduled invoices)
     * - Sync status is still pending/not_applicable
     */
    private function shouldTriggerSync(InvoiceInterface $invoice): bool
    {
        // Always trigger for new invoices
        if ($invoice->isNew()) {
            return true;
        }

        // Trigger when invoice status changes to 'created' (scheduled invoices)
        if ($invoice->hasChanged('status') && $invoice->getStatus() === InvoiceInterface::STATUS_CREATED) {
            return true;
        }

        // Trigger when sync status is pending but not yet processed
        $syncStatus = $invoice->getSyncStatus();
        if ($syncStatus === InvoiceInterface::SYNC_STATUS_PENDING) {
            return true;
        }

        // Don't trigger for other updates (prevent infinite loops)
        return false;
    }
}
