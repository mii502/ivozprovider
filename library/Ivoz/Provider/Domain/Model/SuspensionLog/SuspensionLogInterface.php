<?php

namespace Ivoz\Provider\Domain\Model\SuspensionLog;

use Ivoz\Core\Domain\Model\LoggableEntityInterface;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;

/**
 * SuspensionLogInterface
 */
interface SuspensionLogInterface extends LoggableEntityInterface
{
    public const ACTION_SUSPEND = 'suspend';
    public const ACTION_UNSUSPEND = 'unsuspend';
    public const ACTION_SUSPEND_DDI = 'suspend_ddi';
    public const ACTION_UNSUSPEND_DDI = 'unsuspend_ddi';

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
    public static function createDto($id = null): SuspensionLogDto;

    /**
     * @internal use EntityTools instead
     * @param null|SuspensionLogInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?SuspensionLogDto;

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param SuspensionLogDto $dto
     */
    public static function fromDto(DataTransferObjectInterface $dto, ForeignKeyTransformerInterface $fkTransformer): static;

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): SuspensionLogDto;

    public function getAction(): string;

    public function getReason(): ?string;

    public function getCreatedAt(): ?\DateTime;

    public function getCompany(): CompanyInterface;

    public function setCompany(CompanyInterface $company): static;

    public function getDdi(): ?DdiInterface;

    /**
     * Check if this is a company-level suspension
     */
    public function isCompanySuspension(): bool;

    /**
     * Check if this is a DDI-level suspension
     */
    public function isDdiSuspension(): bool;

    /**
     * Check if this is a suspension (not unsuspension) action
     */
    public function isSuspension(): bool;
}
