<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\BalanceTopUp;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Invoice\Invoice;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceDto;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Domain\Model\Invoice\Pdf;
use Psr\Log\LoggerInterface;

/**
 * Service for creating balance top-up invoices
 *
 * This service:
 * 1. Validates the top-up amount (€5.00 to €1,000.00)
 * 2. Validates company is prepaid or pseudoprepaid
 * 3. Creates an Invoice with type='balance_topup'
 * 4. The SyncToWhmcs lifecycle handler will automatically sync to WHMCS
 *
 * @see integration/modules/ivozprovider-invoice-infrastructure for Invoice entity and sync
 */
class BalanceTopUpService
{
    private const MIN_AMOUNT = 5.00;
    private const MAX_AMOUNT = 1000.00;

    public function __construct(
        private EntityTools $entityTools,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get minimum top-up amount
     */
    public function getMinAmount(): float
    {
        return self::MIN_AMOUNT;
    }

    /**
     * Get maximum top-up amount
     */
    public function getMaxAmount(): float
    {
        return self::MAX_AMOUNT;
    }

    /**
     * Check if company can perform top-ups
     */
    public function canTopUp(CompanyInterface $company): bool
    {
        $billingMethod = $company->getBillingMethod();

        return in_array($billingMethod, ['prepaid', 'pseudoprepaid'], true);
    }

    /**
     * Validate top-up amount
     *
     * @throws \DomainException if amount is invalid
     */
    public function validateAmount(float $amount): void
    {
        if ($amount < self::MIN_AMOUNT || $amount > self::MAX_AMOUNT) {
            throw new \DomainException(sprintf(
                'Amount must be between €%.2f and €%.2f',
                self::MIN_AMOUNT,
                self::MAX_AMOUNT
            ));
        }

        // Check maximum 2 decimal places
        // Using bccomp for precise decimal comparison
        $rounded = round($amount, 2);
        if (abs($amount - $rounded) > 0.0001) {
            throw new \DomainException('Amount cannot have more than 2 decimal places');
        }
    }

    /**
     * Validate company can perform top-up
     *
     * @throws \DomainException if company cannot top up
     */
    public function validateCompany(CompanyInterface $company): void
    {
        if (!$this->canTopUp($company)) {
            throw new \DomainException(
                'Top-up is only available for prepaid accounts'
            );
        }

        // Check if company has WHMCS client ID for payment processing
        if (!method_exists($company, 'getWhmcsClientId') || !$company->getWhmcsClientId()) {
            throw new \DomainException(
                'Your account is not linked to the billing system. Please contact support.'
            );
        }
    }

    /**
     * Create a balance top-up invoice
     *
     * @return InvoiceInterface The created invoice (will be automatically synced to WHMCS)
     * @throws \DomainException if validation fails
     */
    public function createTopUpInvoice(CompanyInterface $company, float $amount): InvoiceInterface
    {
        // Validate
        $this->validateAmount($amount);
        $this->validateCompany($company);

        $this->logger->info('Creating balance top-up invoice', [
            'company_id' => $company->getId(),
            'company_name' => $company->getName(),
            'amount' => $amount,
        ]);

        // Create invoice DTO
        $invoiceDto = Invoice::createDto();
        $invoiceDto
            ->setBrand($company->getBrand()->toDto())
            ->setCompany($company->toDto())
            ->setInDate(new \DateTime())
            ->setOutDate(new \DateTime())
            ->setTaxRate(0.0)  // No tax on credit top-ups
            ->setTotal($amount)
            ->setTotalWithTax($amount)
            ->setInvoiceType(InvoiceInterface::INVOICE_TYPE_BALANCE_TOPUP)
            ->setSyncStatus(InvoiceInterface::SYNC_STATUS_PENDING)
            ->setNumber($this->generateInvoiceNumber($company));

        // Persist the invoice
        // The SyncToWhmcs lifecycle handler will automatically sync it to WHMCS
        /** @var InvoiceInterface $invoice */
        $invoice = $this->entityTools->persistDto($invoiceDto, null, true);

        $this->logger->info('Balance top-up invoice created', [
            'invoice_id' => $invoice->getId(),
            'company_id' => $company->getId(),
            'amount' => $amount,
        ]);

        return $invoice;
    }

    /**
     * Generate a unique invoice number for top-up invoices
     */
    private function generateInvoiceNumber(CompanyInterface $company): string
    {
        // Format: TOPUP-{company_id}-{timestamp}
        return sprintf(
            'TOPUP-%d-%d',
            $company->getId(),
            time()
        );
    }

    /**
     * Get WHMCS payment URL for an invoice
     *
     * @param InvoiceInterface $invoice
     * @return string|null Payment URL or null if not synced to WHMCS
     */
    public function getPaymentUrl(InvoiceInterface $invoice): ?string
    {
        $whmcsInvoiceId = $invoice->getWhmcsInvoiceId();

        if (!$whmcsInvoiceId) {
            return null;
        }

        // WHMCS payment URL format
        return sprintf(
            'https://voip.ing/viewinvoice.php?id=%d',
            $whmcsInvoiceId
        );
    }
}
