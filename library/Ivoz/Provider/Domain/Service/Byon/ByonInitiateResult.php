<?php

declare(strict_types=1);

/**
 * BYON Initiate Result DTO
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonInitiateResult.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

/**
 * Result object for BYON initiation (OTP send)
 */
final class ByonInitiateResult
{
    public function __construct(
        private bool $success,
        private string $message,
        private int $expiresIn,
        private int $dailyAttemptsRemaining,
        private int $byonCount,
        private int $byonLimit
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getDailyAttemptsRemaining(): int
    {
        return $this->dailyAttemptsRemaining;
    }

    public function getByonCount(): int
    {
        return $this->byonCount;
    }

    public function getByonLimit(): int
    {
        return $this->byonLimit;
    }

    /**
     * Convert to API response format
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'expiresIn' => $this->expiresIn,
            'dailyAttemptsRemaining' => $this->dailyAttemptsRemaining,
            'byonCount' => $this->byonCount,
            'byonLimit' => $this->byonLimit,
        ];
    }

    /**
     * Create success result
     */
    public static function success(
        int $expiresIn,
        int $dailyAttemptsRemaining,
        int $byonCount,
        int $byonLimit
    ): self {
        return new self(
            success: true,
            message: 'Verification code sent',
            expiresIn: $expiresIn,
            dailyAttemptsRemaining: $dailyAttemptsRemaining,
            byonCount: $byonCount,
            byonLimit: $byonLimit
        );
    }
}
