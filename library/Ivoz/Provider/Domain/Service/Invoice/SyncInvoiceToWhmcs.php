<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\Invoice;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Infrastructure\Api\Whmcs\WhmcsApiException;
use Ivoz\Provider\Infrastructure\Api\Whmcs\WhmcsApiService;
use Psr\Log\LoggerInterface;

/**
 * Service to synchronize IvozProvider invoices to WHMCS
 *
 * Handles the creation of corresponding invoices in WHMCS with retry logic
 * and exponential backoff for failed sync attempts.
 *
 * Retry schedule: 30s, 60s, 5m, 15m, 1h (5 attempts total)
 */
class SyncInvoiceToWhmcs
{
    /**
     * Retry backoff delays in seconds: 30s, 60s, 5m, 15m, 1h
     */
    public const BACKOFF_DELAYS = [30, 60, 300, 900, 3600];

    /**
     * Maximum number of sync attempts
     */
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private WhmcsApiService $whmcsApiService,
        private EntityTools $entityTools,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Synchronize an invoice to WHMCS
     *
     * Creates the invoice in WHMCS and updates the local invoice record
     * with the WHMCS invoice ID and sync status.
     *
     * @param InvoiceInterface $invoice The invoice to sync
     * @param bool $retryOnFailure Whether to implement retry logic for failures
     *
     * @return bool True if sync succeeded, false otherwise
     */
    public function execute(InvoiceInterface $invoice, bool $retryOnFailure = true): bool
    {
        // Skip if already synced
        if ($invoice->getSyncStatus() === InvoiceInterface::SYNC_STATUS_SYNCED) {
            $this->logger->info(sprintf(
                'Invoice #%d already synced to WHMCS as #%d',
                $invoice->getId(),
                $invoice->getWhmcsInvoiceId()
            ));
            return true;
        }

        // Skip if marked as not applicable
        if ($invoice->getSyncStatus() === InvoiceInterface::SYNC_STATUS_NOT_APPLICABLE) {
            $this->logger->debug(sprintf(
                'Invoice #%d sync not applicable',
                $invoice->getId()
            ));
            return true;
        }

        // Check if company is WHMCS-linked
        $company = $invoice->getCompany();
        if (!method_exists($company, 'getWhmcsClientId') || !$company->getWhmcsClientId()) {
            $invoice->markAsNotApplicable();
            $this->persistInvoice($invoice);
            $this->logger->debug(sprintf(
                'Invoice #%d: Company not linked to WHMCS',
                $invoice->getId()
            ));
            return true;
        }

        // Check if this invoice type should sync
        if (!$invoice->shouldSyncToWhmcs()) {
            $invoice->markAsNotApplicable();
            $this->persistInvoice($invoice);
            $this->logger->debug(sprintf(
                'Invoice #%d: Type %s not syncable for company billing method',
                $invoice->getId(),
                $invoice->getInvoiceType()
            ));
            return true;
        }

        // Mark as pending if not already
        if ($invoice->getSyncStatus() !== InvoiceInterface::SYNC_STATUS_PENDING) {
            $invoice->markAsPending();
        }

        try {
            $whmcsInvoiceId = $this->createWhmcsInvoice($invoice);

            // Mark as synced
            $invoice->markAsSynced($whmcsInvoiceId);
            $this->persistInvoice($invoice);

            $this->logger->info(sprintf(
                'Invoice #%d synced to WHMCS as #%d',
                $invoice->getId(),
                $whmcsInvoiceId
            ));

            return true;
        } catch (WhmcsApiException $e) {
            return $this->handleSyncFailure($invoice, $e, $retryOnFailure);
        }
    }

    /**
     * Create the WHMCS invoice via API
     *
     * @param InvoiceInterface $invoice
     *
     * @return int WHMCS invoice ID
     *
     * @throws WhmcsApiException
     */
    private function createWhmcsInvoice(InvoiceInterface $invoice): int
    {
        $company = $invoice->getCompany();
        $whmcsClientId = $company->getWhmcsClientId();

        $description = $this->buildInvoiceDescription($invoice);
        $amount = $invoice->getTotalWithTax() ?? $invoice->getTotal() ?? 0.00;
        $dueDate = (new \DateTime())->modify('+30 days');
        $notes = sprintf('IvozProvider:%d', $invoice->getId());

        return $this->whmcsApiService->createInvoice(
            $whmcsClientId,
            $description,
            $amount,
            $dueDate,
            $notes,
            true
        );
    }

    /**
     * Build the invoice description based on invoice type
     *
     * @param InvoiceInterface $invoice
     *
     * @return string
     */
    private function buildInvoiceDescription(InvoiceInterface $invoice): string
    {
        $type = $invoice->getInvoiceType();
        $ddi = $invoice->getDdi();

        return match ($type) {
            InvoiceInterface::INVOICE_TYPE_DID_PURCHASE => $ddi
                ? sprintf('VoIP DID Purchase - %s', $ddi->getDdi())
                : 'VoIP DID Purchase',
            InvoiceInterface::INVOICE_TYPE_DID_RENEWAL => $ddi
                ? sprintf('VoIP DID Monthly Rental - %s', $ddi->getDdi())
                : 'VoIP DID Monthly Rental',
            InvoiceInterface::INVOICE_TYPE_BALANCE_TOPUP => sprintf(
                'VoIP Balance Top-Up - %s',
                $invoice->getCompany()->getName()
            ),
            InvoiceInterface::INVOICE_TYPE_STANDARD => sprintf(
                'VoIP Monthly Invoice #%s',
                $invoice->getNumber() ?? $invoice->getId()
            ),
            default => sprintf('VoIP Invoice #%d', $invoice->getId()),
        };
    }

    /**
     * Handle sync failure with retry logic
     *
     * @param InvoiceInterface $invoice
     * @param WhmcsApiException $exception
     * @param bool $retryOnFailure
     *
     * @return bool
     */
    private function handleSyncFailure(
        InvoiceInterface $invoice,
        WhmcsApiException $exception,
        bool $retryOnFailure
    ): bool {
        $invoice->incrementSyncAttempts();
        $invoice->setSyncError($exception->getMessage());

        $attempts = $invoice->getSyncAttempts();

        $this->logger->warning(sprintf(
            'Invoice #%d sync attempt %d/%d failed: %s',
            $invoice->getId(),
            $attempts,
            self::MAX_ATTEMPTS,
            $exception->getMessage()
        ));

        // Check if we should retry
        if ($retryOnFailure && $exception->isRetryable() && $attempts < self::MAX_ATTEMPTS) {
            // Keep status as pending for retry
            $this->persistInvoice($invoice);

            // Get delay for next retry
            $delayIndex = min($attempts - 1, count(self::BACKOFF_DELAYS) - 1);
            $delay = self::BACKOFF_DELAYS[$delayIndex];

            $this->logger->info(sprintf(
                'Invoice #%d will retry in %d seconds',
                $invoice->getId(),
                $delay
            ));

            // Sleep for the backoff delay (synchronous retry)
            sleep($delay);

            // Retry
            return $this->execute($invoice, true);
        }

        // Mark as permanently failed
        $invoice->markSyncFailed($exception->getMessage());
        $this->persistInvoice($invoice);

        $this->logger->error(sprintf(
            'Invoice #%d sync failed permanently after %d attempts: %s',
            $invoice->getId(),
            $attempts,
            $exception->getMessage()
        ));

        return false;
    }

    /**
     * Persist invoice changes
     *
     * @param InvoiceInterface $invoice
     */
    private function persistInvoice(InvoiceInterface $invoice): void
    {
        $this->entityTools->persist($invoice, true);
    }

    /**
     * Get the current backoff delay for an invoice
     *
     * @param InvoiceInterface $invoice
     *
     * @return int Delay in seconds
     */
    public function getBackoffDelay(InvoiceInterface $invoice): int
    {
        $attempts = $invoice->getSyncAttempts();
        if ($attempts === 0) {
            return 0;
        }

        $delayIndex = min($attempts - 1, count(self::BACKOFF_DELAYS) - 1);
        return self::BACKOFF_DELAYS[$delayIndex];
    }

    /**
     * Check if an invoice can be retried
     *
     * @param InvoiceInterface $invoice
     *
     * @return bool
     */
    public function canRetry(InvoiceInterface $invoice): bool
    {
        return $invoice->getSyncStatus() === InvoiceInterface::SYNC_STATUS_PENDING
            && $invoice->getSyncAttempts() < self::MAX_ATTEMPTS;
    }
}
