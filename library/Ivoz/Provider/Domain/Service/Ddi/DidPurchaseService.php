<?php

declare(strict_types=1);

/**
 * DID Purchase Service - Handles DID purchase with balance billing
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidPurchaseService.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Model\Invoice\Invoice;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Domain\Service\Company\CompanyBalanceServiceInterface;
use Ivoz\Provider\Domain\Service\Company\SyncBalances;
use Ivoz\Provider\Domain\Service\BalanceMovement\CreateByCompany;
use Psr\Log\LoggerInterface;

/**
 * Service for purchasing DIDs from the marketplace
 *
 * Implements Balance-First billing:
 * - All purchases require sufficient balance
 * - No WHMCS fallback (pay-by-invoice not supported)
 * - Creates invoice marked as paid via balance
 */
class DidPurchaseService implements DidPurchaseServiceInterface
{
    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly LoggerInterface $logger,
        private readonly CompanyBalanceServiceInterface $companyBalanceService,
        private readonly SyncBalances $syncBalanceService,
        private readonly CreateByCompany $createBalanceMovementByCompany,
        private readonly DdiRepository $ddiRepository,
        private readonly FirstPeriodCalculator $firstPeriodCalculator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function preview(CompanyInterface $company, DdiInterface $ddi): array
    {
        $calculation = $this->firstPeriodCalculator->preview(
            $ddi->getSetupPrice(),
            $ddi->getMonthlyPrice()
        );

        $currentBalance = $this->getCompanyBalance($company);
        $balanceAfterPurchase = $currentBalance - $calculation['totalDueNow'];

        $country = $ddi->getCountry();
        $countryDisplay = 'Unknown';
        if ($country !== null) {
            $nameObj = $country->getName();
            if ($nameObj !== null) {
                $countryDisplay = $nameObj->getEn() ?? 'Unknown';
            }
        }

        return [
            'ddi' => $ddi->getDdie164(),
            'ddiId' => $ddi->getId(),
            'country' => $countryDisplay,
            'setupPrice' => $calculation['setupPrice'],
            'monthlyPrice' => $calculation['monthlyPrice'],
            'proratedFirstMonth' => $calculation['proratedFirstMonth'],
            'totalDueNow' => $calculation['totalDueNow'],
            'nextRenewalDate' => $calculation['nextRenewalDate'],
            'nextRenewalAmount' => $calculation['nextRenewalAmount'],
            'currentBalance' => $currentBalance,
            'balanceAfterPurchase' => round($balanceAfterPurchase, 2),
            'canPurchase' => $balanceAfterPurchase >= 0,
            'breakdown' => $calculation['breakdown'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function purchase(CompanyInterface $company, DdiInterface $ddi): PurchaseResult
    {
        $this->logger->info(sprintf(
            'DID purchase initiated: Company #%d purchasing DID #%d (%s)',
            $company->getId(),
            $ddi->getId(),
            $ddi->getDdie164()
        ));

        // Step 1: Verify DID is still available
        if (!$this->isDdiAvailable($ddi)) {
            $this->logger->warning(sprintf(
                'DID purchase failed: DID #%d is not available',
                $ddi->getId()
            ));
            return PurchaseResult::ddiNotAvailable($ddi->getId());
        }

        // Step 2: Calculate costs
        $calculation = $this->firstPeriodCalculator->calculate(
            $ddi->getSetupPrice(),
            $ddi->getMonthlyPrice()
        );
        $totalCost = $calculation['totalDueNow'];

        // Step 3: Check balance
        $currentBalance = $this->getCompanyBalance($company);
        if ($currentBalance < $totalCost) {
            $this->logger->warning(sprintf(
                'DID purchase failed: Insufficient balance. Required: %.2f, Available: %.2f',
                $totalCost,
                $currentBalance
            ));
            return PurchaseResult::insufficientBalance($totalCost, $currentBalance);
        }

        // Step 4: Deduct balance
        $deductResult = $this->deductBalance($company, $totalCost);
        if (!$deductResult['success']) {
            $this->logger->error(sprintf(
                'DID purchase failed: Balance deduction failed - %s',
                $deductResult['error'] ?? 'Unknown error'
            ));
            return PurchaseResult::balanceDeductionFailed($deductResult['error'] ?? 'Unknown error');
        }

        // Get updated balance after deduction
        $newBalance = $this->getCompanyBalance($company);

        // Step 5: Create invoice
        $invoice = $this->createInvoice($company, $ddi, $totalCost, $calculation);

        // Step 6: Update DDI
        $this->assignDdiToCompany($ddi, $company, $calculation['nextRenewalDate']);

        $this->logger->info(sprintf(
            'DID purchase successful: Company #%d purchased DID #%d, charged %.2f, Invoice #%s',
            $company->getId(),
            $ddi->getId(),
            $totalCost,
            $invoice->getNumber()
        ));

        return PurchaseResult::success(
            $ddi,
            $invoice,
            $totalCost,
            $newBalance
        );
    }

    /**
     * @inheritDoc
     */
    public function canAfford(CompanyInterface $company, DdiInterface $ddi): bool
    {
        $calculation = $this->firstPeriodCalculator->calculate(
            $ddi->getSetupPrice(),
            $ddi->getMonthlyPrice()
        );

        $currentBalance = $this->getCompanyBalance($company);

        return $currentBalance >= $calculation['totalDueNow'];
    }

    /**
     * Get company's current balance
     */
    private function getCompanyBalance(CompanyInterface $company): float
    {
        $brandId = (int) $company->getBrand()->getId();
        $companyId = (int) $company->getId();

        $balance = $this->companyBalanceService->getBalance($brandId, $companyId);

        return (float) ($balance ?? 0);
    }

    /**
     * Deduct amount from company balance
     *
     * @return array{success: bool, error?: string}
     */
    private function deductBalance(CompanyInterface $company, float $amount): array
    {
        $response = $this->companyBalanceService->decrementBalance($company, $amount);

        if (!isset($response['success']) || !$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Balance deduction failed',
            ];
        }

        // Sync balances after deduction
        $brandId = (int) $company->getBrand()->getId();
        $companyIds = [(int) $company->getId()];
        $this->syncBalanceService->updateCompanies($brandId, $companyIds);

        // Get updated balance for movement record
        $newBalance = $this->getCompanyBalance($company);

        // Create balance movement record (negative amount for deduction)
        $this->createBalanceMovementByCompany->execute(
            $company,
            -$amount,
            $newBalance
        );

        return ['success' => true];
    }

    /**
     * Create an invoice for the DID purchase
     */
    private function createInvoice(
        CompanyInterface $company,
        DdiInterface $ddi,
        float $totalCost,
        array $calculation
    ): InvoiceInterface {
        $now = new \DateTime();

        // Generate unique invoice number
        $invoiceNumber = sprintf(
            'DID-%d-%s',
            $company->getId(),
            $now->format('YmdHis')
        );

        // Format dates for Invoice entity
        $inDate = $calculation['periodStart'] instanceof \DateTimeInterface
            ? \DateTime::createFromInterface($calculation['periodStart'])
            : new \DateTime();
        $outDate = $calculation['periodEnd'] instanceof \DateTimeInterface
            ? \DateTime::createFromInterface($calculation['periodEnd'])
            : new \DateTime();

        // Create invoice DTO
        $invoiceDto = Invoice::createDto();
        $invoiceDto
            ->setNumber($invoiceNumber)
            ->setInDate($inDate)
            ->setOutDate($outDate)
            ->setTotal($totalCost)
            ->setTaxRate(0.0) // No tax for DID purchases (handled separately if needed)
            ->setTotalWithTax($totalCost)
            ->setStatus(InvoiceInterface::STATUS_CREATED)
            ->setStatusMsg('DID purchase completed')
            ->setBrandId($company->getBrand()->getId())
            ->setCompanyId($company->getId())
            ->setInvoiceType(InvoiceInterface::INVOICE_TYPE_DID_PURCHASE)
            ->setSyncStatus(InvoiceInterface::SYNC_STATUS_NOT_APPLICABLE)
            ->setDdiId($ddi->getId());

        /** @var InvoiceInterface $invoice */
        $invoice = $this->entityTools->persistDto($invoiceDto, null, true);

        // Mark as paid via balance (this sets paidVia and syncStatus)
        $invoice->markAsPaidViaBalance();
        $this->entityTools->persist($invoice, true);

        return $invoice;
    }

    /**
     * Assign DID to company and update inventory status
     */
    private function assignDdiToCompany(
        DdiInterface $ddi,
        CompanyInterface $company,
        \DateTimeInterface $nextRenewalDate
    ): void {
        $ddiDto = $ddi->toDto();
        $ddiDto
            ->setCompanyId($company->getId())
            ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_ASSIGNED)
            ->setAssignedAt(new \DateTime())
            ->setNextRenewalAt($nextRenewalDate)
            ->setReservedForCompanyId(null)
            ->setReservedUntil(null);

        $this->entityTools->persistDto($ddiDto, $ddi, true);
    }

    /**
     * Check if DID is available for purchase
     */
    private function isDdiAvailable(DdiInterface $ddi): bool
    {
        // Refresh from database to get current state
        $currentDdi = $this->ddiRepository->find($ddi->getId());

        if (!$currentDdi) {
            return false;
        }

        // Must be in available status
        if ($currentDdi->getInventoryStatus() !== DdiInterface::INVENTORYSTATUS_AVAILABLE) {
            return false;
        }

        // Must not be assigned to any company
        if ($currentDdi->getCompany() !== null) {
            return false;
        }

        return true;
    }
}
