<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Service\DidOrder;

use Ivoz\Provider\Domain\Model\DidOrder\DidOrderInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;

/**
 * Immutable result object for DID order operations
 */
final class DidOrderResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?string $errorCode,
        private readonly ?string $errorMessage,
        private readonly ?DidOrderInterface $order,
        private readonly ?DdiInterface $ddi,
        private readonly ?InvoiceInterface $invoice
    ) {
    }

    // Error codes
    public const ERROR_COMPANY_NOT_POSTPAID = 'company_not_postpaid';
    public const ERROR_DDI_NOT_AVAILABLE = 'ddi_not_available';
    public const ERROR_ORDER_NOT_FOUND = 'order_not_found';
    public const ERROR_ORDER_NOT_PENDING = 'order_not_pending';
    public const ERROR_DDI_PROVISION_FAILED = 'ddi_provision_failed';
    public const ERROR_INVOICE_CREATION_FAILED = 'invoice_creation_failed';
    public const ERROR_UNAUTHORIZED = 'unauthorized';

    /**
     * Create a success result for order creation
     */
    public static function orderCreated(DidOrderInterface $order): self
    {
        return new self(
            success: true,
            errorCode: null,
            errorMessage: null,
            order: $order,
            ddi: $order->getDdi(),
            invoice: null
        );
    }

    /**
     * Create a success result for order approval
     */
    public static function approved(
        DidOrderInterface $order,
        DdiInterface $ddi,
        ?InvoiceInterface $invoice
    ): self {
        return new self(
            success: true,
            errorCode: null,
            errorMessage: null,
            order: $order,
            ddi: $ddi,
            invoice: $invoice
        );
    }

    /**
     * Create a success result for order rejection
     */
    public static function rejected(DidOrderInterface $order): self
    {
        return new self(
            success: true,
            errorCode: null,
            errorMessage: null,
            order: $order,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: company is not postpaid
     */
    public static function companyNotPostpaid(): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_COMPANY_NOT_POSTPAID,
            errorMessage: 'DID ordering is only available for postpaid accounts. Prepaid accounts should use the marketplace purchase feature.',
            order: null,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: DID is not available
     */
    public static function ddiNotAvailable(int $ddiId): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_DDI_NOT_AVAILABLE,
            errorMessage: sprintf('DID #%d is not available for ordering', $ddiId),
            order: null,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: order not found
     */
    public static function orderNotFound(int $orderId): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_ORDER_NOT_FOUND,
            errorMessage: sprintf('Order #%d not found', $orderId),
            order: null,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: order is not pending
     */
    public static function orderNotPending(int $orderId, string $currentStatus): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_ORDER_NOT_PENDING,
            errorMessage: sprintf('Order #%d cannot be modified (current status: %s)', $orderId, $currentStatus),
            order: null,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: DID provisioning failed
     */
    public static function ddiProvisionFailed(string $reason): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_DDI_PROVISION_FAILED,
            errorMessage: sprintf('Failed to provision DID: %s', $reason),
            order: null,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: invoice creation failed
     */
    public static function invoiceCreationFailed(string $reason): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_INVOICE_CREATION_FAILED,
            errorMessage: sprintf('Failed to create invoice: %s', $reason),
            order: null,
            ddi: null,
            invoice: null
        );
    }

    /**
     * Create an error result: unauthorized access
     */
    public static function unauthorized(): self
    {
        return new self(
            success: false,
            errorCode: self::ERROR_UNAUTHORIZED,
            errorMessage: 'You are not authorized to perform this action',
            order: null,
            ddi: null,
            invoice: null
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getOrder(): ?DidOrderInterface
    {
        return $this->order;
    }

    public function getDdi(): ?DdiInterface
    {
        return $this->ddi;
    }

    public function getInvoice(): ?InvoiceInterface
    {
        return $this->invoice;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        if (!$this->success) {
            return [
                'success' => false,
                'error' => $this->errorCode,
                'message' => $this->errorMessage,
            ];
        }

        $result = [
            'success' => true,
        ];

        if ($this->order !== null) {
            $result['orderId'] = $this->order->getId();
            $result['status'] = $this->order->getStatus();
        }

        if ($this->ddi !== null) {
            $result['ddiId'] = $this->ddi->getId();
            $result['ddi'] = $this->ddi->getDdie164();
        }

        if ($this->invoice !== null) {
            $result['invoiceId'] = $this->invoice->getId();
            $result['invoiceNumber'] = $this->invoice->getNumber();
        }

        return $result;
    }
}
