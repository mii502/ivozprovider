<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;

/**
 * Interface for DID order approval service (admin-facing)
 *
 * This service handles brand admin actions on DID orders:
 * - Approve: provisions the DID and creates an invoice
 * - Reject: releases the reservation with a reason
 */
interface DidOrderApprovalServiceInterface
{
    /**
     * Approve a pending DID order
     *
     * This method:
     * 1. Validates the order is pending
     * 2. Updates order status to 'approved'
     * 3. Provisions the DID to the company
     * 4. Creates an unpaid invoice for the setup fee (synced to WHMCS)
     *
     * @param DidOrderInterface $order The order to approve
     * @param AdministratorInterface $admin The admin approving the order
     * @return DidOrderResult Result object with order and invoice details or error
     */
    public function approve(DidOrderInterface $order, AdministratorInterface $admin): DidOrderResult;

    /**
     * Reject a pending DID order
     *
     * This method:
     * 1. Validates the order is pending
     * 2. Updates order status to 'rejected'
     * 3. Releases the DID reservation
     *
     * @param DidOrderInterface $order The order to reject
     * @param string $reason The reason for rejection
     * @return DidOrderResult Result object with order details or error
     */
    public function reject(DidOrderInterface $order, string $reason): DidOrderResult;
}
