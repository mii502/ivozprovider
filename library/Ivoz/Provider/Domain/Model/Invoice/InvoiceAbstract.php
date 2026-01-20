<?php

declare(strict_types=1);

namespace Ivoz\Provider\Domain\Model\Invoice;

use Assert\Assertion;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\Model\ChangelogTrait;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Core\Domain\Model\Helper\DateTimeHelper;
use Ivoz\Provider\Domain\Model\Invoice\Pdf;
use Ivoz\Provider\Domain\Model\InvoiceTemplate\InvoiceTemplateInterface;
use Ivoz\Provider\Domain\Model\Brand\BrandInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\InvoiceNumberSequence\InvoiceNumberSequenceInterface;
use Ivoz\Provider\Domain\Model\InvoiceScheduler\InvoiceSchedulerInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\InvoiceTemplate\InvoiceTemplate;
use Ivoz\Provider\Domain\Model\Brand\Brand;
use Ivoz\Provider\Domain\Model\Company\Company;
use Ivoz\Provider\Domain\Model\InvoiceNumberSequence\InvoiceNumberSequence;
use Ivoz\Provider\Domain\Model\InvoiceScheduler\InvoiceScheduler;
use Ivoz\Provider\Domain\Model\Ddi\Ddi;

/**
* InvoiceAbstract
* @codeCoverageIgnore
*/
abstract class InvoiceAbstract
{
    use ChangelogTrait;

    /**
     * @var ?string
     */
    protected $number = null;

    /**
     * @var ?\DateTime
     */
    protected $inDate = null;

    /**
     * @var ?\DateTime
     */
    protected $outDate = null;

    /**
     * @var ?float
     */
    protected $total = null;

    /**
     * @var ?float
     */
    protected $taxRate = null;

    /**
     * @var ?float
     */
    protected $totalWithTax = null;

    /**
     * @var ?string
     * comment: enum:waiting|processing|created|error
     */
    protected $status = null;

    /**
     * @var ?string
     */
    protected $statusMsg = null;

    /**
     * @var Pdf
     */
    protected $pdf;

    /**
     * @var ?InvoiceTemplateInterface
     */
    protected $invoiceTemplate = null;

    /**
     * @var BrandInterface
     */
    protected $brand;

    /**
     * @var CompanyInterface
     */
    protected $company;

    /**
     * @var ?InvoiceNumberSequenceInterface
     */
    protected $numberSequence = null;

    /**
     * @var ?InvoiceSchedulerInterface
     */
    protected $scheduler = null;

    // WHMCS sync fields

    /**
     * @var ?int
     */
    protected $whmcsInvoiceId = null;

    /**
     * @var ?string
     * comment: enum:not_applicable|pending|synced|failed
     */
    protected $syncStatus = 'not_applicable';

    /**
     * @var ?\DateTime
     */
    protected $whmcsSyncedAt = null;

    /**
     * @var ?\DateTime
     */
    protected $whmcsPaidAt = null;

    /**
     * @var ?string
     */
    protected $syncError = null;

    /**
     * @var int
     */
    protected $syncAttempts = 0;

    /**
     * @var string
     * comment: enum:standard|did_purchase|did_renewal|balance_topup
     */
    protected $invoiceType = 'standard';

    /**
     * How invoice was paid (null=unpaid, 'balance'=Company.balance, 'whmcs'=WHMCS gateway)
     * @var ?string
     * comment: enum:balance|whmcs
     */
    protected $paidVia = null;

    /**
     * @var ?DdiInterface
     */
    protected $ddi = null;

    /**
     * Permanent E.164 phone number reference - survives DDI deletion via UnlinkDdi
     * @var ?string
     */
    protected $ddiE164 = null;

    /**
     * Constructor
     */
    protected function __construct(
        Pdf $pdf
    ) {
        $this->pdf = $pdf;
    }

    abstract public function getId(): null|string|int;

    public function __toString(): string
    {
        return sprintf(
            "%s#%s",
            "Invoice",
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
    public static function createDto($id = null): InvoiceDto
    {
        return new InvoiceDto($id);
    }

    /**
     * @internal use EntityTools instead
     * @param null|InvoiceInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?InvoiceDto
    {
        if (!$entity) {
            return null;
        }

        Assertion::isInstanceOf($entity, InvoiceInterface::class);

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
     * @param InvoiceDto $dto
     */
    public static function fromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, InvoiceDto::class);
        $brand = $dto->getBrand();
        Assertion::notNull($brand, 'getBrand value is null, but non null value was expected.');
        $company = $dto->getCompany();
        Assertion::notNull($company, 'getCompany value is null, but non null value was expected.');

        $pdf = new Pdf(
            $dto->getPdfFileSize(),
            $dto->getPdfMimeType(),
            $dto->getPdfBaseName()
        );

        $self = new static(
            $pdf
        );

        $self
            ->setNumber($dto->getNumber())
            ->setInDate($dto->getInDate())
            ->setOutDate($dto->getOutDate())
            ->setTotal($dto->getTotal())
            ->setTaxRate($dto->getTaxRate())
            ->setTotalWithTax($dto->getTotalWithTax())
            ->setStatus($dto->getStatus())
            ->setStatusMsg($dto->getStatusMsg())
            ->setInvoiceTemplate($fkTransformer->transform($dto->getInvoiceTemplate()))
            ->setBrand($fkTransformer->transform($brand))
            ->setCompany($fkTransformer->transform($company))
            ->setNumberSequence($fkTransformer->transform($dto->getNumberSequence()))
            ->setScheduler($fkTransformer->transform($dto->getScheduler()))
            // WHMCS sync fields
            ->setWhmcsInvoiceId($dto->getWhmcsInvoiceId())
            ->setSyncStatus($dto->getSyncStatus())
            ->setWhmcsSyncedAt($dto->getWhmcsSyncedAt())
            ->setWhmcsPaidAt($dto->getWhmcsPaidAt())
            ->setSyncError($dto->getSyncError())
            ->setSyncAttempts($dto->getSyncAttempts() ?? 0)
            ->setInvoiceType($dto->getInvoiceType() ?? 'standard')
            ->setPaidVia($dto->getPaidVia())
            ->setDdi($fkTransformer->transform($dto->getDdi()))
            ->setDdiE164($dto->getDdiE164());

        $self->initChangelog();

        return $self;
    }

    /**
     * @internal use EntityTools instead
     * @param InvoiceDto $dto
     */
    public function updateFromDto(
        DataTransferObjectInterface $dto,
        ForeignKeyTransformerInterface $fkTransformer
    ): static {
        Assertion::isInstanceOf($dto, InvoiceDto::class);

        $brand = $dto->getBrand();
        Assertion::notNull($brand, 'getBrand value is null, but non null value was expected.');
        $company = $dto->getCompany();
        Assertion::notNull($company, 'getCompany value is null, but non null value was expected.');

        $pdf = new Pdf(
            $dto->getPdfFileSize(),
            $dto->getPdfMimeType(),
            $dto->getPdfBaseName()
        );

        $this
            ->setNumber($dto->getNumber())
            ->setInDate($dto->getInDate())
            ->setOutDate($dto->getOutDate())
            ->setTotal($dto->getTotal())
            ->setTaxRate($dto->getTaxRate())
            ->setTotalWithTax($dto->getTotalWithTax())
            ->setStatus($dto->getStatus())
            ->setStatusMsg($dto->getStatusMsg())
            ->setPdf($pdf)
            ->setInvoiceTemplate($fkTransformer->transform($dto->getInvoiceTemplate()))
            ->setBrand($fkTransformer->transform($brand))
            ->setCompany($fkTransformer->transform($company))
            ->setNumberSequence($fkTransformer->transform($dto->getNumberSequence()))
            ->setScheduler($fkTransformer->transform($dto->getScheduler()))
            // WHMCS sync fields
            ->setWhmcsInvoiceId($dto->getWhmcsInvoiceId())
            ->setSyncStatus($dto->getSyncStatus())
            ->setWhmcsSyncedAt($dto->getWhmcsSyncedAt())
            ->setWhmcsPaidAt($dto->getWhmcsPaidAt())
            ->setSyncError($dto->getSyncError())
            ->setSyncAttempts($dto->getSyncAttempts() ?? 0)
            ->setInvoiceType($dto->getInvoiceType() ?? 'standard')
            ->setPaidVia($dto->getPaidVia())
            ->setDdi($fkTransformer->transform($dto->getDdi()))
            ->setDdiE164($dto->getDdiE164());

        return $this;
    }

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): InvoiceDto
    {
        return self::createDto()
            ->setNumber(self::getNumber())
            ->setInDate(self::getInDate())
            ->setOutDate(self::getOutDate())
            ->setTotal(self::getTotal())
            ->setTaxRate(self::getTaxRate())
            ->setTotalWithTax(self::getTotalWithTax())
            ->setStatus(self::getStatus())
            ->setStatusMsg(self::getStatusMsg())
            ->setPdfFileSize(self::getPdf()->getFileSize())
            ->setPdfMimeType(self::getPdf()->getMimeType())
            ->setPdfBaseName(self::getPdf()->getBaseName())
            ->setInvoiceTemplate(InvoiceTemplate::entityToDto(self::getInvoiceTemplate(), $depth))
            ->setBrand(Brand::entityToDto(self::getBrand(), $depth))
            ->setCompany(Company::entityToDto(self::getCompany(), $depth))
            ->setNumberSequence(InvoiceNumberSequence::entityToDto(self::getNumberSequence(), $depth))
            ->setScheduler(InvoiceScheduler::entityToDto(self::getScheduler(), $depth))
            // WHMCS sync fields
            ->setWhmcsInvoiceId(self::getWhmcsInvoiceId())
            ->setSyncStatus(self::getSyncStatus())
            ->setWhmcsSyncedAt(self::getWhmcsSyncedAt())
            ->setWhmcsPaidAt(self::getWhmcsPaidAt())
            ->setSyncError(self::getSyncError())
            ->setSyncAttempts(self::getSyncAttempts())
            ->setInvoiceType(self::getInvoiceType())
            ->setPaidVia(self::getPaidVia())
            ->setDdi(Ddi::entityToDto(self::getDdi(), $depth))
            ->setDdiE164(self::getDdiE164());
    }

    /**
     * @return array<string, mixed>
     */
    protected function __toArray(): array
    {
        return [
            'number' => self::getNumber(),
            'inDate' => self::getInDate(),
            'outDate' => self::getOutDate(),
            'total' => self::getTotal(),
            'taxRate' => self::getTaxRate(),
            'totalWithTax' => self::getTotalWithTax(),
            'status' => self::getStatus(),
            'statusMsg' => self::getStatusMsg(),
            'pdfFileSize' => self::getPdf()->getFileSize(),
            'pdfMimeType' => self::getPdf()->getMimeType(),
            'pdfBaseName' => self::getPdf()->getBaseName(),
            'invoiceTemplateId' => self::getInvoiceTemplate()?->getId(),
            'brandId' => self::getBrand()->getId(),
            'companyId' => self::getCompany()->getId(),
            'numberSequenceId' => self::getNumberSequence()?->getId(),
            'schedulerId' => self::getScheduler()?->getId(),
            // WHMCS sync fields
            'whmcsInvoiceId' => self::getWhmcsInvoiceId(),
            'syncStatus' => self::getSyncStatus(),
            'whmcsSyncedAt' => self::getWhmcsSyncedAt(),
            'whmcsPaidAt' => self::getWhmcsPaidAt(),
            'syncError' => self::getSyncError(),
            'syncAttempts' => self::getSyncAttempts(),
            'invoiceType' => self::getInvoiceType(),
            'paidVia' => self::getPaidVia(),
            'ddiId' => self::getDdi()?->getId(),
            'ddiE164' => self::getDdiE164()
        ];
    }

    protected function setNumber(?string $number = null): static
    {
        if (!is_null($number)) {
            Assertion::maxLength($number, 30, 'number value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        }

        $this->number = $number;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    protected function setInDate(string|\DateTimeInterface|null $inDate = null): static
    {
        if (!is_null($inDate)) {

            /** @var ?\DateTime */
            $inDate = DateTimeHelper::createOrFix(
                $inDate,
                null
            );

            if ($this->isInitialized() && $this->inDate == $inDate) {
                return $this;
            }
        }

        $this->inDate = $inDate;

        return $this;
    }

    public function getInDate(): ?\DateTime
    {
        return !is_null($this->inDate) ? clone $this->inDate : null;
    }

    protected function setOutDate(string|\DateTimeInterface|null $outDate = null): static
    {
        if (!is_null($outDate)) {

            /** @var ?\DateTime */
            $outDate = DateTimeHelper::createOrFix(
                $outDate,
                null
            );

            if ($this->isInitialized() && $this->outDate == $outDate) {
                return $this;
            }
        }

        $this->outDate = $outDate;

        return $this;
    }

    public function getOutDate(): ?\DateTime
    {
        return !is_null($this->outDate) ? clone $this->outDate : null;
    }

    protected function setTotal(?float $total = null): static
    {
        if (!is_null($total)) {
            $total = (float) $total;
        }

        $this->total = $total;

        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    protected function setTaxRate(?float $taxRate = null): static
    {
        if (!is_null($taxRate)) {
            $taxRate = (float) $taxRate;
        }

        $this->taxRate = $taxRate;

        return $this;
    }

    public function getTaxRate(): ?float
    {
        return $this->taxRate;
    }

    protected function setTotalWithTax(?float $totalWithTax = null): static
    {
        if (!is_null($totalWithTax)) {
            $totalWithTax = (float) $totalWithTax;
        }

        $this->totalWithTax = $totalWithTax;

        return $this;
    }

    public function getTotalWithTax(): ?float
    {
        return $this->totalWithTax;
    }

    protected function setStatus(?string $status = null): static
    {
        if (!is_null($status)) {
            Assertion::maxLength($status, 25, 'status value "%s" is too long, it should have no more than %d characters, but has %d characters.');
            Assertion::choice(
                $status,
                [
                    InvoiceInterface::STATUS_WAITING,
                    InvoiceInterface::STATUS_PROCESSING,
                    InvoiceInterface::STATUS_CREATED,
                    InvoiceInterface::STATUS_ERROR,
                ],
                'statusvalue "%s" is not an element of the valid values: %s'
            );
        }

        $this->status = $status;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    protected function setStatusMsg(?string $statusMsg = null): static
    {
        if (!is_null($statusMsg)) {
            Assertion::maxLength($statusMsg, 140, 'statusMsg value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        }

        $this->statusMsg = $statusMsg;

        return $this;
    }

    public function getStatusMsg(): ?string
    {
        return $this->statusMsg;
    }

    public function getPdf(): Pdf
    {
        return $this->pdf;
    }

    protected function setPdf(Pdf $pdf): static
    {
        $isEqual = $this->pdf->equals($pdf);
        if ($isEqual) {
            return $this;
        }

        $this->pdf = $pdf;
        return $this;
    }

    protected function setInvoiceTemplate(?InvoiceTemplateInterface $invoiceTemplate = null): static
    {
        $this->invoiceTemplate = $invoiceTemplate;

        return $this;
    }

    public function getInvoiceTemplate(): ?InvoiceTemplateInterface
    {
        return $this->invoiceTemplate;
    }

    protected function setBrand(BrandInterface $brand): static
    {
        $this->brand = $brand;

        return $this;
    }

    public function getBrand(): BrandInterface
    {
        return $this->brand;
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

    protected function setNumberSequence(?InvoiceNumberSequenceInterface $numberSequence = null): static
    {
        $this->numberSequence = $numberSequence;

        return $this;
    }

    public function getNumberSequence(): ?InvoiceNumberSequenceInterface
    {
        return $this->numberSequence;
    }

    protected function setScheduler(?InvoiceSchedulerInterface $scheduler = null): static
    {
        $this->scheduler = $scheduler;

        return $this;
    }

    public function getScheduler(): ?InvoiceSchedulerInterface
    {
        return $this->scheduler;
    }

    // WHMCS sync field getters and setters

    protected function setWhmcsInvoiceId(?int $whmcsInvoiceId = null): static
    {
        $this->whmcsInvoiceId = $whmcsInvoiceId;

        return $this;
    }

    public function getWhmcsInvoiceId(): ?int
    {
        return $this->whmcsInvoiceId;
    }

    protected function setSyncStatus(?string $syncStatus = null): static
    {
        if (!is_null($syncStatus)) {
            Assertion::choice(
                $syncStatus,
                [
                    InvoiceInterface::SYNC_STATUS_NOT_APPLICABLE,
                    InvoiceInterface::SYNC_STATUS_PENDING,
                    InvoiceInterface::SYNC_STATUS_SYNCED,
                    InvoiceInterface::SYNC_STATUS_FAILED,
                ],
                'syncStatus value "%s" is not an element of the valid values: %s'
            );
        }

        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getSyncStatus(): ?string
    {
        return $this->syncStatus;
    }

    protected function setWhmcsSyncedAt(string|\DateTimeInterface|null $whmcsSyncedAt = null): static
    {
        if (!is_null($whmcsSyncedAt)) {
            /** @var ?\DateTime */
            $whmcsSyncedAt = DateTimeHelper::createOrFix(
                $whmcsSyncedAt,
                null
            );
        }

        $this->whmcsSyncedAt = $whmcsSyncedAt;

        return $this;
    }

    public function getWhmcsSyncedAt(): ?\DateTime
    {
        return !is_null($this->whmcsSyncedAt) ? clone $this->whmcsSyncedAt : null;
    }

    protected function setWhmcsPaidAt(string|\DateTimeInterface|null $whmcsPaidAt = null): static
    {
        if (!is_null($whmcsPaidAt)) {
            /** @var ?\DateTime */
            $whmcsPaidAt = DateTimeHelper::createOrFix(
                $whmcsPaidAt,
                null
            );
        }

        $this->whmcsPaidAt = $whmcsPaidAt;

        return $this;
    }

    public function getWhmcsPaidAt(): ?\DateTime
    {
        return !is_null($this->whmcsPaidAt) ? clone $this->whmcsPaidAt : null;
    }

    protected function setSyncError(?string $syncError = null): static
    {
        $this->syncError = $syncError;

        return $this;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    protected function setSyncAttempts(int $syncAttempts): static
    {
        $this->syncAttempts = $syncAttempts;

        return $this;
    }

    public function getSyncAttempts(): int
    {
        return $this->syncAttempts;
    }

    protected function setInvoiceType(string $invoiceType): static
    {
        Assertion::choice(
            $invoiceType,
            [
                InvoiceInterface::INVOICE_TYPE_STANDARD,
                InvoiceInterface::INVOICE_TYPE_DID_PURCHASE,
                InvoiceInterface::INVOICE_TYPE_DID_RENEWAL,
                InvoiceInterface::INVOICE_TYPE_BALANCE_TOPUP,
            ],
            'invoiceType value "%s" is not an element of the valid values: %s'
        );

        $this->invoiceType = $invoiceType;

        return $this;
    }

    public function getInvoiceType(): string
    {
        return $this->invoiceType;
    }

    protected function setPaidVia(?string $paidVia = null): static
    {
        if (!is_null($paidVia)) {
            Assertion::choice(
                $paidVia,
                [
                    InvoiceInterface::PAID_VIA_BALANCE,
                    InvoiceInterface::PAID_VIA_WHMCS,
                ],
                'paidVia value "%s" is not an element of the valid values: %s'
            );
        }

        $this->paidVia = $paidVia;

        return $this;
    }

    public function getPaidVia(): ?string
    {
        return $this->paidVia;
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

    protected function setDdiE164(?string $ddiE164 = null): static
    {
        if (!is_null($ddiE164)) {
            Assertion::maxLength($ddiE164, 25, 'ddiE164 value "%s" is too long, it should have no more than %d characters, but has %d characters.');
        }

        $this->ddiE164 = $ddiE164;

        return $this;
    }

    public function getDdiE164(): ?string
    {
        return $this->ddiE164;
    }
}
