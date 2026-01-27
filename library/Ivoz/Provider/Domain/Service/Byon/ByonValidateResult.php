<?php

declare(strict_types=1);

/**
 * BYON Validate Result DTO
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonValidateResult.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

/**
 * Result object for BYON phone number validation (pre-SMS check)
 */
final class ByonValidateResult
{
    public function __construct(
        private bool $valid,
        private ?string $error,
        private ?string $errorCode,
        private ?string $countryName,
        private ?string $countryCode,
        private ?int $countryId,
        private ?string $nationalNumber,
        private ?string $e164Number
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getCountryName(): ?string
    {
        return $this->countryName;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getCountryId(): ?int
    {
        return $this->countryId;
    }

    public function getNationalNumber(): ?string
    {
        return $this->nationalNumber;
    }

    public function getE164Number(): ?string
    {
        return $this->e164Number;
    }

    /**
     * Convert to API response format
     */
    public function toArray(): array
    {
        if (!$this->valid) {
            return [
                'valid' => false,
                'error' => $this->error,
                'errorCode' => $this->errorCode,
            ];
        }

        return [
            'valid' => true,
            'country' => $this->countryId !== null ? [
                'id' => $this->countryId,
                'name' => $this->countryName,
                'code' => $this->countryCode,
            ] : null,
            'nationalNumber' => $this->nationalNumber,
            'e164Number' => $this->e164Number,
        ];
    }

    /**
     * Create success result
     */
    public static function success(
        string $countryName,
        string $countryCode,
        int $countryId,
        string $nationalNumber,
        string $e164Number
    ): self {
        return new self(
            valid: true,
            error: null,
            errorCode: null,
            countryName: $countryName,
            countryCode: $countryCode,
            countryId: $countryId,
            nationalNumber: $nationalNumber,
            e164Number: $e164Number
        );
    }

    /**
     * Create success result without detected country
     */
    public static function successUnknownCountry(
        string $e164Number
    ): self {
        return new self(
            valid: true,
            error: null,
            errorCode: null,
            countryName: null,
            countryCode: null,
            countryId: null,
            nationalNumber: ltrim($e164Number, '+'),
            e164Number: $e164Number
        );
    }

    /**
     * Create error result
     */
    public static function error(string $errorCode, string $error): self
    {
        return new self(
            valid: false,
            error: $error,
            errorCode: $errorCode,
            countryName: null,
            countryCode: null,
            countryId: null,
            nationalNumber: null,
            e164Number: null
        );
    }
}
