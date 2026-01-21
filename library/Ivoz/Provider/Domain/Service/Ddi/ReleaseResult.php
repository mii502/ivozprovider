<?php

declare(strict_types=1);

/**
 * DID Release Result - Value object for release operation results
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Ddi/ReleaseResult.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

namespace Ivoz\Provider\Domain\Service\Ddi;

/**
 * Immutable value object representing the result of a DID release operation
 */
final class ReleaseResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?int $newDdiId = null,
        private readonly ?string $ddiNumber = null
    ) {
    }

    public static function success(int $newDdiId, string $ddiNumber): self
    {
        return new self(
            success: true,
            newDdiId: $newDdiId,
            ddiNumber: $ddiNumber
        );
    }

    public static function ddiNotFound(): self
    {
        return new self(
            success: false,
            errorCode: 'ddi_not_found',
            errorMessage: 'DID not found'
        );
    }

    public static function ddiNotOwned(): self
    {
        return new self(
            success: false,
            errorCode: 'ddi_not_owned',
            errorMessage: 'This DID does not belong to your company'
        );
    }

    public static function ddiNotAssigned(): self
    {
        return new self(
            success: false,
            errorCode: 'ddi_not_assigned',
            errorMessage: 'This DID is not currently assigned'
        );
    }

    public static function releaseFailed(string $message): self
    {
        return new self(
            success: false,
            errorCode: 'release_failed',
            errorMessage: $message
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

    public function getNewDdiId(): ?int
    {
        return $this->newDdiId;
    }

    public function getDdiNumber(): ?string
    {
        return $this->ddiNumber;
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
                'message' => 'DID released successfully',
                'ddiNumber' => $this->ddiNumber,
                'newDdiId' => $this->newDdiId,
            ];
        }

        return [
            'success' => false,
            'errorCode' => $this->errorCode,
            'message' => $this->errorMessage,
        ];
    }
}
