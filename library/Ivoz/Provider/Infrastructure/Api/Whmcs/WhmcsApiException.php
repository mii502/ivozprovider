<?php

declare(strict_types=1);

namespace Ivoz\Provider\Infrastructure\Api\Whmcs;

/**
 * Exception thrown when WHMCS API operations fail
 */
class WhmcsApiException extends \RuntimeException
{
    /**
     * @var array
     */
    private array $apiResponse;

    public function __construct(
        string $message,
        array $apiResponse = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->apiResponse = $apiResponse;
    }

    /**
     * Get the raw API response that caused the exception
     */
    public function getApiResponse(): array
    {
        return $this->apiResponse;
    }

    /**
     * Check if this is a connection/transport error
     */
    public function isTransportError(): bool
    {
        return $this->getPrevious() !== null;
    }

    /**
     * Check if this is a retryable error
     */
    public function isRetryable(): bool
    {
        // Transport errors are retryable
        if ($this->isTransportError()) {
            return true;
        }

        // Check for known non-retryable errors
        $message = strtolower($this->getMessage());
        $nonRetryablePatterns = [
            'client id not found',
            'invalid client',
            'authentication failed',
            'invalid api credentials',
            'access denied',
        ];

        foreach ($nonRetryablePatterns as $pattern) {
            if (str_contains($message, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
