<?php

declare(strict_types=1);

/**
 * DID Purchase Service Interface
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidPurchaseServiceInterface.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;

/**
 * Interface for DID purchase operations
 */
interface DidPurchaseServiceInterface
{
    /**
     * Preview purchase costs without committing
     *
     * @param CompanyInterface $company Company making the purchase
     * @param DdiInterface $ddi DID to purchase
     * @return array{
     *     ddi: string,
     *     ddiId: int,
     *     country: string,
     *     setupPrice: float,
     *     monthlyPrice: float,
     *     proratedFirstMonth: float,
     *     totalDueNow: float,
     *     nextRenewalDate: string,
     *     nextRenewalAmount: float,
     *     currentBalance: float,
     *     balanceAfterPurchase: float,
     *     canPurchase: bool,
     *     breakdown: array<int, array{description: string, amount: float}>
     * }
     */
    public function preview(CompanyInterface $company, DdiInterface $ddi): array;

    /**
     * Execute DID purchase
     *
     * Steps:
     * 1. Verify DID is still available
     * 2. Calculate prorated first period
     * 3. Check company balance
     * 4. Deduct balance via DecrementBalance (creates BalanceMovement)
     * 5. Create invoice (type=did_purchase, paidVia=balance)
     * 6. Update DDI: status=assigned, company, assignedAt, nextRenewalAt
     *
     * @param CompanyInterface $company Company making the purchase
     * @param DdiInterface $ddi DID to purchase
     * @return PurchaseResult
     */
    public function purchase(CompanyInterface $company, DdiInterface $ddi): PurchaseResult;

    /**
     * Check if company can afford to purchase a DID
     *
     * @param CompanyInterface $company Company to check
     * @param DdiInterface $ddi DID to check
     * @return bool
     */
    public function canAfford(CompanyInterface $company, DdiInterface $ddi): bool;
}
