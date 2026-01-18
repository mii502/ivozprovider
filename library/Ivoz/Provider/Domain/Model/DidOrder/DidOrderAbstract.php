<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Model\DidOrder;

use Assert\Assertion;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\ChangelogTrait;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Core\Domain\Model\Helper\DateTimeHelper;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Company\Company;
use Ivoz\Provider\Domain\Model\Ddi\Ddi;
use Ivoz\Provider\Domain\Model\Administrator\Administrator;

/**
 * DidOrderAbstract
 * @codeCoverageIgnore
 */
abstract class DidOrderAbstract
{
    use ChangelogTrait;

    /**
     * @var string
     * comment: enum:pending_approval|approved|rejected|expired
     */
    protected $status = DidOrderInterface::STATUS_PENDING_APPROVAL;

    /**
     * @var \DateTime
     */
    protected $requestedAt;

    /**
     * @var ?\DateTime
     */
    protected $approvedAt = null;

    /**
     * @var ?\DateTime
     */
    protected $rejectedAt = null;

    /**
     * @var ?string
     */
    protected $rejectionReason = null;

    /**
     * @var float
     */
    protected $setupFee = 0.0;

    /**
     * @var float
     */
    protected $monthlyFee = 0.0;

    /**
     * @var CompanyInterface
     */
    protected $company;

    /**
     * @var DdiInterface
     */
    protected $ddi;

    /**
     * @var ?AdministratorInterface
     */
    protected $approvedBy = null;

    /**
     * Constructor
     */
    protected function __construct(
        string $status,
        \DateTimeInterface $requestedAt,
        float $setupFee,
        float $monthlyFee
    ) {
        $this->setStatus($status);
        $this->setRequestedAt($requestedAt);
        $this->setSetupFee($setupFee);
        $this->setMonthlyFee($monthlyFee);
    }

    abstract public function getId(): null|string|int;

    public function __toString(): string
    {
        return sprintf(
            "%s#%s",
            "DidOrder",
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
    public static function createDto($id = null): DidOrderDto
    {
        return new DidOrderDto($id);
    }

    /**
     * @internal use EntityTools instead
     * @param null|DidOrderInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?DidOrderDto
    {
        if (!$entity) {
            return null;
        }

        Assertion::isInstanceOf($entity, DidOrderInterface::class);

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
     * @param DidOrderDto $dto
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, DidOrderDto::class);

        $status = $dto->getStatus();
        Assertion::notNull($status, 'getStatus value is null, but non null value was expected.');
        $requestedAt = $dto->getRequestedAt();
        Assertion::notNull($requestedAt, 'getRequestedAt value is null, but non null value was expected.');
        $setupFee = $dto->getSetupFee();
        Assertion::notNull($setupFee, 'getSetupFee value is null, but non null value was expected.');
        $monthlyFee = $dto->getMonthlyFee();
        Assertion::notNull($monthlyFee, 'getMonthlyFee value is null, but non null value was expected.');
        $company = $dto->getCompany();
        Assertion::notNull($company, 'getCompany value is null, but non null value was expected.');
        $ddi = $dto->getDdi();
        Assertion::notNull($ddi, 'getDdi value is null, but non null value was expected.');

        $self = new static(
            $status,
            $requestedAt,
            $setupFee,
            $monthlyFee
        );

        $self
            ->setApprovedAt($dto->getApprovedAt())
            ->setRejectedAt($dto->getRejectedAt())
            ->setRejectionReason($dto->getRejectionReason())
            ->setCompany($fkTransformer->transform($company))
            ->setDdi($fkTransformer->transform($ddi))
            ->setApprovedBy($fkTransformer->transform($dto->getApprovedBy()));

        $self->initChangelog();

        return $self;
    }

    /**
     * @internal use EntityTools instead
     * @param DidOrderDto $dto
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, DidOrderDto::class);

        $status = $dto->getStatus();
        Assertion::notNull($status, 'getStatus value is null, but non null value was expected.');
        $requestedAt = $dto->getRequestedAt();
        Assertion::notNull($requestedAt, 'getRequestedAt value is null, but non null value was expected.');
        $setupFee = $dto->getSetupFee();
        Assertion::notNull($setupFee, 'getSetupFee value is null, but non null value was expected.');
        $monthlyFee = $dto->getMonthlyFee();
        Assertion::notNull($monthlyFee, 'getMonthlyFee value is null, but non null value was expected.');
        $company = $dto->getCompany();
        Assertion::notNull($company, 'getCompany value is null, but non null value was expected.');
        $ddi = $dto->getDdi();
        Assertion::notNull($ddi, 'getDdi value is null, but non null value was expected.');

        $this
            ->setStatus($status)
            ->setRequestedAt($requestedAt)
            ->setApprovedAt($dto->getApprovedAt())
            ->setRejectedAt($dto->getRejectedAt())
            ->setRejectionReason($dto->getRejectionReason())
            ->setSetupFee($setupFee)
            ->setMonthlyFee($monthlyFee)
            ->setCompany($fkTransformer->transform($company))
            ->setDdi($fkTransformer->transform($ddi))
            ->setApprovedBy($fkTransformer->transform($dto->getApprovedBy()));

        return $this;
    }

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): DidOrderDto
    {
        return self::createDto()
            ->setStatus(self::getStatus())
            ->setRequestedAt(self::getRequestedAt())
            ->setApprovedAt(self::getApprovedAt())
            ->setRejectedAt(self::getRejectedAt())
            ->setRejectionReason(self::getRejectionReason())
            ->setSetupFee(self::getSetupFee())
            ->setMonthlyFee(self::getMonthlyFee())
            ->setCompany(Company::entityToDto(self::getCompany(), $depth))
            ->setDdi(Ddi::entityToDto(self::getDdi(), $depth))
            ->setApprovedBy(Administrator::entityToDto(self::getApprovedBy(), $depth));
    }

    /**
     * @return array<string, mixed>
     */
    protected function __toArray(): array
    {
        return [
            'status' => self::getStatus(),
            'requestedAt' => self::getRequestedAt(),
            'approvedAt' => self::getApprovedAt(),
            'rejectedAt' => self::getRejectedAt(),
            'rejectionReason' => self::getRejectionReason(),
            'setupFee' => self::getSetupFee(),
            'monthlyFee' => self::getMonthlyFee(),
            'companyId' => self::getCompany()->getId(),
            'ddiId' => self::getDdi()->getId(),
            'approvedById' => self::getApprovedBy()?->getId()
        ];
    }

    protected function setStatus(string $status): static
    {
        Assertion::maxLength($status, 30, 'status value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        Assertion::choice(
            $status,
            [
                DidOrderInterface::STATUS_PENDING_APPROVAL,
                DidOrderInterface::STATUS_APPROVED,
                DidOrderInterface::STATUS_REJECTED,
                DidOrderInterface::STATUS_EXPIRED,
            ],
            'status value "%s" is not an element of the valid values: %s'
        );

        $this->status = $status;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    protected function setRequestedAt(string|\DateTimeInterface $requestedAt): static
    {
        /** @var \DateTime */
        $requestedAt = DateTimeHelper::createOrFix(
            $requestedAt,
            null
        );

        if ($this->isInitialized() && $this->requestedAt == $requestedAt) {
            return $this;
        }

        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getRequestedAt(): \DateTime
    {
        return clone $this->requestedAt;
    }

    protected function setApprovedAt(string|\DateTimeInterface|null $approvedAt = null): static
    {
        if (!is_null($approvedAt)) {
            /** @var ?\DateTime */
            $approvedAt = DateTimeHelper::createOrFix(
                $approvedAt,
                null
            );

            if ($this->isInitialized() && $this->approvedAt == $approvedAt) {
                return $this;
            }
        }

        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getApprovedAt(): ?\DateTime
    {
        return !is_null($this->approvedAt) ? clone $this->approvedAt : null;
    }

    protected function setRejectedAt(string|\DateTimeInterface|null $rejectedAt = null): static
    {
        if (!is_null($rejectedAt)) {
            /** @var ?\DateTime */
            $rejectedAt = DateTimeHelper::createOrFix(
                $rejectedAt,
                null
            );

            if ($this->isInitialized() && $this->rejectedAt == $rejectedAt) {
                return $this;
            }
        }

        $this->rejectedAt = $rejectedAt;

        return $this;
    }

    public function getRejectedAt(): ?\DateTime
    {
        return !is_null($this->rejectedAt) ? clone $this->rejectedAt : null;
    }

    protected function setRejectionReason(?string $rejectionReason = null): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    protected function setSetupFee(float $setupFee): static
    {
        $this->setupFee = $setupFee;

        return $this;
    }

    public function getSetupFee(): float
    {
        return $this->setupFee;
    }

    protected function setMonthlyFee(float $monthlyFee): static
    {
        $this->monthlyFee = $monthlyFee;

        return $this;
    }

    public function getMonthlyFee(): float
    {
        return $this->monthlyFee;
    }

    protected function setCompany(CompanyInterface $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getCompany(): CompanyInterface
    {
        return $this->company;
    }

    protected function setDdi(DdiInterface $ddi): static
    {
        $this->ddi = $ddi;

        return $this;
    }

    public function getDdi(): DdiInterface
    {
        return $this->ddi;
    }

    protected function setApprovedBy(?AdministratorInterface $approvedBy = null): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedBy(): ?AdministratorInterface
    {
        return $this->approvedBy;
    }
}
