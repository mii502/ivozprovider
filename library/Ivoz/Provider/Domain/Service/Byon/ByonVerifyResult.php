<?php

declare(strict_types=1);

/**
 * BYON Verify Result DTO
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/ByonVerifyResult.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;

/**
 * Result object for BYON verification (code check)
 */
final class ByonVerifyResult
{
    public function __construct(
        private bool $success,
        private ?DdiInterface $ddi = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getDdi(): ?DdiInterface
    {
        return $this->ddi;
    }

    /**
     * Convert to API response format
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->ddi !== null) {
            $result['ddi'] = [
                'id' => $this->ddi->getId(),
                'number' => $this->ddi->getDdiE164(),
                'isByon' => $this->ddi->getIsByon(),
            ];
        }

        return $result;
    }

    /**
     * Create success result
     */
    public static function success(DdiInterface $ddi): self
    {
        return new self(
            success: true,
            ddi: $ddi
        );
    }
}
