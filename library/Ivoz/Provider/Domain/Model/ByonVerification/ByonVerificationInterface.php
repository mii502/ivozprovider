<?php

namespace Ivoz\Provider\Domain\Model\ByonVerification;

use Ivoz\Core\Domain\Model\LoggableEntityInterface;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;

/**
 * ByonVerificationInterface
 * BYON (Bring Your Own Number) verification tracking
 */
interface ByonVerificationInterface extends LoggableEntityInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'failed';

    /**
     * @codeCoverageIgnore
     * @return array<string, mixed>
     */
    public function getChangeSet(): array;

    /**
     * Get id
     * @codeCoverageIgnore
     * @return integer
     */
    public function getId(): ?int;

    /**
     * @param int | null $id
     */
    public static function createDto($id = null): ByonVerificationDto;

    /**
     * @internal use EntityTools instead
     * @param null|ByonVerificationInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?ByonVerificationDto;

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param ByonVerificationDto $dto
     */
    public static function fromDto(DataTransferObjectInterface $dto, ForeignKeyTransformerInterface $fkTransformer): static;

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): ByonVerificationDto;

    public function getPhoneNumber(): string;

    public function getVerificationSid(): ?string;

    public function setVerificationSid(?string $verificationSid): static;

    public function getStatus(): string;

    public function setStatus(string $status): static;

    public function getAttempts(): int;

    public function incrementAttempts(): static;

    public function getCreatedAt(): \DateTimeInterface;

    public function getVerifiedAt(): ?\DateTimeInterface;

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt): static;

    public function getExpiresAt(): \DateTimeInterface;

    public function getCompany(): CompanyInterface;

    /**
     * Check if verification has expired
     */
    public function isExpired(): bool;

    /**
     * Check if verification can accept more OTP attempts
     */
    public function canRetry(): bool;

    /**
     * Mark verification as approved
     */
    public function markApproved(): static;

    /**
     * Mark verification as failed
     */
    public function markFailed(): static;
}
