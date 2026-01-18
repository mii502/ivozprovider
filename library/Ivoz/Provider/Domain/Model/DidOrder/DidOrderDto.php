<?php

namespace Ivoz\Provider\Domain\Model\DidOrder;

class DidOrderDto extends DidOrderDtoAbstract
{
    public static function getPropertyMap(string $context = '', string $role = null): array
    {
        if ($role === 'ROLE_SUPER_ADMIN') {
            return [
                'id' => 'id',
                'status' => 'status',
            ];
        }

        if ($context === self::CONTEXT_COLLECTION) {
            $response = [
                'id' => 'id',
                'status' => 'status',
                'requestedAt' => 'requestedAt',
                'setupFee' => 'setupFee',
                'monthlyFee' => 'monthlyFee',
                'companyId' => 'company',
                'ddiId' => 'ddi',
            ];

            // Brand admin sees all orders in their brand
            if ($role === 'ROLE_BRAND_ADMIN') {
                $response['approvedAt'] = 'approvedAt';
                $response['rejectedAt'] = 'rejectedAt';
                $response['approvedById'] = 'approvedBy';
            }

            return $response;
        }

        // Detail context
        $response = parent::getPropertyMap(...func_get_args());

        // Company admin doesn't see who approved
        if ($role === 'ROLE_COMPANY_ADMIN') {
            unset($response['approvedById']);
            unset($response['companyId']);
        }

        // Brand admin doesn't need brandId since they filter by brand context
        if ($role === 'ROLE_BRAND_ADMIN') {
            // Brand admin sees everything
        }

        return $response;
    }

    public function toArray(bool $hideSensitiveData = false): array
    {
        $response = parent::toArray($hideSensitiveData);

        return $response;
    }

    public function denormalize(array $data, string $context, string $role = ''): void
    {
        $contextProperties = self::getPropertyMap($context, $role);

        // Brand admins can set company
        if ($role === 'ROLE_BRAND_ADMIN') {
            $contextProperties['companyId'] = 'company';
        }

        $this->setByContext(
            $contextProperties,
            $data
        );
    }
}
