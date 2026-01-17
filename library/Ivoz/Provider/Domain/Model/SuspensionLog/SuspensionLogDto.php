<?php

namespace Ivoz\Provider\Domain\Model\SuspensionLog;

class SuspensionLogDto extends SuspensionLogDtoAbstract
{
    /**
     * @inheritdoc
     * @codeCoverageIgnore
     */
    public static function getPropertyMap(string $context = '', string $role = null): array
    {
        if ($context === self::CONTEXT_COLLECTION) {
            return [
                'action' => 'action',
                'reason' => 'reason',
                'createdAt' => 'createdAt',
                'id' => 'id',
                'companyId' => 'company',
                'ddiId' => 'ddi'
            ];
        }

        return parent::getPropertyMap($context, $role);
    }
}
