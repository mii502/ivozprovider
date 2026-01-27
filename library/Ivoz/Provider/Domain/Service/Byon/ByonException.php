<?php

declare(strict_types=1);

/**
 * BYON Exception
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonException.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

/**
 * Custom exception for BYON operations
 *
 * Error codes:
 * - INVALID_PHONE_FORMAT: Phone number not in E.164 format
 * - DUPLICATE_NUMBER: Number already BYON by another company
 * - INVENTORY_NUMBER: Number exists in marketplace inventory
 * - DAILY_LIMIT_EXCEEDED: 10 daily verification attempts reached
 * - BYON_LIMIT_REACHED: Company max BYON numbers reached
 * - VERIFICATION_FAILED: Somleng API verification failed
 * - INVALID_CODE: OTP code is incorrect
 * - EXPIRED: Verification expired
 * - MAX_ATTEMPTS: Max OTP check attempts reached
 * - NOT_FOUND: No pending verification found
 * - RELEASE_DENIED: Cannot release BYON DDI
 */
class ByonException extends \Exception
{
    // Validation errors
    public const INVALID_PHONE_FORMAT = 'INVALID_PHONE_FORMAT';
    public const DUPLICATE_NUMBER = 'DUPLICATE_NUMBER';
    public const INVENTORY_NUMBER = 'INVENTORY_NUMBER';

    // Limit errors
    public const DAILY_LIMIT_EXCEEDED = 'DAILY_LIMIT_EXCEEDED';
    public const BYON_LIMIT_REACHED = 'BYON_LIMIT_REACHED';
    public const MAX_ATTEMPTS = 'MAX_ATTEMPTS';

    // Verification errors
    public const VERIFICATION_FAILED = 'VERIFICATION_FAILED';
    public const INVALID_CODE = 'INVALID_CODE';
    public const EXPIRED = 'EXPIRED';
    public const NOT_FOUND = 'NOT_FOUND';

    // Release errors
    public const RELEASE_DENIED = 'RELEASE_DENIED';

    private string $errorCode;
    private array $context;

    public function __construct(
        string $errorCode,
        string $message,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Check if this is a client error (4xx) vs server error (5xx)
     */
    public function isClientError(): bool
    {
        return in_array($this->errorCode, [
            self::INVALID_PHONE_FORMAT,
            self::DUPLICATE_NUMBER,
            self::INVENTORY_NUMBER,
            self::DAILY_LIMIT_EXCEEDED,
            self::BYON_LIMIT_REACHED,
            self::INVALID_CODE,
            self::EXPIRED,
            self::MAX_ATTEMPTS,
            self::NOT_FOUND,
            self::RELEASE_DENIED,
        ], true);
    }

    /**
     * Convert to API response format
     */
    public function toApiResponse(): array
    {
        return [
            'success' => false,
            'errorCode' => $this->errorCode,
            'errorMessage' => $this->getMessage(),
        ];
    }

    /**
     * Get HTTP status code for this error
     */
    public function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            self::INVALID_PHONE_FORMAT => 400,       // Bad Request
            self::DUPLICATE_NUMBER => 409,           // Conflict
            self::INVENTORY_NUMBER => 409,           // Conflict
            self::DAILY_LIMIT_EXCEEDED => 429,       // Too Many Requests
            self::BYON_LIMIT_REACHED => 403,         // Forbidden
            self::MAX_ATTEMPTS => 429,               // Too Many Requests
            self::VERIFICATION_FAILED => 503,        // Service Unavailable
            self::INVALID_CODE => 401,               // Unauthorized
            self::EXPIRED => 401,                    // Unauthorized
            self::NOT_FOUND => 404,                  // Not Found
            self::RELEASE_DENIED => 403,             // Forbidden
            default => 400,                          // Bad Request
        };
    }
}
