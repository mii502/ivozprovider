<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Model\SuspensionLog;

use Assert\Assertion;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\ChangelogTrait;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Core\Domain\Model\Helper\DateTimeHelper;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Company\Company;
use Ivoz\Provider\Domain\Model\Ddi\Ddi;

/**
 * SuspensionLogAbstract
 *
 * Audit trail for company/DDI suspension events
 *
 * @codeCoverageIgnore
 */
abstract class SuspensionLogAbstract
{
    use ChangelogTrait;

    // Note: Constants ACTION_SUSPEND, ACTION_UNSUSPEND, ACTION_SUSPEND_DDI, ACTION_UNSUSPEND_DDI
    // are defined only in SuspensionLogInterface to avoid inheritance ambiguity

    /**
     * @var string
     * column: action
     * comment: enum:suspend|unsuspend|suspend_ddi|unsuspend_ddi
     */
    protected $action;

    /**
     * @var ?string
     */
    protected $reason = null;

    /**
     * @var ?\DateTime
     */
    protected $createdAt = null;

    /**
     * @var CompanyInterface
     */
    protected $company;

    /**
     * @var ?DdiInterface
     */
    protected $ddi = null;

    /**
     * Constructor
     */
    protected function __construct(
        string $action
    ) {
        $this->setAction($action);
    }

    abstract public function getId(): null|string|int;

    public function __toString(): string
    {
        return sprintf(
            "%s#%s",
            "SuspensionLog",
            (string) $this->getId()
        );
    }

    /**
     * @throws \Exception
     */
    protected function sanitizeValues(): void
    {
    }

    /**
     * @param int | null $id
     */
    public static function createDto($id = null): SuspensionLogDto
    {
        return new SuspensionLogDto($id);
    }

    /**
     * @internal use EntityTools instead
     * @param null|SuspensionLogInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?SuspensionLogDto
    {
        if (!$entity) {
            return null;
        }

        Assertion::isInstanceOf($entity, SuspensionLogInterface::class);

        if ($depth < 1) {
            return static::createDto($entity->getId());
        }

        if ($entity instanceof \Doctrine\ORM\Proxy\Proxy && !$entity->__isInitialized()) {
            return static::createDto($entity->getId());
        }

        $dto = $entity->toDto($depth - 1);

        return $dto;
    }

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param SuspensionLogDto $dto
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, SuspensionLogDto::class);
        $action = $dto->getAction();
        Assertion::notNull($action, 'action is required');

        $self = new static(
            $action
        );

        $self
            ->setReason($dto->getReason())
            ->setCreatedAt($dto->getCreatedAt())
            ->setCompany($fkTransformer->transform($dto->getCompany()))
            ->setDdi($fkTransformer->transform($dto->getDdi()));

        $self->initChangelog();

        return $self;
    }

    /**
     * @internal use EntityTools instead
     * @param SuspensionLogDto $dto
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, SuspensionLogDto::class);

        $action = $dto->getAction();
        Assertion::notNull($action, 'action is required');

        $this
            ->setAction($action)
            ->setReason($dto->getReason())
            ->setCreatedAt($dto->getCreatedAt())
            ->setCompany($fkTransformer->transform($dto->getCompany()))
            ->setDdi($fkTransformer->transform($dto->getDdi()));

        return $this;
    }

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): SuspensionLogDto
    {
        return self::createDto()
            ->setAction(self::getAction())
            ->setReason(self::getReason())
            ->setCreatedAt(self::getCreatedAt())
            ->setCompany(Company::entityToDto(self::getCompany(), $depth))
            ->setDdi(Ddi::entityToDto(self::getDdi(), $depth));
    }

    /**
     * @return array<string, mixed>
     */
    protected function __toArray(): array
    {
        return [
            'action' => self::getAction(),
            'reason' => self::getReason(),
            'createdAt' => self::getCreatedAt(),
            'companyId' => self::getCompany()->getId(),
            'ddiId' => self::getDdi()?->getId()
        ];
    }

    protected function setAction(string $action): static
    {
        Assertion::maxLength($action, 20, 'action value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        Assertion::choice(
            $action,
            [
                SuspensionLogInterface::ACTION_SUSPEND,
                SuspensionLogInterface::ACTION_UNSUSPEND,
                SuspensionLogInterface::ACTION_SUSPEND_DDI,
                SuspensionLogInterface::ACTION_UNSUSPEND_DDI,
            ],
            'action value "%s" is not in the allowed choices: %s'
        );

        $this->action = $action;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    protected function setReason(?string $reason = null): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    protected function setCreatedAt(string|\DateTimeInterface|null $createdAt = null): static
    {
        if (!is_null($createdAt)) {

            /** @var ?\DateTime */
            $createdAt = DateTimeHelper::createOrFix(
                $createdAt,
                'CURRENT_TIMESTAMP'
            );

            if ($this->isInitialized() && $this->createdAt == $createdAt) {
                return $this;
            }
        }

        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return !is_null($this->createdAt) ? clone $this->createdAt : null;
    }

    public function setCompany(CompanyInterface $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getCompany(): CompanyInterface
    {
        return $this->company;
    }

    protected function setDdi(?DdiInterface $ddi = null): static
    {
        $this->ddi = $ddi;

        return $this;
    }

    public function getDdi(): ?DdiInterface
    {
        return $this->ddi;
    }
}
