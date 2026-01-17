<?php

declare(strict_types=1);

/**
 * DID Renewal Overdue Handler Interface
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/DidRenewalOverdueHandlerInterface.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-renewal (Phase 5)
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;

/**
 * Interface for handling DID release on WHMCS invoice overdue
 *
 * When a DID renewal invoice is marked overdue in WHMCS (non-payment),
 * this handler releases the DIDs back to inventory so they can be
 * purchased by other customers.
 *
 * This is a RELEASE, not suspension - the customer loses the DIDs.
 *
 * @see DidRenewalService For the daily renewal cron job
 * @see WhmcsOverdueWebhookController For the webhook that calls this handler
 */
interface DidRenewalOverdueHandlerInterface
{
    /**
     * Check if this handler supports the given invoice type
     *
     * @param string $invoiceType
     * @return bool
     */
    public function supports(string $invoiceType): bool;

    /**
     * Handle DID release for overdue renewal invoice
     *
     * Releases DIDs back to inventory:
     * - Sets inventoryStatus to 'available'
     * - Clears company assignment
     * - Clears assignedAt and nextRenewalAt dates
     * - Disables the DID
     *
     * @param InvoiceInterface $invoice The overdue did_renewal invoice
     * @return array{
     *     action: string,
     *     released_ddis: array<int, array{
     *         ddi_id: int,
     *         ddi_number: string,
     *         previous_company_id: int
     *     }>,
     *     count: int
     * }
     * @throws \DomainException If invoice type is not did_renewal
     */
    public function handle(InvoiceInterface $invoice): array;
}
