<?php

declare(strict_types=1);

/**
 * DID Marketplace Countries Action - Get countries with available DIDs
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Provider/GetDidMarketplaceCountriesAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-marketplace
 */

namespace Controller\Provider;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\Ddi\DidInventoryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /client/api/dids/marketplace/countries
 *
 * Returns list of countries that have available DIDs for purchase.
 * Includes count of available DIDs per country.
 */
class GetDidMarketplaceCountriesAction
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

        // Get countries with available DIDs
        $countries = $this->didInventoryService->getAvailableCountries($brand);

        // Get types with counts
        $types = $this->didInventoryService->getAvailableTypes($brand);

        // Get price range
        $priceRange = $this->didInventoryService->getPriceRange($brand);

        return new JsonResponse([
            'countries' => $countries,
            'types' => $types,
            'priceRange' => [
                'setup' => [
                    'min' => number_format($priceRange['minSetup'], 2, '.', ''),
                    'max' => number_format($priceRange['maxSetup'], 2, '.', ''),
                ],
                'monthly' => [
                    'min' => number_format($priceRange['minMonthly'], 2, '.', ''),
                    'max' => number_format($priceRange['maxMonthly'], 2, '.', ''),
                ],
            ],
        ]);
    }
}
