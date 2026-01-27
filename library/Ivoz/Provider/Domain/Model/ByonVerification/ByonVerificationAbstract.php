<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Model\ByonVerification;

use Assert\Assertion;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\ChangelogTrait;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Company\Company;

/**
 * ByonVerificationAbstract
 * @codeCoverageIgnore
 */
abstract class ByonVerificationAbstract
{
    use ChangelogTrait;

    /**
     * @var string
     * Phone number in E.164 format
     */
    protected $phoneNumber;

    /**
     * @var ?string
     * Somleng verification SID
     */
    protected $verificationSid = null;

    /**
     * @var string
     * comment: enum:pending|approved|expired|failed
     */
    protected $status = 'pending';

    /**
     * @var int
     * OTP check attempts
     */
    protected $attempts = 0;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var ?\DateTimeInterface
     */
    protected $verifiedAt = null;

    /**
     * @var \DateTimeInterface
     */
    protected $expiresAt;

    /**
     * @var CompanyInterface
     */
    protected $company;

    /**
     * Constructor
     */
    protected function __construct(
        string $phoneNumber,
        string $status,
        int $attempts,
        \DateTimeInterface $createdAt,
        \DateTimeInterface $expiresAt
    ) {
        $this->setPhoneNumber($phoneNumber);
        $this->setStatus($status);
        $this->setAttempts($attempts);
        $this->setCreatedAt($createdAt);
        $this->setExpiresAt($expiresAt);
    }

    abstract public function getId(): null|string|int;

    public function __toString(): string
    {
        return sprintf(
            "%s#%s",
            "ByonVerification",
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
    public static function createDto($id = null): ByonVerificationDto
    {
        return new ByonVerificationDto($id);
    }

    /**
     * @internal use EntityTools instead
     * @param null|ByonVerificationInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?ByonVerificationDto
    {
        if (!$entity) {
            return null;
        }

        Assertion::isInstanceOf($entity, ByonVerificationInterface::class);

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
     * @param ByonVerificationDto $dto
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, ByonVerificationDto::class);

        $phoneNumber = $dto->getPhoneNumber();
        Assertion::notNull($phoneNumber, 'getPhoneNumber value is null, but non null value was expected.');
        $status = $dto->getStatus();
        Assertion::notNull($status, 'getStatus value is null, but non null value was expected.');
        $attempts = $dto->getAttempts();
        Assertion::notNull($attempts, 'getAttempts value is null, but non null value was expected.');
        $createdAt = $dto->getCreatedAt();
        Assertion::notNull($createdAt, 'getCreatedAt value is null, but non null value was expected.');
        $expiresAt = $dto->getExpiresAt();
        Assertion::notNull($expiresAt, 'getExpiresAt value is null, but non null value was expected.');
        $company = $dto->getCompany();
        Assertion::notNull($company, 'getCompany value is null, but non null value was expected.');

        $self = new static(
            $phoneNumber,
            $status,
            $attempts,
            $createdAt,
            $expiresAt
        );

        $self
            ->setVerificationSid($dto->getVerificationSid())
            ->setVerifiedAt($dto->getVerifiedAt())
            ->setCompany($fkTransformer->transform($company));

        $self->initChangelog();

        return $self;
    }

    /**
     * @internal use EntityTools instead
     * @param ByonVerificationDto $dto
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, ByonVerificationDto::class);

        $phoneNumber = $dto->getPhoneNumber();
        Assertion::notNull($phoneNumber, 'getPhoneNumber value is null, but non null value was expected.');
        $status = $dto->getStatus();
        Assertion::notNull($status, 'getStatus value is null, but non null value was expected.');
        $attempts = $dto->getAttempts();
        Assertion::notNull($attempts, 'getAttempts value is null, but non null value was expected.');
        $createdAt = $dto->getCreatedAt();
        Assertion::notNull($createdAt, 'getCreatedAt value is null, but non null value was expected.');
        $expiresAt = $dto->getExpiresAt();
        Assertion::notNull($expiresAt, 'getExpiresAt value is null, but non null value was expected.');
        $company = $dto->getCompany();
        Assertion::notNull($company, 'getCompany value is null, but non null value was expected.');

        $this
            ->setPhoneNumber($phoneNumber)
            ->setVerificationSid($dto->getVerificationSid())
            ->setStatus($status)
            ->setAttempts($attempts)
            ->setCreatedAt($createdAt)
            ->setVerifiedAt($dto->getVerifiedAt())
            ->setExpiresAt($expiresAt)
            ->setCompany($fkTransformer->transform($company));

        return $this;
    }

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): ByonVerificationDto
    {
        return self::createDto()
            ->setPhoneNumber(self::getPhoneNumber())
            ->setVerificationSid(self::getVerificationSid())
            ->setStatus(self::getStatus())
            ->setAttempts(self::getAttempts())
            ->setCreatedAt(self::getCreatedAt())
            ->setVerifiedAt(self::getVerifiedAt())
            ->setExpiresAt(self::getExpiresAt())
            ->setCompany(Company::entityToDto(self::getCompany(), $depth));
    }

    /**
     * @return array<string, mixed>
     */
    protected function __toArray(): array
    {
        return [
            'phoneNumber' => self::getPhoneNumber(),
            'verificationSid' => self::getVerificationSid(),
            'status' => self::getStatus(),
            'attempts' => self::getAttempts(),
            'createdAt' => self::getCreatedAt(),
            'verifiedAt' => self::getVerifiedAt(),
            'expiresAt' => self::getExpiresAt(),
            'companyId' => self::getCompany()->getId(),
        ];
    }

    protected function setPhoneNumber(string $phoneNumber): static
    {
        Assertion::maxLength($phoneNumber, 25, 'phoneNumber value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        Assertion::regex($phoneNumber, '/^\+[1-9]\d{1,14}$/', 'phoneNumber must be in E.164 format');

        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function setVerificationSid(?string $verificationSid = null): static
    {
        if (!is_null($verificationSid)) {
            Assertion::maxLength($verificationSid, 64, 'verificationSid value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        }

        $this->verificationSid = $verificationSid;

        return $this;
    }

    public function getVerificationSid(): ?string
    {
        return $this->verificationSid;
    }

    public function setStatus(string $status): static
    {
        Assertion::maxLength($status, 20, 'status value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        Assertion::choice(
            $status,
            [
                ByonVerificationInterface::STATUS_PENDING,
                ByonVerificationInterface::STATUS_APPROVED,
                ByonVerificationInterface::STATUS_EXPIRED,
                ByonVerificationInterface::STATUS_FAILED,
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

    protected function setAttempts(int $attempts): static
    {
        Assertion::greaterOrEqualThan($attempts, 0, 'attempts provided "%s" is not greater or equal than "%s".');

        $this->attempts = $attempts;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): static
    {
        $this->attempts++;

        return $this;
    }

    protected function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt = null): static
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    protected function setExpiresAt(\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
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
}
