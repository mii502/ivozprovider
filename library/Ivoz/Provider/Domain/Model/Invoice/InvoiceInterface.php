<?php

namespace Ivoz\Provider\Domain\Model\Invoice;

use Ivoz\Core\Domain\Model\LoggableEntityInterface;
use Ivoz\Core\Domain\Service\FileContainerInterface;
use Ivoz\Core\Domain\Model\EntityInterface;
use Ivoz\Core\Domain\DataTransferObjectInterface;
use Ivoz\Core\Domain\ForeignKeyTransformerInterface;
use Ivoz\Provider\Domain\Model\InvoiceTemplate\InvoiceTemplateInterface;
use Ivoz\Provider\Domain\Model\Brand\BrandInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\InvoiceNumberSequence\InvoiceNumberSequenceInterface;
use Ivoz\Provider\Domain\Model\InvoiceScheduler\InvoiceSchedulerInterface;
use Ivoz\Provider\Domain\Model\FixedCostsRelInvoice\FixedCostsRelInvoiceInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Ivoz\Core\Domain\Service\TempFile;

/**
* InvoiceInterface
*/
interface InvoiceInterface extends LoggableEntityInterface, FileContainerInterface
{
    // Invoice generation status
    public const STATUS_WAITING = 'waiting';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_CREATED = 'created';

    public const STATUS_ERROR = 'error';

    // Invoice types (for WHMCS integration)
    public const INVOICE_TYPE_STANDARD = 'standard';

    public const INVOICE_TYPE_DID_PURCHASE = 'did_purchase';

    public const INVOICE_TYPE_DID_RENEWAL = 'did_renewal';

    public const INVOICE_TYPE_BALANCE_TOPUP = 'balance_topup';

    // WHMCS sync status
    public const SYNC_STATUS_NOT_APPLICABLE = 'not_applicable';

    public const SYNC_STATUS_PENDING = 'pending';

    public const SYNC_STATUS_SYNCED = 'synced';

    public const SYNC_STATUS_FAILED = 'failed';

    /**
     * @codeCoverageIgnore
     * @return array<string, mixed>
     */
    public function getChangeSet(): array;

    /**
     * @return array
     */
    public function getFileObjects(?int $filter = null): array;

    /**
     * Get id
     * @codeCoverageIgnore
     * @return integer
     */
    public function getId(): ?int;

    /**
     * @return bool
     */
    public function isWaiting(): bool;

    public function setNumber(?string $number = null): static;

    public function mustRunInvoicer(): bool;

    public function mustCheckValidity(): bool;

    /**
     * @param int | null $id
     */
    public static function createDto($id = null): InvoiceDto;

    /**
     * @internal use EntityTools instead
     * @param null|InvoiceInterface $entity
     */
    public static function entityToDto(?EntityInterface $entity, int $depth = 0): ?InvoiceDto;

    /**
     * Factory method
     * @internal use EntityTools instead
     * @param InvoiceDto $dto
     */
    public static function fromDto(DataTransferObjectInterface $dto, ForeignKeyTransformerInterface $fkTransformer): static;

    /**
     * @internal use EntityTools instead
     */
    public function toDto(int $depth = 0): InvoiceDto;

    public function getNumber(): ?string;

    public function getInDate(): ?\DateTime;

    public function getOutDate(): ?\DateTime;

    public function getTotal(): ?float;

    public function getTaxRate(): ?float;

    public function getTotalWithTax(): ?float;

    public function getStatus(): ?string;

    public function getStatusMsg(): ?string;

    public function getPdf(): Pdf;

    public function getInvoiceTemplate(): ?InvoiceTemplateInterface;

    public function getBrand(): BrandInterface;

    public function getCompany(): CompanyInterface;

    public function getNumberSequence(): ?InvoiceNumberSequenceInterface;

    public function getScheduler(): ?InvoiceSchedulerInterface;

    public function addRelFixedCost(FixedCostsRelInvoiceInterface $relFixedCost): InvoiceInterface;

    public function removeRelFixedCost(FixedCostsRelInvoiceInterface $relFixedCost): InvoiceInterface;

    /**
     * @param Collection<array-key, FixedCostsRelInvoiceInterface> $relFixedCosts
     */
    public function replaceRelFixedCosts(Collection $relFixedCosts): InvoiceInterface;

    /**
     * @return array<array-key, FixedCostsRelInvoiceInterface>
     */
    public function getRelFixedCosts(?Criteria $criteria = null): array;

    /**
     * @return void
     */
    public function addTmpFile(string $fldName, TempFile $file);

    /**
     * @throws \Exception
     * @return void
     */
    public function removeTmpFile(TempFile $file);

    /**
     * @return \Ivoz\Core\Domain\Service\TempFile[]
     */
    public function getTempFiles();

    /**
     * @param string $fldName
     * @return null | \Ivoz\Core\Domain\Service\TempFile
     */
    public function getTempFileByFieldName($fldName);

    // WHMCS sync fields
    public function getWhmcsInvoiceId(): ?int;

    public function getSyncStatus(): ?string;

    public function getWhmcsSyncedAt(): ?\DateTime;

    public function getWhmcsPaidAt(): ?\DateTime;

    public function getSyncError(): ?string;

    public function getSyncAttempts(): int;

    public function getInvoiceType(): string;

    public function getDdi(): ?DdiInterface;

    /**
     * Get permanent E.164 phone number reference - survives DDI deletion via UnlinkDdi
     */
    public function getDdiE164(): ?string;

    /**
     * Check if invoice should be synced to WHMCS
     */
    public function shouldSyncToWhmcs(): bool;

    /**
     * Check if invoice has been paid via WHMCS
     */
    public function isPaidViaWhmcs(): bool;

    /**
     * Mark invoice as synced to WHMCS
     */
    public function markAsSynced(int $whmcsInvoiceId): static;

    /**
     * Mark invoice as paid from WHMCS webhook
     */
    public function markAsPaid(): static;

    /**
     * Mark sync as failed with error message
     */
    public function markSyncFailed(string $error): static;

    /**
     * Increment sync attempts for retry logic
     */
    public function incrementSyncAttempts(): static;
}
