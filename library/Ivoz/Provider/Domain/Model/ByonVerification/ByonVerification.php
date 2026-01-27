<?php

namespace Ivoz\Provider\Domain\Model\ByonVerification;

/**
 * ByonVerification
 * BYON (Bring Your Own Number) verification tracking
 */
class ByonVerification extends ByonVerificationAbstract implements ByonVerificationInterface
{
    use ByonVerificationTrait;

    private const MAX_OTP_ATTEMPTS = 3;

    /**
     * @codeCoverageIgnore
     * @return array<string, mixed>
     */
    public function getChangeSet(): array
    {
        return parent::getChangeSet();
    }

    /**
     * Get id
     * @codeCoverageIgnore
     * @return integer
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    protected function sanitizeValues(): void
    {
        // Auto-expire if past expiration time
        if ($this->isExpired() && $this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_EXPIRED;
        }
    }

    /**
     * Check if verification has expired
     */
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime('now', new \DateTimeZone('UTC'));
    }

    /**
     * Check if verification can accept more OTP attempts
     */
    public function canRetry(): bool
    {
        return $this->attempts < self::MAX_OTP_ATTEMPTS
            && $this->status === self::STATUS_PENDING
            && !$this->isExpired();
    }

    /**
     * Mark verification as approved
     */
    public function markApproved(): static
    {
        $this->setStatus(self::STATUS_APPROVED);
        $this->setVerifiedAt(new \DateTime('now', new \DateTimeZone('UTC')));

        return $this;
    }

    /**
     * Mark verification as failed
     */
    public function markFailed(): static
    {
        $this->setStatus(self::STATUS_FAILED);

        return $this;
    }
}
