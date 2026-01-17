<?php

declare(strict_types=1);

/**
 * DID Purchase Result - Value object for purchase operation results
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/PurchaseResult.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;

/**
 * Immutable value object representing the result of a DID purchase operation
 */
final class PurchaseResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?string $errorCode,
        private readonly ?string $errorMessage,
        private readonly ?DdiInterface $ddi,
        private readonly ?InvoiceInterface $invoice,
        private readonly ?float $totalCharged,
        private readonly ?float $currentBalance,
        private readonly ?float $requiredAmount
    ) {
    }

    // Success factory
    public static function success(
        DdiInterface $ddi,
        InvoiceInterface $invoice,
        float $totalCharged,
        float $currentBalance
    ): self {
        return new self(
            success: true,
            errorCode: null,
            errorMessage: null,
            ddi: $ddi,
            invoice: $invoice,
            totalCharged: $totalCharged,
            currentBalance: $currentBalance,
            requiredAmount: null
        );
    }

    // Error factories
    public static function insufficientBalance(
        float $requiredAmount,
        float $currentBalance
    ): self {
        return new self(
            success: false,
            errorCode: 'INSUFFICIENT_BALANCE',
            errorMessage: sprintf(
                'Insufficient balance. Required: %.2f, Available: %.2f',
                $requiredAmount,
                $currentBalance
            ),
            ddi: null,
            invoice: null,
            totalCharged: null,
            currentBalance: $currentBalance,
            requiredAmount: $requiredAmount
        );
    }

    public static function ddiNotAvailable(int $ddiId): self
    {
        return new self(
            success: false,
            errorCode: 'DDI_NOT_AVAILABLE',
            errorMessage: sprintf('DID %d is not available for purchase', $ddiId),
            ddi: null,
            invoice: null,
            totalCharged: null,
            currentBalance: null,
            requiredAmount: null
        );
    }

    public static function ddiNotFound(int $ddiId): self
    {
        return new self(
            success: false,
            errorCode: 'DDI_NOT_FOUND',
            errorMessage: sprintf('DID %d was not found', $ddiId),
            ddi: null,
            invoice: null,
            totalCharged: null,
            currentBalance: null,
            requiredAmount: null
        );
    }

    public static function balanceDeductionFailed(string $error): self
    {
        return new self(
            success: false,
            errorCode: 'BALANCE_DEDUCTION_FAILED',
            errorMessage: sprintf('Balance deduction failed: %s', $error),
            ddi: null,
            invoice: null,
            totalCharged: null,
            currentBalance: null,
            requiredAmount: null
        );
    }

    public static function error(string $code, string $message): self
    {
        return new self(
            success: false,
            errorCode: $code,
            errorMessage: $message,
            ddi: null,
            invoice: null,
            totalCharged: null,
            currentBalance: null,
            requiredAmount: null
        );
    }

    // Getters
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

    public function getDdi(): ?DdiInterface
    {
        return $this->ddi;
    }

    public function getInvoice(): ?InvoiceInterface
    {
        return $this->invoice;
    }

    public function getTotalCharged(): ?float
    {
        return $this->totalCharged;
    }

    public function getCurrentBalance(): ?float
    {
        return $this->currentBalance;
    }

    public function getRequiredAmount(): ?float
    {
        return $this->requiredAmount;
    }

    /**
     * Convert to array for API response
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->success) {
            return [
                'success' => true,
                'ddiId' => $this->ddi?->getId(),
                'ddi' => $this->ddi?->getDdie164(),
                'invoiceId' => $this->invoice?->getId(),
                'invoiceNumber' => $this->invoice?->getNumber(),
                'totalCharged' => $this->totalCharged,
                'currentBalance' => $this->currentBalance,
            ];
        }

        return [
            'success' => false,
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->errorMessage,
            'currentBalance' => $this->currentBalance,
            'requiredAmount' => $this->requiredAmount,
        ];
    }
}
