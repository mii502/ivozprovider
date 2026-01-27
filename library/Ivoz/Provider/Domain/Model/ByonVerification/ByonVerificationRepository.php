<?php

namespace Ivoz\Provider\Domain\Model\ByonVerification;

use Doctrine\Common\Collections\Selectable;
use Doctrine\Persistence\ObjectRepository;

interface ByonVerificationRepository extends ObjectRepository, Selectable
{
    /**
     * Count verification attempts by company today
     *
     * @param int $companyId
     * @param \DateTimeInterface $today Start of today (00:00:00)
     * @return int Number of attempts today
     */
    public function countByCompanyToday(int $companyId, \DateTimeInterface $today): int;

    /**
     * Find pending verification for a phone number
     *
     * @param string $phoneNumber E.164 format
     * @return ByonVerificationInterface|null
     */
    public function findPendingByPhoneNumber(string $phoneNumber): ?ByonVerificationInterface;

    /**
     * Find approved verification for a phone number
     *
     * @param string $phoneNumber E.164 format
     * @return ByonVerificationInterface|null
     */
    public function findApprovedByPhoneNumber(string $phoneNumber): ?ByonVerificationInterface;

    /**
     * Find all verifications by company
     *
     * @param int $companyId
     * @return ByonVerificationInterface[]
     */
    public function findByCompany(int $companyId): array;
}
