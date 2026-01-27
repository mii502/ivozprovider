<?php

declare(strict_types=1);

/**
 * BYON Service Interface
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonServiceInterface.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

use Ivoz\Provider\Domain\Model\Company\CompanyInterface;

/**
 * Service interface for BYON (Bring Your Own Number) operations
 *
 * BYON allows customers to verify ownership of their phone numbers via SMS OTP
 * and use them as DDIs at €0 cost.
 *
 * Key features:
 * - E.164 format validation
 * - Duplicate detection (platform-wide)
 * - Daily verification limit (10/day per company)
 * - BYON count limit (configurable per company, default 10)
 */
interface ByonServiceInterface
{
    /**
     * Initiate OTP verification for a phone number
     *
     * Validates:
     * - E.164 format
     * - Not already BYON for another company (PERMANENT BLOCK)
     * - Not existing DDI in marketplace inventory
     * - Daily attempt limit not exceeded (10/day)
     * - Company BYON limit not reached
     *
     * @param CompanyInterface $company Company initiating verification
     * @param string $phoneNumber E.164 format phone number (e.g., +34612345678)
     * @return ByonInitiateResult Result with expiry time and remaining limits
     * @throws ByonException On validation or API failure
     */
    public function initiate(CompanyInterface $company, string $phoneNumber): ByonInitiateResult;

    /**
     * Verify OTP code and create BYON DDI
     *
     * Creates DDI with:
     * - isByon = true
     * - type = 'inout'
     * - setupPrice = 0
     * - monthlyPrice = 0
     * - inventoryStatus = 'assigned'
     *
     * @param CompanyInterface $company Company verifying the number
     * @param string $phoneNumber E.164 format phone number
     * @param string $code 6-digit verification code
     * @return ByonVerifyResult Result with created DDI
     * @throws ByonException On verification failure
     */
    public function verify(CompanyInterface $company, string $phoneNumber, string $code): ByonVerifyResult;

    /**
     * Get BYON status for a company
     *
     * @param CompanyInterface $company Company to check
     * @return ByonStatus Status with counts and limits
     */
    public function getStatus(CompanyInterface $company): ByonStatus;

    /**
     * Validate a phone number before sending OTP
     *
     * Performs all validations without sending SMS:
     * - E.164 format validation
     * - Country detection (returns country name and code)
     * - Number availability check
     * - BYON limit check
     *
     * Use this to provide instant feedback to users before they
     * click "Send Code" and consume an SMS credit.
     *
     * @param CompanyInterface $company Company to validate for
     * @param string $phoneNumber E.164 format phone number
     * @return ByonValidateResult Validation result with country info
     */
    public function validate(CompanyInterface $company, string $phoneNumber): ByonValidateResult;

    /**
     * Release a BYON DDI (brand admin only)
     *
     * This removes the BYON flag and allows the number to be verified
     * by another customer if needed.
     *
     * @param int $ddiId DDI ID to release
     * @throws ByonException If DDI is not BYON or not found
     */
    public function release(int $ddiId): void;
}
