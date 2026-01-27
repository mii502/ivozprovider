<?php

declare(strict_types=1);

/**
 * BYON Service
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonService.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Core\Domain\Service\EntityTools;
use Ivoz\Provider\Domain\Model\Company\CompanyInterface;
use Ivoz\Provider\Domain\Model\Ddi\Ddi;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Model\ByonVerification\ByonVerification;
use Ivoz\Provider\Domain\Model\ByonVerification\ByonVerificationInterface;
use Ivoz\Provider\Domain\Model\ByonVerification\ByonVerificationRepository;
use Ivoz\Provider\Domain\Model\Country\CountryRepository;
use Psr\Log\LoggerInterface;

/**
 * BYON (Bring Your Own Number) Service Implementation
 */
class ByonService implements ByonServiceInterface
{
    // Configuration
    private const DAILY_LIMIT = 10;
    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_OTP_ATTEMPTS = 3;

    // E.164 format regex
    private const E164_PATTERN = '/^\+[1-9]\d{1,14}$/';

    public function __construct(
        private readonly EntityTools $entityTools,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly SomlengVerifyClient $somlengClient,
        private readonly DdiRepository $ddiRepository,
        private readonly ByonVerificationRepository $byonVerificationRepository,
        private readonly CountryRepository $countryRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function initiate(CompanyInterface $company, string $phoneNumber): ByonInitiateResult
    {
        $phoneNumber = trim($phoneNumber);

        $this->logger->info('BYON: Initiate verification', [
            'companyId' => $company->getId(),
            'phoneNumber' => $this->maskPhone($phoneNumber),
        ]);

        // Validation 1: E.164 format
        $this->validateE164Format($phoneNumber);

        // Validation 2: Check for duplicates (platform-wide)
        $this->checkNumberAvailability($phoneNumber, $company);

        // Validation 3: Daily limit
        $this->checkDailyLimit($company);

        // Validation 4: BYON count limit
        $this->checkByonLimit($company);

        // Create verification record
        $verification = $this->createVerificationRecord($company, $phoneNumber);

        // Send OTP via Somleng
        $result = $this->somlengClient->sendVerification($phoneNumber);

        if (!$result['success']) {
            // Update verification as failed
            $this->updateVerificationStatus(
                $verification,
                ByonVerificationInterface::STATUS_FAILED
            );

            $this->logger->warning('BYON: Somleng verification failed', [
                'companyId' => $company->getId(),
                'phoneNumber' => $this->maskPhone($phoneNumber),
                'error' => $result['error'],
            ]);

            throw new ByonException(
                ByonException::VERIFICATION_FAILED,
                $result['error'] ?? 'Failed to send verification code'
            );
        }

        // Update verification with Somleng SID
        $this->updateVerificationSid($verification, $result['verificationSid'] ?? null);

        $this->logger->info('BYON: Verification initiated successfully', [
            'companyId' => $company->getId(),
            'phoneNumber' => $this->maskPhone($phoneNumber),
            'verificationId' => $verification->getId(),
        ]);

        // Get status for response
        $status = $this->getStatus($company);

        return ByonInitiateResult::success(
            expiresIn: self::OTP_EXPIRY_MINUTES * 60,
            dailyAttemptsRemaining: $status->getDailyAttemptsRemaining() - 1, // Just used one
            byonCount: $status->getByonCount(),
            byonLimit: $status->getByonLimit()
        );
    }

    /**
     * @inheritDoc
     */
    public function verify(CompanyInterface $company, string $phoneNumber, string $code): ByonVerifyResult
    {
        $phoneNumber = trim($phoneNumber);
        $code = trim($code);

        $this->logger->info('BYON: Verify code', [
            'companyId' => $company->getId(),
            'phoneNumber' => $this->maskPhone($phoneNumber),
        ]);

        // Find pending verification
        $verification = $this->byonVerificationRepository->findPendingByPhoneNumber($phoneNumber);

        if ($verification === null) {
            throw new ByonException(
                ByonException::NOT_FOUND,
                'No pending verification found for this number'
            );
        }

        // Check if verification belongs to this company
        if ($verification->getCompany()->getId() !== $company->getId()) {
            throw new ByonException(
                ByonException::NOT_FOUND,
                'No pending verification found for this number'
            );
        }

        // Check if expired
        if ($verification->isExpired()) {
            $this->updateVerificationStatus(
                $verification,
                ByonVerificationInterface::STATUS_EXPIRED
            );

            throw new ByonException(
                ByonException::EXPIRED,
                'Verification code expired. Please request a new one.'
            );
        }

        // Check max attempts
        if (!$verification->canRetry()) {
            $this->updateVerificationStatus(
                $verification,
                ByonVerificationInterface::STATUS_FAILED
            );

            throw new ByonException(
                ByonException::MAX_ATTEMPTS,
                'Maximum verification attempts reached'
            );
        }

        // Increment attempts
        $this->incrementAttempts($verification);

        // Verify code with Somleng
        $result = $this->somlengClient->checkVerification($phoneNumber, $code);

        if (!$result['success'] || !$result['approved']) {
            $remainingAttempts = self::MAX_OTP_ATTEMPTS - $verification->getAttempts() - 1;

            $this->logger->warning('BYON: Invalid verification code', [
                'companyId' => $company->getId(),
                'phoneNumber' => $this->maskPhone($phoneNumber),
                'remainingAttempts' => $remainingAttempts,
            ]);

            if ($remainingAttempts <= 0) {
                $this->updateVerificationStatus(
                    $verification,
                    ByonVerificationInterface::STATUS_FAILED
                );
            }

            throw new ByonException(
                ByonException::INVALID_CODE,
                sprintf('Invalid verification code. %d attempts remaining.', max(0, $remainingAttempts)),
                ['remainingAttempts' => max(0, $remainingAttempts)]
            );
        }

        // Mark verification as approved
        $this->markVerificationApproved($verification);

        // Create BYON DDI
        $ddi = $this->createByonDdi($company, $phoneNumber, $verification);

        $this->logger->info('BYON: Number verified and DDI created', [
            'companyId' => $company->getId(),
            'phoneNumber' => $this->maskPhone($phoneNumber),
            'ddiId' => $ddi->getId(),
        ]);

        return ByonVerifyResult::success($ddi);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(CompanyInterface $company): ByonStatus
    {
        $companyId = $company->getId();

        // Count BYON DDIs for this company
        $byonCount = $this->countByonDdis($companyId);

        // Get BYON limit from company
        $byonLimit = $company->getByonMaxNumbers();

        // Count today's verification attempts
        $today = new \DateTime('today', new \DateTimeZone('UTC'));
        $dailyAttemptsUsed = $this->byonVerificationRepository->countByCompanyToday(
            $companyId,
            $today
        );

        return new ByonStatus(
            byonCount: $byonCount,
            byonLimit: $byonLimit,
            dailyAttemptsUsed: $dailyAttemptsUsed,
            dailyAttemptsLimit: self::DAILY_LIMIT
        );
    }

    /**
     * @inheritDoc
     */
    public function release(int $ddiId): void
    {
        $ddi = $this->ddiRepository->find($ddiId);

        if ($ddi === null) {
            throw new ByonException(
                ByonException::NOT_FOUND,
                'DDI not found'
            );
        }

        if (!$ddi->getIsByon()) {
            throw new ByonException(
                ByonException::RELEASE_DENIED,
                'This DDI is not a BYON number'
            );
        }

        $this->logger->info('BYON: Releasing DDI', [
            'ddiId' => $ddiId,
            'phoneNumber' => $this->maskPhone($ddi->getDdie164()),
        ]);

        // Delete the DDI (since it's customer-provided, we just remove it)
        $this->entityTools->remove($ddi);
        $this->entityTools->dispatchQueuedOperations();

        $this->logger->info('BYON: DDI released', [
            'ddiId' => $ddiId,
        ]);
    }

    // ==================== Private Helper Methods ====================

    /**
     * Validate E.164 format
     */
    private function validateE164Format(string $phoneNumber): void
    {
        if (!preg_match(self::E164_PATTERN, $phoneNumber)) {
            throw new ByonException(
                ByonException::INVALID_PHONE_FORMAT,
                'Phone number must be in E.164 format (e.g., +34612345678)'
            );
        }
    }

    /**
     * Check if number is available for BYON
     */
    private function checkNumberAvailability(string $phoneNumber, CompanyInterface $company): void
    {
        // Check if DDI exists with this E.164 number
        $existingDdi = $this->ddiRepository->findOneByDdiE164($phoneNumber);

        if ($existingDdi !== null) {
            if ($existingDdi->getIsByon()) {
                // BYON exists
                $existingCompany = $existingDdi->getCompany();
                if ($existingCompany !== null && $existingCompany->getId() !== $company->getId()) {
                    // Different company owns it - BLOCKED PERMANENTLY
                    throw new ByonException(
                        ByonException::DUPLICATE_NUMBER,
                        'This number is already registered'
                    );
                }
                // Same company - they might be re-verifying, allow
            } else {
                // Marketplace DDI exists - cannot BYON a number in inventory
                throw new ByonException(
                    ByonException::INVENTORY_NUMBER,
                    'This number is part of our inventory'
                );
            }
        }
    }

    /**
     * Check daily verification limit
     */
    private function checkDailyLimit(CompanyInterface $company): void
    {
        $today = new \DateTime('today', new \DateTimeZone('UTC'));
        $count = $this->byonVerificationRepository->countByCompanyToday(
            $company->getId(),
            $today
        );

        if ($count >= self::DAILY_LIMIT) {
            throw new ByonException(
                ByonException::DAILY_LIMIT_EXCEEDED,
                'Daily verification limit reached. Try again tomorrow.'
            );
        }
    }

    /**
     * Check BYON count limit
     */
    private function checkByonLimit(CompanyInterface $company): void
    {
        $byonCount = $this->countByonDdis($company->getId());
        $byonLimit = $company->getByonMaxNumbers();

        if ($byonCount >= $byonLimit) {
            throw new ByonException(
                ByonException::BYON_LIMIT_REACHED,
                sprintf('Maximum BYON numbers reached (%d)', $byonLimit)
            );
        }
    }

    /**
     * Count BYON DDIs for a company
     */
    private function countByonDdis(int $companyId): int
    {
        // Use QueryBuilder to count BYON DDIs
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('COUNT(d.id)')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->where('d.company = :companyId')
            ->andWhere('d.isByon = :isByon')
            ->setParameter('companyId', $companyId)
            ->setParameter('isByon', true);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Create verification record
     */
    private function createVerificationRecord(
        CompanyInterface $company,
        string $phoneNumber
    ): ByonVerificationInterface {
        $expiresAt = (new \DateTime())->modify('+' . self::OTP_EXPIRY_MINUTES . ' minutes');

        $verificationDto = ByonVerification::createDto();
        $verificationDto
            ->setCompanyId($company->getId())
            ->setPhoneNumber($phoneNumber)
            ->setStatus(ByonVerificationInterface::STATUS_PENDING)
            ->setAttempts(0)
            ->setCreatedAt(new \DateTime())
            ->setExpiresAt($expiresAt);

        /** @var ByonVerificationInterface $verification */
        $verification = $this->entityTools->persistDto($verificationDto, null, true);

        return $verification;
    }

    /**
     * Update verification status
     */
    private function updateVerificationStatus(
        ByonVerificationInterface $verification,
        string $status
    ): void {
        $verificationDto = $verification->toDto();
        $verificationDto->setStatus($status);
        $this->entityTools->persistDto($verificationDto, $verification, true);
    }

    /**
     * Update verification with Somleng SID
     */
    private function updateVerificationSid(
        ByonVerificationInterface $verification,
        ?string $sid
    ): void {
        $verificationDto = $verification->toDto();
        $verificationDto->setVerificationSid($sid);
        $this->entityTools->persistDto($verificationDto, $verification, true);
    }

    /**
     * Increment verification attempts
     */
    private function incrementAttempts(ByonVerificationInterface $verification): void
    {
        $verificationDto = $verification->toDto();
        $verificationDto->setAttempts($verification->getAttempts() + 1);
        $this->entityTools->persistDto($verificationDto, $verification, true);
    }

    /**
     * Mark verification as approved
     */
    private function markVerificationApproved(ByonVerificationInterface $verification): void
    {
        $verificationDto = $verification->toDto();
        $verificationDto
            ->setStatus(ByonVerificationInterface::STATUS_APPROVED)
            ->setVerifiedAt(new \DateTime());
        $this->entityTools->persistDto($verificationDto, $verification, true);
    }

    /**
     * Create BYON DDI
     */
    private function createByonDdi(
        CompanyInterface $company,
        string $phoneNumber,
        ByonVerificationInterface $verification
    ): DdiInterface {
        // Extract country code from E.164 number
        $country = $this->detectCountry($phoneNumber);

        // Get the DDI part (number without country prefix)
        $ddiNumber = $phoneNumber;
        if ($country !== null) {
            $countryPrefix = '+' . $country->getCountryCode();
            if (str_starts_with($phoneNumber, $countryPrefix)) {
                $ddiNumber = substr($phoneNumber, strlen($countryPrefix));
            }
        }

        $ddiDto = Ddi::createDto();
        $ddiDto
            ->setDdi($ddiNumber)
            ->setDdie164($phoneNumber)
            ->setType('inout')
            ->setRecordCalls('none')
            ->setDescription('BYON: ' . $phoneNumber)
            ->setCompanyId($company->getId())
            ->setBrandId($company->getBrand()->getId())
            ->setCountryId($country?->getId())
            ->setSetupPrice(0.0)
            ->setMonthlyPrice(0.0)
            ->setInventoryStatus(DdiInterface::INVENTORYSTATUS_ASSIGNED)
            ->setAssignedAt(new \DateTime())
            ->setIsByon(true)
            ->setByonVerificationId($verification->getId());

        /** @var DdiInterface $ddi */
        $ddi = $this->entityTools->persistDto($ddiDto, null, true);

        return $ddi;
    }

    /**
     * Detect country from E.164 number
     *
     * @return \Ivoz\Provider\Domain\Model\Country\CountryInterface|null
     */
    private function detectCountry(string $phoneNumber)
    {
        // Remove leading +
        $number = ltrim($phoneNumber, '+');

        // Try country codes from longest to shortest (1-4 digits)
        for ($len = 4; $len >= 1; $len--) {
            $prefix = substr($number, 0, $len);
            $country = $this->countryRepository->findOneBy(['countryCode' => $prefix]);
            if ($country !== null) {
                return $country;
            }
        }

        return null;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) {
            return $phone;
        }
        return str_repeat('*', $len - 4) . substr($phone, -4);
    }
}
