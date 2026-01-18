<?php

namespace Ivoz\Provider\Domain\Model\DidOrder;

use Ivoz\Core\Domain\Model\LoggableEntityInterface;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;

/**
 * DidOrderInterface
 */
interface DidOrderInterface extends LoggableEntityInterface
{
    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

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
    public static function createDto($id = null): DidOrderDto;

    /**
     * @internal use EntityTools instead
     * @param null|DidOrderInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?DidOrderDto;

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param DidOrderDto $dto
     */
    public static function fromDto(DataTransferObjectInterface $dto, ForeignKeyTransformerInterface $fkTransformer): static;

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): DidOrderDto;

    public function getStatus(): string;

    public function getRequestedAt(): \DateTime;

    public function getApprovedAt(): ?\DateTime;

    public function getRejectedAt(): ?\DateTime;

    public function getRejectionReason(): ?string;

    public function getSetupFee(): float;

    public function getMonthlyFee(): float;

    public function getCompany(): CompanyInterface;

    public function getDdi(): DdiInterface;

    public function getApprovedBy(): ?AdministratorInterface;

    /**
     * Check if order is pending approval
     */
    public function isPending(): bool;

    /**
     * Check if order has been approved
     */
    public function isApproved(): bool;

    /**
     * Check if order has been rejected
     */
    public function isRejected(): bool;

    /**
     * Check if order has expired
     */
    public function isExpired(): bool;
}
