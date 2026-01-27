<?php

declare(strict_types=1);

/**
 * BYON Status DTO
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonStatus.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

/**
 * Status object for BYON feature
 */
final class ByonStatus
{
    private const DAILY_LIMIT = 10;

    public function __construct(
        private int $byonCount,
        private int $byonLimit,
        private int $dailyAttemptsUsed,
        private int $dailyAttemptsLimit = self::DAILY_LIMIT
    ) {
    }

    public function getByonCount(): int
    {
        return $this->byonCount;
    }

    public function getByonLimit(): int
    {
        return $this->byonLimit;
    }

    public function getDailyAttemptsRemaining(): int
    {
        return max(0, $this->dailyAttemptsLimit - $this->dailyAttemptsUsed);
    }

    public function getDailyAttemptsLimit(): int
    {
        return $this->dailyAttemptsLimit;
    }

    public function canAddByon(): bool
    {
        return $this->byonCount < $this->byonLimit
            && $this->getDailyAttemptsRemaining() > 0;
    }

    /**
     * Get the reason why BYON is disabled (or null if enabled)
     */
    public function getDisabledReason(): ?string
    {
        if ($this->byonCount >= $this->byonLimit) {
            return 'BYON_LIMIT_REACHED';
        }
        if ($this->getDailyAttemptsRemaining() <= 0) {
            return 'DAILY_LIMIT_REACHED';
        }
        return null;
    }

    /**
     * Get when the daily limit will reset (midnight UTC next day)
     * Returns ISO 8601 timestamp or null if not applicable
     */
    public function getDailyResetAt(): ?string
    {
        if ($this->getDailyAttemptsRemaining() > 0) {
            return null; // Not needed if daily limit not reached
        }

        // Calculate midnight UTC tomorrow
        $tomorrow = new \DateTime('tomorrow', new \DateTimeZone('UTC'));
        return $tomorrow->format(\DateTime::ATOM);
    }

    /**
     * Convert to API response format
     */
    public function toArray(): array
    {
        return [
            'byonCount' => $this->byonCount,
            'byonLimit' => $this->byonLimit,
            'dailyAttemptsRemaining' => $this->getDailyAttemptsRemaining(),
            'dailyAttemptsLimit' => $this->dailyAttemptsLimit,
            'canAddByon' => $this->canAddByon(),
            'disabledReason' => $this->getDisabledReason(),
            'dailyResetAt' => $this->getDailyResetAt(),
        ];
    }
}
