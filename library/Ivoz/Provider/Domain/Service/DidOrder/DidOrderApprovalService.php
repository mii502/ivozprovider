<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\Invoice\Invoice;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Domain\Service\Ddi\FirstPeriodCalculator;
use Psr\Log\LoggerInterface;

/**
 * DID Order Approval Service - Handles admin approval and rejection of orders
 *
 * For postpaid customers:
 * - Approval: provisions DID immediately, creates invoice for WHMCS sync
 * - Rejection: releases the DID reservation
 *
 * Sends email notifications for order lifecycle events.
 */
class DidOrderApprovalService implements DidOrderApprovalServiceInterface
{
    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly DdiRepository $ddiRepository,
        private readonly FirstPeriodCalculator $firstPeriodCalculator,
        private readonly LoggerInterface $logger,
        private readonly ?DidOrderEmailSenderInterface $emailSender = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function approve(DidOrderInterface $order, AdministratorInterface $admin): DidOrderResult
    {
        $orderId = $order->getId();

        $this->logger->info(sprintf(
            'DID order approval: Admin #%d approving Order #%d',
            $admin->getId(),
            $orderId
        ));

        // Step 1: Validate order is pending
        if (!$order->isPending()) {
            $this->logger->warning(sprintf(
                'DID order approval failed: Order #%d is not pending (status: %s)',
                $orderId,
                $order->getStatus()
            ));
            return DidOrderResult::orderNotPending($orderId, $order->getStatus());
        }

        $ddi = $order->getDdi();
        $company = $order->getCompany();

        // Step 2: Refresh DDI and validate it's still reserved for this company
        $currentDdi = $this->ddiRepository->find($ddi->getId());
        if (!$currentDdi) {
            $this->logger->error(sprintf(
                'DID order approval failed: DDI #%d not found',
                $ddi->getId()
            ));
            return DidOrderResult::ddiProvisionFailed('DID no longer exists');
        }

        // Check reservation is still valid
        if ($currentDdi->getInventoryStatus() !== DdiInterface::INVENTORYSTATUS_RESERVED) {
            $this->logger->error(sprintf(
                'DID order approval failed: DDI #%d is no longer reserved (status: %s)',
                $ddi->getId(),
                $currentDdi->getInventoryStatus()
            ));
            return DidOrderResult::ddiProvisionFailed('DID is no longer reserved');
        }

        $reservedForCompany = $currentDdi->getReservedForCompany();
        if (!$reservedForCompany || $reservedForCompany->getId() !== $company->getId()) {
            $this->logger->error(sprintf(
                'DID order approval failed: DDI #%d is reserved for different company',
                $ddi->getId()
            ));
            return DidOrderResult::ddiProvisionFailed('DID is reserved for a different company');
        }

        // Step 3: Update order status to approved
        $order->approve($admin);
        $this->entityTools->persist($order, true);

        // Step 4: Calculate costs and provision DID
        $calculation = $this->firstPeriodCalculator->calculate(
            $order->getSetupFee(),
            $order->getMonthlyFee()
        );

        $this->assignDdiToCompany($currentDdi, $company, $calculation['nextRenewalDate']);

        // Step 5: Create invoice for WHMCS sync (postpaid - customer pays via WHMCS)
        $invoice = null;
        $totalAmount = $calculation['totalDueNow'];

        if ($totalAmount > 0) {
            try {
                $invoice = $this->createInvoice($company, $currentDdi, $totalAmount, $calculation);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    'DID order approval: Invoice creation failed for Order #%d: %s',
                    $orderId,
                    $e->getMessage()
                ));
                // Don't fail the approval if invoice creation fails
                // The DID is provisioned, invoice can be created manually
            }
        }

        $this->logger->info(sprintf(
            'DID order approved: Order #%d, DID #%d provisioned to Company #%d%s',
            $orderId,
            $currentDdi->getId(),
            $company->getId(),
            $invoice ? sprintf(', Invoice #%s created', $invoice->getNumber()) : ''
        ));

        // Send approval notification email
        if ($this->emailSender !== null) {
            try {
                $this->emailSender->sendOrderApprovedNotification($order);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'DID order approval: Failed to send notification email for Order #%d: %s',
                    $orderId,
                    $e->getMessage()
                ));
                // Don't fail the approval if email fails
            }
        }

        return DidOrderResult::approved($order, $currentDdi, $invoice);
    }

    /**
     * @inheritDoc
     */
    public function reject(DidOrderInterface $order, string $reason): DidOrderResult
    {
        $orderId = $order->getId();

        $this->logger->info(sprintf(
            'DID order rejection: Rejecting Order #%d with reason: %s',
            $orderId,
            $reason
        ));

        // Step 1: Validate order is pending
        if (!$order->isPending()) {
            $this->logger->warning(sprintf(
                'DID order rejection failed: Order #%d is not pending (status: %s)',
                $orderId,
                $order->getStatus()
            ));
            return DidOrderResult::orderNotPending($orderId, $order->getStatus());
        }

        // Step 2: Reject the order
        $order->reject($reason);
        $this->entityTools->persist($order, true);

        // Step 3: Release the DID reservation
        $ddi = $order->getDdi();
        $this->releaseDdiReservation($ddi);

        $this->logger->info(sprintf(
            'DID order rejected: Order #%d, DID #%d released',
            $orderId,
            $ddi->getId()
        ));

        // Send rejection notification email
        if ($this->emailSender !== null) {
            try {
                $this->emailSender->sendOrderRejectedNotification($order);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'DID order rejection: Failed to send notification email for Order #%d: %s',
                    $orderId,
                    $e->getMessage()
                ));
                // Don't fail the rejection if email fails
            }
        }

        return DidOrderResult::rejected($order);
    }

    /**
     * Assign DID to company (provisions the DID)
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
     * Release a DID reservation
     */
    private function releaseDdiReservation(DdiInterface $ddi): void
    {
        // Refresh from database
        $currentDdi = $this->ddiRepository->find($ddi->getId());
        if (!$currentDdi) {
            return;
        }

        // Only release if it's currently reserved
        if ($currentDdi->getInventoryStatus() !== DdiInterface::INVENTORYSTATUS_RESERVED) {
            return;
        }

        $ddiDto = $currentDdi->toDto();
        $ddiDto
            ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_AVAILABLE)
            ->setReservedForCompanyId(null)
            ->setReservedUntil(null);

        $this->entityTools->persistDto($ddiDto, $currentDdi, true);
    }

    /**
     * Create an invoice for the DID order (syncs to WHMCS for payment)
     */
    private function createInvoice(
        CompanyInterface $company,
        DdiInterface $ddi,
        float $totalAmount,
        array $calculation
    ): InvoiceInterface {
        $now = new \DateTime();

        // Generate unique invoice number
        $invoiceNumber = sprintf(
            'DID-ORDER-%d-%s',
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

        // Create invoice DTO with pending sync status for WHMCS
        $invoiceDto = Invoice::createDto();
        $invoiceDto
            ->setNumber($invoiceNumber)
            ->setInDate($inDate)
            ->setOutDate($outDate)
            ->setTotal($totalAmount)
            ->setTaxRate(0.0)
            ->setTotalWithTax($totalAmount)
            ->setStatus(InvoiceInterface::STATUS_CREATED)
            ->setStatusMsg('DID order approved - awaiting payment')
            ->setBrandId($company->getBrand()->getId())
            ->setCompanyId($company->getId())
            ->setInvoiceType(InvoiceInterface::INVOICE_TYPE_DID_PURCHASE)
            ->setSyncStatus(InvoiceInterface::SYNC_STATUS_PENDING)
            ->setDdiId($ddi->getId());

        /** @var InvoiceInterface $invoice */
        $invoice = $this->entityTools->persistDto($invoiceDto, null, true);

        return $invoice;
    }
}
