<?php

namespace Ivoz\Provider\Domain\Service\Invoice\Handler;

use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Domain\Service\Company\IncrementBalance;
use Psr\Log\LoggerInterface;

/**
 * Handler for balance_topup invoice type
 *
 * When a balance top-up invoice is paid, this handler:
 * 1. Increments the Company.balance by the invoice amount
 * 2. Creates a BalanceMovement record for audit trail
 * 3. Syncs balance with CGRateS (via IncrementBalance service)
 *
 * @see integration/modules/ivozprovider-balance-topup for the service that creates these invoices
 */
class BalanceTopUpHandler implements InvoicePaidHandlerInterface
{
    public function __construct(
        private IncrementBalance $incrementBalance,
        private LoggerInterface $logger
    ) {
    }

    public function supports(string $invoiceType): bool
    {
        return $invoiceType === InvoiceInterface::INVOICE_TYPE_BALANCE_TOPUP;
    }

    public function handle(InvoiceInterface $invoice, array $webhookData): array
    {
        $company = $invoice->getCompany();
        $amount = $invoice->getTotalWithTax();

        if ($amount <= 0) {
            $this->logger->warning('Balance top-up handler: Invoice has zero or negative amount', [
                'invoice_id' => $invoice->getId(),
                'amount' => $amount,
            ]);

            return [
                'action' => 'balance_topup_skipped',
                'message' => 'Invoice amount is zero or negative',
                'amount' => $amount,
            ];
        }

        $companyId = $company->getId();
        $previousBalance = $company->getBalance() ?? 0;

        $this->logger->info('Balance top-up handler: Incrementing company balance', [
            'invoice_id' => $invoice->getId(),
            'company_id' => $companyId,
            'company_name' => $company->getName(),
            'amount' => $amount,
            'previous_balance' => $previousBalance,
        ]);

        try {
            // Use existing IncrementBalance service which:
            // 1. Calls CGRateS to increment balance
            // 2. Syncs balances back to IvozProvider
            // 3. Creates BalanceMovement record for audit trail
            $success = $this->incrementBalance->execute($companyId, $amount);

            if (!$success) {
                $error = $this->incrementBalance->getLastError();
                $this->logger->error('Balance top-up handler: IncrementBalance failed', [
                    'invoice_id' => $invoice->getId(),
                    'company_id' => $companyId,
                    'error' => $error,
                ]);

                throw new \DomainException(
                    sprintf('Failed to increment balance: %s', $error)
                );
            }

            $newBalance = $company->getBalance() ?? 0;

            $this->logger->info('Balance top-up handler: Balance incremented successfully', [
                'invoice_id' => $invoice->getId(),
                'company_id' => $companyId,
                'amount' => $amount,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
            ]);

            return [
                'action' => 'balance_topup_completed',
                'message' => 'Balance incremented successfully',
                'company_id' => $companyId,
                'amount' => $amount,
                'previous_balance' => $previousBalance,
                'new_balance' => $newBalance,
            ];
        } catch (\DomainException $e) {
            // Re-throw domain exceptions
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Balance top-up handler: Unexpected error', [
                'invoice_id' => $invoice->getId(),
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \DomainException(
                sprintf('Balance top-up failed: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
