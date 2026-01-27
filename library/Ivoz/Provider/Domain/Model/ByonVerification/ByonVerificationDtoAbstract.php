<?php

namespace Ivoz\Provider\Domain\Model\ByonVerification;

use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\DtoNormalizer;
use Ivoz\Provider\Domain\Model\Company\CompanyDto;

/**
 * ByonVerificationDtoAbstract
 * @codeCoverageIgnore
 */
abstract class ByonVerificationDtoAbstract implements DataTransferObjectInterface
{
    use DtoNormalizer;

    /**
     * @var string|null
     */
    private $phoneNumber = null;

    /**
     * @var string|null
     */
    private $verificationSid = null;

    /**
     * @var string|null
     */
    private $status = 'pending';

    /**
     * @var int|null
     */
    private $attempts = 0;

    /**
     * @var \DateTimeInterface|string|null
     */
    private $createdAt = null;

    /**
     * @var \DateTimeInterface|string|null
     */
    private $verifiedAt = null;

    /**
     * @var \DateTimeInterface|string|null
     */
    private $expiresAt = null;

    /**
     * @var int|null
     */
    private $id = null;

    /**
     * @var CompanyDto | null
     */
    private $company = null;

    public function __construct(?int $id = null)
    {
        $this->setId($id);
    }

    /**
     * @inheritdoc
     */
    public static function getPropertyMap(string $context = '', string $role = null): array
    {
        if ($context === self::CONTEXT_COLLECTION) {
            return ['id' => 'id'];
        }

        return [
            'phoneNumber' => 'phoneNumber',
            'verificationSid' => 'verificationSid',
            'status' => 'status',
            'attempts' => 'attempts',
            'createdAt' => 'createdAt',
            'verifiedAt' => 'verifiedAt',
            'expiresAt' => 'expiresAt',
            'id' => 'id',
            'companyId' => 'company',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $hideSensitiveData = false): array
    {
        $response = [
            'phoneNumber' => $this->getPhoneNumber(),
            'verificationSid' => $this->getVerificationSid(),
            'status' => $this->getStatus(),
            'attempts' => $this->getAttempts(),
            'createdAt' => $this->getCreatedAt(),
            'verifiedAt' => $this->getVerifiedAt(),
            'expiresAt' => $this->getExpiresAt(),
            'id' => $this->getId(),
            'company' => $this->getCompany(),
        ];

        if (!$hideSensitiveData) {
            return $response;
        }

        foreach ($this->sensitiveFields as $sensitiveField) {
            if (!array_key_exists($sensitiveField, $response)) {
                throw new \Exception($sensitiveField . ' field was not found');
            }
            $response[$sensitiveField] = '*****';
        }

        return $response;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setVerificationSid(?string $verificationSid): static
    {
        $this->verificationSid = $verificationSid;

        return $this;
    }

    public function getVerificationSid(): ?string
    {
        return $this->verificationSid;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getAttempts(): ?int
    {
        return $this->attempts;
    }

    public function setCreatedAt(\DateTimeInterface|string|null $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface|string|null
    {
        return $this->createdAt;
    }

    public function setVerifiedAt(\DateTimeInterface|string|null $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getVerifiedAt(): \DateTimeInterface|string|null
    {
        return $this->verifiedAt;
    }

    public function setExpiresAt(\DateTimeInterface|string|null $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getExpiresAt(): \DateTimeInterface|string|null
    {
        return $this->expiresAt;
    }

    /**
     * @param int|null $id
     */
    public function setId($id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setCompany(?CompanyDto $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getCompany(): ?CompanyDto
    {
        return $this->company;
    }

    public function setCompanyId(?int $id): static
    {
        $value = !is_null($id)
            ? new CompanyDto($id)
            : null;

        return $this->setCompany($value);
    }

    public function getCompanyId(): ?int
    {
        if ($dto = $this->getCompany()) {
            return $dto->getId();
        }

        return null;
    }
}
