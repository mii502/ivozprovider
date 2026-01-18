<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;

/**
 * Interface for DID order creation service (customer-facing)
 *
 * This service handles postpaid customer requests to order DIDs.
 * Orders require admin approval before provisioning.
 */
interface DidOrderServiceInterface
{
    /**
     * Check if a company can create DID orders
     *
     * Only postpaid companies can use the order workflow.
     * Prepaid companies must use the marketplace purchase feature.
     */
    public function canCreateOrders(CompanyInterface $company): bool;

    /**
     * Preview an order before creation
     *
     * Returns order details without creating the order.
     * Used by the UI to show pricing information.
     *
     * @return array{
     *   ddi: string,
     *   ddiId: int,
     *   country: string,
     *   setupFee: float,
     *   monthlyFee: float,
     *   canOrder: bool,
     *   reservationDuration: string
     * }
     */
    public function preview(CompanyInterface $company, DdiInterface $ddi): array;

    /**
     * Create a DID order
     *
     * This method:
     * 1. Validates the company is postpaid
     * 2. Validates the DID is available
     * 3. Reserves the DID (24 hours)
     * 4. Creates the order with 'pending_approval' status
     *
     * @param CompanyInterface $company The company requesting the DID
     * @param DdiInterface $ddi The DID being ordered
     * @return DidOrderResult Result object with order details or error
     */
    public function createOrder(CompanyInterface $company, DdiInterface $ddi): DidOrderResult;
}
