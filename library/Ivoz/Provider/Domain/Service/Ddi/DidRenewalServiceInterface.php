<?php

declare(strict_types=1);

/**
 * DID Renewal Service Interface
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidRenewalServiceInterface.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-renewal
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;

/**
 * Service for processing DID renewals
 *
 * Implements Balance-First renewal strategy:
 * 1. If company has sufficient balance → silent renewal with balance deduction
 * 2. If insufficient balance → create unpaid invoice for WHMCS sync
 *
 * Called by DailyDidRenewalCommand cron job
 */
interface DidRenewalServiceInterface
{
    /**
     * Get all DIDs due for renewal grouped by company
     *
     * @param \DateTime $date Date to check renewals against (typically today)
     * @return array<int, DdiInterface[]> Array of DDIs keyed by company ID
     */
    public function getDdisForRenewalGroupedByCompany(\DateTime $date): array;

    /**
     * Process silent renewal from company balance
     *
     * This method:
     * 1. Deducts total cost from company balance
     * 2. Syncs balance to CGRates
     * 3. Creates BalanceMovement record
     * 4. Creates paid Invoice (type=did_renewal, paidVia=balance)
     * 5. Advances nextRenewalAt for each DDI by 1 month
     *
     * Should only be called if canRenewFromBalance() returns true
     *
     * @param CompanyInterface $company The company to renew for
     * @param DdiInterface[] $ddis DIDs to renew
     * @return InvoiceInterface The created invoice (marked as paid)
     * @throws \DomainException If balance deduction fails
     */
    public function renewFromBalance(CompanyInterface $company, array $ddis): InvoiceInterface;

    /**
     * Create unpaid renewal invoice for WHMCS sync
     *
     * This method:
     * 1. Creates unpaid Invoice (type=did_renewal, syncStatus=pending)
     * 2. InvoiceWhmcsSyncObserver will auto-sync to WHMCS
     * 3. DIDs remain assigned but not renewed (awaiting payment)
     * 4. When WHMCS payment webhook received, DidRenewalHandler advances dates
     *
     * Called when company has insufficient balance
     *
     * @param CompanyInterface $company The company to invoice
     * @param DdiInterface[] $ddis DIDs to include in invoice
     * @return InvoiceInterface The created invoice (unpaid, pending sync)
     */
    public function createWhmcsRenewalInvoice(CompanyInterface $company, array $ddis): InvoiceInterface;

    /**
     * Calculate total renewal cost for a set of DIDs
     *
     * Sums the monthlyPrice of all provided DDIs
     *
     * @param DdiInterface[] $ddis DIDs to calculate cost for
     * @return float Total renewal cost
     */
    public function calculateRenewalCost(array $ddis): float;

    /**
     * Check if company can renew all DIDs from balance
     *
     * @param CompanyInterface $company The company to check
     * @param DdiInterface[] $ddis DIDs to check affordability for
     * @return bool True if balance >= total renewal cost
     */
    public function canRenewFromBalance(CompanyInterface $company, array $ddis): bool;

    /**
     * Get company's current balance
     *
     * @param CompanyInterface $company
     * @return float Current balance
     */
    public function getCompanyBalance(CompanyInterface $company): float;
}
