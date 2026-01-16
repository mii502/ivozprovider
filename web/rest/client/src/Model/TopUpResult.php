<?php

declare(strict_types=1);

namespace Model;

use Ivoz\Api\Core\Annotation\AttributeDefinition;

/**
 * Response model for POST /balance/topup endpoint
 * @codeCoverageIgnore
 */
class TopUpResult
{
    /**
     * @var bool
     * @AttributeDefinition(type="bool")
     */
    protected $success;

    /**
     * @var int|null
     * @AttributeDefinition(type="int")
     */
    protected $invoiceId;

    /**
     * @var float|null
     * @AttributeDefinition(type="float")
     */
    protected $amount;

    /**
     * @var int|null
     * @AttributeDefinition(type="int")
     */
    protected $whmcsInvoiceId;

    /**
     * @var string|null
     * @AttributeDefinition(type="string")
     */
    protected $paymentUrl;

    /**
     * @var string|null
     * @AttributeDefinition(type="string")
     */
    protected $error;

    public function __construct(
        bool $success,
        ?int $invoiceId = null,
        ?float $amount = null,
        ?int $whmcsInvoiceId = null,
        ?string $paymentUrl = null,
        ?string $error = null
    ) {
        $this->success = $success;
        $this->invoiceId = $invoiceId;
        $this->amount = $amount;
        $this->whmcsInvoiceId = $whmcsInvoiceId;
        $this->paymentUrl = $paymentUrl;
        $this->error = $error;
    }

    /** @return bool */
    public function getSuccess(): bool { return $this->success; }

    /** @return int|null */
    public function getInvoiceId(): ?int { return $this->invoiceId; }

    /** @return float|null */
    public function getAmount(): ?float { return $this->amount; }

    /** @return int|null */
    public function getWhmcsInvoiceId(): ?int { return $this->whmcsInvoiceId; }

    /** @return string|null */
    public function getPaymentUrl(): ?string { return $this->paymentUrl; }

    /** @return string|null */
    public function getError(): ?string { return $this->error; }

    public static function success(int $invoiceId, float $amount, ?int $whmcsInvoiceId = null, ?string $paymentUrl = null): self {
        return new self(true, $invoiceId, $amount, $whmcsInvoiceId, $paymentUrl);
    }

    public static function error(string $error): self {
        return new self(false, null, null, null, null, $error);
    }
}
