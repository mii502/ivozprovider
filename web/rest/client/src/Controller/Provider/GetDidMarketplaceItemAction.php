<?php

declare(strict_types=1);

/**
 * DID Marketplace Item Action - Get single DID details
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Provider/GetDidMarketplaceItemAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 */

namespace Controller\Provider;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Ivoz\Provider\Domain\Service\Ddi\DidInventoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /client/api/dids/marketplace/{id}
 *
 * Returns detailed information about a single available DID.
 */
class GetDidMarketplaceItemAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private DidInventoryService $didInventoryService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            throw new ResourceClassNotFoundException('User not found');
        }

        /** @var AdministratorInterface $admin */
        $admin = $token->getUser();
        $company = $admin->getCompany();

        if (!$company) {
            throw new ResourceClassNotFoundException('Company not found');
        }

        $brand = $company->getBrand();

        // Get DID ID from path
        $ddiId = (int) $request->attributes->get('id');

        if ($ddiId <= 0) {
            return new JsonResponse(
                ['error' => 'Invalid DID ID'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Get available DID
        $ddi = $this->didInventoryService->getAvailableDdiById($brand, $ddiId);

        if (!$ddi) {
            return new JsonResponse(
                ['error' => 'DID not found or not available'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Transform to response
        return new JsonResponse($this->transformDdi($ddi));
    }

    /**
     * Transform a DDI entity to the detailed API response format
     *
     * @param DdiInterface $ddi
     * @return array<string, mixed>
     */
    private function transformDdi(DdiInterface $ddi): array
    {
        $country = $ddi->getCountry();
        $countryName = null;
        if ($country) {
            $name = $country->getName();
            $countryName = is_array($name) ? ($name['en'] ?? reset($name)) : (string) $name;
        }

        return [
            '@context' => '/api/client/contexts/AvailableDdi',
            '@id' => '/api/client/dids/marketplace/' . $ddi->getId(),
            '@type' => 'AvailableDdi',
            'id' => $ddi->getId(),
            'ddi' => $ddi->getDdi(),
            'ddiE164' => $ddi->getDdie164(),
            'description' => $ddi->getDescription(),
            'country' => [
                'id' => $country?->getId(),
                'code' => $country?->getCode(),
                'name' => $countryName,
                'countryCode' => $country?->getCountryCode(),
            ],
            'ddiType' => $ddi->getType(),
            'pricing' => [
                'setupPrice' => number_format($ddi->getSetupPrice(), 2, '.', ''),
                'monthlyPrice' => number_format($ddi->getMonthlyPrice(), 2, '.', ''),
                'currency' => 'EUR',
            ],
            'inventoryStatus' => $ddi->getInventoryStatus(),
        ];
    }
}
