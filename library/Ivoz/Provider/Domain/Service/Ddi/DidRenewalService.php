<?php

declare(strict_types=1);

/**
 * DID Renewal Service - Handles monthly DID renewal with balance billing
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidRenewalService.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-renewal
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
 * Service for processing DID renewals
 *
 * Implements Balance-First renewal strategy:
 * 1. If company has sufficient balance → silent renewal with balance deduction
 * 2. If insufficient balance → create unpaid invoice for WHMCS sync
 *
 * Renewal modes:
 * - per_did: Each DID advances independently by 1 month
 * - consolidated: All DIDs advance to anchor + 1 month
 */
class DidRenewalService implements DidRenewalServiceInterface
{
    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly LoggerInterface $logger,
        private readonly CompanyBalanceServiceInterface $companyBalanceService,
        private readonly SyncBalances $syncBalanceService,
        private readonly CreateByCompany $createBalanceMovementByCompany,
        private readonly DdiRepository $ddiRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getDdisForRenewalGroupedByCompany(\DateTime $date): array
    {
        return $this->ddiRepository->findDdisForRenewalGroupedByCompany($date);
    }

    /**
     * @inheritDoc
     */
    public function renewFromBalance(CompanyInterface $company, array $ddis): InvoiceInterface
    {
        $totalCost = $this->calculateRenewalCost($ddis);
        $ddiCount = count($ddis);

        // Get renewal mode from brand
        $brand = $company->getBrand();
        $renewalMode = $brand->getDidRenewalMode();

        $this->logger->info(sprintf(
            'DID renewal from balance: Company #%d (%s), %d DDIs, total: %.2f [mode=%s]',
            $company->getId(),
            $company->getName(),
            $ddiCount,
            $totalCost,
            $renewalMode
        ));

        // Step 1: Deduct balance
        $deductResult = $this->deductBalance($company, $totalCost);
        if (!$deductResult['success']) {
            $this->logger->error(sprintf(
                'DID renewal failed: Balance deduction failed for Company #%d - %s',
                $company->getId(),
                $deductResult['error'] ?? 'Unknown error'
            ));
            throw new \DomainException(
                sprintf('Balance deduction failed: %s', $deductResult['error'] ?? 'Unknown error')
            );
        }

        // Step 2: Create paid invoice with mode info
        $invoice = $this->createInvoice($company, $ddis, $totalCost, true, $renewalMode);

        // Step 3: Advance renewal dates for all DDIs
        $this->advanceRenewalDates($company, $ddis, $renewalMode);

        $this->logger->info(sprintf(
            'DID renewal successful: Company #%d, Invoice #%s, %d DDIs renewed [mode=%s]',
            $company->getId(),
            $invoice->getNumber(),
            $ddiCount,
            $renewalMode
        ));

        return $invoice;
    }

    /**
     * @inheritDoc
     */
    public function createWhmcsRenewalInvoice(CompanyInterface $company, array $ddis): InvoiceInterface
    {
        $totalCost = $this->calculateRenewalCost($ddis);
        $ddiCount = count($ddis);

        // Get renewal mode from brand
        $brand = $company->getBrand();
        $renewalMode = $brand->getDidRenewalMode();

        $this->logger->info(sprintf(
            'DID renewal via WHMCS: Company #%d (%s), %d DDIs, total: %.2f [mode=%s]',
            $company->getId(),
            $company->getName(),
            $ddiCount,
            $totalCost,
            $renewalMode
        ));

        // Create unpaid invoice - InvoiceWhmcsSyncObserver will sync to WHMCS
        $invoice = $this->createInvoice($company, $ddis, $totalCost, false, $renewalMode);

        $this->logger->info(sprintf(
            'DID renewal invoice created: Company #%d, Invoice #%s (pending WHMCS sync) [mode=%s]',
            $company->getId(),
            $invoice->getNumber(),
            $renewalMode
        ));

        return $invoice;
    }

    /**
     * @inheritDoc
     */
    public function calculateRenewalCost(array $ddis): float
    {
        $total = 0.0;
        foreach ($ddis as $ddi) {
            $total += (float) ($ddi->getMonthlyPrice() ?? 0);
        }
        return round($total, 2);
    }

    /**
     * @inheritDoc
     */
    public function canRenewFromBalance(CompanyInterface $company, array $ddis): bool
    {
        $totalCost = $this->calculateRenewalCost($ddis);
        $balance = $this->getCompanyBalance($company);

        return $balance >= $totalCost;
    }

    /**
     * @inheritDoc
     */
    public function getCompanyBalance(CompanyInterface $company): float
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
     * Create an invoice for DID renewal
     *
     * @param CompanyInterface $company
     * @param DdiInterface[] $ddis
     * @param float $totalCost
     * @param bool $paidViaBalance Whether this is a balance payment (true) or WHMCS invoice (false)
     * @param string $renewalMode The renewal mode (per_did or consolidated)
     * @return InvoiceInterface
     */
    private function createInvoice(
        CompanyInterface $company,
        array $ddis,
        float $totalCost,
        bool $paidViaBalance,
        string $renewalMode = FirstPeriodCalculator::MODE_PER_DID
    ): InvoiceInterface {
        $now = new \DateTime();

        // Generate unique invoice number
        $invoiceNumber = sprintf(
            'DID-REN-%d-%s',
            $company->getId(),
            $now->format('YmdHis')
        );

        // Period is the upcoming month
        $periodStart = new \DateTime();
        $periodEnd = (clone $periodStart)->modify('+1 month');

        // Create invoice DTO
        $invoiceDto = Invoice::createDto();
        $invoiceDto
            ->setNumber($invoiceNumber)
            ->setInDate($periodStart)
            ->setOutDate($periodEnd)
            ->setTotal($totalCost)
            ->setTaxRate(0.0) // No tax for DID renewals (handled separately if needed)
            ->setTotalWithTax($totalCost)
            ->setStatus(InvoiceInterface::STATUS_CREATED)
            ->setStatusMsg($this->buildInvoiceStatusMessage($ddis, $renewalMode))
            ->setBrandId($company->getBrand()->getId())
            ->setCompanyId($company->getId())
            ->setInvoiceType(InvoiceInterface::INVOICE_TYPE_DID_RENEWAL);

        // For multi-DDI renewals, we don't link a single DDI
        // The statusMsg contains the DDI list
        // For single DDI, we can link it
        if (count($ddis) === 1) {
            $invoiceDto->setDdiId($ddis[0]->getId());
        }

        // Set sync status based on payment method
        if ($paidViaBalance) {
            // Paid via balance - no WHMCS sync needed
            $invoiceDto->setSyncStatus(InvoiceInterface::SYNC_STATUS_NOT_APPLICABLE);
        } else {
            // Needs WHMCS sync for payment
            $invoiceDto->setSyncStatus(InvoiceInterface::SYNC_STATUS_PENDING);
        }

        /** @var InvoiceInterface $invoice */
        $invoice = $this->entityTools->persistDto($invoiceDto, null, true);

        // Mark as paid via balance if applicable
        if ($paidViaBalance) {
            $invoice->markAsPaidViaBalance();
            $this->entityTools->persist($invoice, true);
        }

        return $invoice;
    }

    /**
     * Build status message listing renewed DDIs
     *
     * @param DdiInterface[] $ddis
     * @param string $renewalMode
     * @return string
     */
    private function buildInvoiceStatusMessage(array $ddis, string $renewalMode = FirstPeriodCalculator::MODE_PER_DID): string
    {
        $ddiNumbers = [];
        foreach ($ddis as $ddi) {
            $ddiNumbers[] = $ddi->getDdie164();
        }

        $count = count($ddiNumbers);
        $modeLabel = $renewalMode === FirstPeriodCalculator::MODE_CONSOLIDATED ? '[consolidated]' : '[per_did]';

        if ($count === 1) {
            return sprintf('DID renewal %s: %s', $modeLabel, $ddiNumbers[0]);
        }

        // For multiple DDIs, show first few and count
        if ($count <= 3) {
            return sprintf('DID renewal %s: %s', $modeLabel, implode(', ', $ddiNumbers));
        }

        return sprintf(
            'DID renewal %s: %s (+%d more)',
            $modeLabel,
            implode(', ', array_slice($ddiNumbers, 0, 3)),
            $count - 3
        );
    }

    /**
     * Advance renewal dates for all DDIs based on mode
     *
     * @param CompanyInterface $company
     * @param DdiInterface[] $ddis
     * @param string $renewalMode
     */
    private function advanceRenewalDates(CompanyInterface $company, array $ddis, string $renewalMode): void
    {
        if ($renewalMode === FirstPeriodCalculator::MODE_CONSOLIDATED) {
            // Consolidated mode: advance all to anchor + 1 month, update company anchor
            $this->advanceRenewalDatesConsolidated($company, $ddis);
        } else {
            // per_did mode: each DID advances independently
            foreach ($ddis as $ddi) {
                $this->advanceRenewalDatePerDid($ddi);
            }
        }
    }

    /**
     * Advance a single DDI's renewal date by 1 month (per_did mode)
     *
     * @param DdiInterface $ddi
     */
    private function advanceRenewalDatePerDid(DdiInterface $ddi): void
    {
        $currentRenewalAt = $ddi->getNextRenewalAt();
        if (!$currentRenewalAt) {
            // If no renewal date set, use current date as base
            $currentRenewalAt = new \DateTime();
        }

        // Convert to DateTime if needed
        if ($currentRenewalAt instanceof \DateTimeImmutable) {
            $currentRenewalAt = \DateTime::createFromInterface($currentRenewalAt);
        }

        $newRenewalAt = (clone $currentRenewalAt)->modify('+1 month');

        // Use DTO for consistency
        $ddiDto = $ddi->toDto();
        $ddiDto->setNextRenewalAt($newRenewalAt);
        $this->entityTools->persistDto($ddiDto, $ddi, true);

        $this->logger->debug(sprintf(
            'DID #%d (%s) [per_did]: nextRenewalAt advanced from %s to %s',
            $ddi->getId(),
            $ddi->getDdie164(),
            $currentRenewalAt->format('Y-m-d'),
            $newRenewalAt->format('Y-m-d')
        ));
    }

    /**
     * Advance all DDIs to anchor + 1 month and update company anchor (consolidated mode)
     *
     * @param CompanyInterface $company
     * @param DdiInterface[] $ddis
     */
    private function advanceRenewalDatesConsolidated(CompanyInterface $company, array $ddis): void
    {
        // Get current anchor
        $currentAnchor = $company->getDidRenewalAnchor();
        if (!$currentAnchor) {
            // Fallback: use the first DID's renewal date
            $firstDdi = reset($ddis);
            $currentAnchor = $firstDdi ? $firstDdi->getNextRenewalAt() : new \DateTime();
        }

        // Convert to DateTime
        if ($currentAnchor instanceof \DateTimeImmutable) {
            $currentAnchor = \DateTime::createFromInterface($currentAnchor);
        }

        // Calculate new anchor (+ 1 month)
        $newAnchor = (clone $currentAnchor)->modify('+1 month');

        // Update company anchor
        $companyDto = $company->toDto();
        $companyDto->setDidRenewalAnchor($newAnchor);
        $this->entityTools->persistDto($companyDto, $company, true);

        $this->logger->debug(sprintf(
            'Company #%d [consolidated]: anchor advanced from %s to %s',
            $company->getId(),
            $currentAnchor->format('Y-m-d'),
            $newAnchor->format('Y-m-d')
        ));

        // Update all DDIs to the new anchor
        foreach ($ddis as $ddi) {
            $ddiDto = $ddi->toDto();
            $ddiDto->setNextRenewalAt($newAnchor);
            $this->entityTools->persistDto($ddiDto, $ddi, true);

            $this->logger->debug(sprintf(
                'DID #%d (%s) [consolidated]: nextRenewalAt set to %s',
                $ddi->getId(),
                $ddi->getDdie164(),
                $newAnchor->format('Y-m-d')
            ));
        }
    }
}
