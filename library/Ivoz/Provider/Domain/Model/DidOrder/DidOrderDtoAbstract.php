<?php

namespace Ivoz\Provider\Domain\Model\DidOrder;

use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\DtoNormalizer;
use Ivoz\Provider\Domain\Model\Company\CompanyDto;
use Ivoz\Provider\Domain\Model\Ddi\DdiDto;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorDto;

/**
 * DidOrderDtoAbstract
 * @codeCoverageIgnore
 */
abstract class DidOrderDtoAbstract implements DataTransferObjectInterface
{
    use DtoNormalizer;

    /**
     * @var string|null
     */
    private $status = DidOrderInterface::STATUS_PENDING_APPROVAL;

    /**
     * @var \DateTimeInterface|string|null
     */
    private $requestedAt = null;

    /**
     * @var \DateTimeInterface|string|null
     */
    private $approvedAt = null;

    /**
     * @var \DateTimeInterface|string|null
     */
    private $rejectedAt = null;

    /**
     * @var string|null
     */
    private $rejectionReason = null;

    /**
     * @var float|null
     */
    private $setupFee = 0.0;

    /**
     * @var float|null
     */
    private $monthlyFee = 0.0;

    /**
     * @var int|null
     */
    private $id = null;

    /**
     * @var CompanyDto | null
     */
    private $company = null;

    /**
     * @var DdiDto | null
     */
    private $ddi = null;

    /**
     * @var AdministratorDto | null
     */
    private $approvedBy = null;

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
            'status' => 'status',
            'requestedAt' => 'requestedAt',
            'approvedAt' => 'approvedAt',
            'rejectedAt' => 'rejectedAt',
            'rejectionReason' => 'rejectionReason',
            'setupFee' => 'setupFee',
            'monthlyFee' => 'monthlyFee',
            'id' => 'id',
            'companyId' => 'company',
            'ddiId' => 'ddi',
            'approvedById' => 'approvedBy'
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $hideSensitiveData = false): array
    {
        $response = [
            'status' => $this->getStatus(),
            'requestedAt' => $this->getRequestedAt(),
            'approvedAt' => $this->getApprovedAt(),
            'rejectedAt' => $this->getRejectedAt(),
            'rejectionReason' => $this->getRejectionReason(),
            'setupFee' => $this->getSetupFee(),
            'monthlyFee' => $this->getMonthlyFee(),
            'id' => $this->getId(),
            'company' => $this->getCompany(),
            'ddi' => $this->getDdi(),
            'approvedBy' => $this->getApprovedBy()
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

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setRequestedAt(null|\DateTimeInterface|string $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getRequestedAt(): \DateTimeInterface|string|null
    {
        return $this->requestedAt;
    }

    public function setApprovedAt(null|\DateTimeInterface|string $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getApprovedAt(): \DateTimeInterface|string|null
    {
        return $this->approvedAt;
    }

    public function setRejectedAt(null|\DateTimeInterface|string $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;

        return $this;
    }

    public function getRejectedAt(): \DateTimeInterface|string|null
    {
        return $this->rejectedAt;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setSetupFee(?float $setupFee): static
    {
        $this->setupFee = $setupFee;

        return $this;
    }

    public function getSetupFee(): ?float
    {
        return $this->setupFee;
    }

    public function setMonthlyFee(?float $monthlyFee): static
    {
        $this->monthlyFee = $monthlyFee;

        return $this;
    }

    public function getMonthlyFee(): ?float
    {
        return $this->monthlyFee;
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

    public function setDdi(?DdiDto $ddi): static
    {
        $this->ddi = $ddi;

        return $this;
    }

    public function getDdi(): ?DdiDto
    {
        return $this->ddi;
    }

    public function setDdiId(?int $id): static
    {
        $value = !is_null($id)
            ? new DdiDto($id)
            : null;

        return $this->setDdi($value);
    }

    public function getDdiId(): ?int
    {
        if ($dto = $this->getDdi()) {
            return $dto->getId();
        }

        return null;
    }

    public function setApprovedBy(?AdministratorDto $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getApprovedBy(): ?AdministratorDto
    {
        return $this->approvedBy;
    }

    public function setApprovedById(?int $id): static
    {
        $value = !is_null($id)
            ? new AdministratorDto($id)
            : null;

        return $this->setApprovedBy($value);
    }

    public function getApprovedById(): ?int
    {
        if ($dto = $this->getApprovedBy()) {
            return $dto->getId();
        }

        return null;
    }
}
