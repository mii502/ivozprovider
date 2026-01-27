<?php

namespace Ivoz\Provider\Domain\Model\ByonVerification;

class ByonVerificationDto extends ByonVerificationDtoAbstract
{
    /**
     * @inheritdoc
     * @codeCoverageIgnore
     */
    public static function getPropertyMap(string $context = '', string $role = null): array
    {
        if ($context === self::CONTEXT_COLLECTION) {
            return [
                'id' => 'id',
                'phoneNumber' => 'phoneNumber',
                'status' => 'status',
                'createdAt' => 'createdAt',
                'companyId' => 'company',
            ];
        }

        return parent::getPropertyMap($context, $role);
    }
}
