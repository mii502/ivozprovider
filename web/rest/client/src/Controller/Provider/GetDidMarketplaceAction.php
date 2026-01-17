<?php

declare(strict_types=1);

/**
 * DID Marketplace List Action - Browse available DIDs
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Provider/GetDidMarketplaceAction.php
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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /client/api/dids/marketplace
 *
 * Browse available DIDs with filtering and pagination.
 * Returns Hydra/API Platform compatible collection response.
 *
 * Query Parameters:
 * - country: Filter by country code (e.g., ES, FR, DE)
 * - type: Filter by DDI type (inout, out)
 * - priceMin: Filter by minimum monthly price
 * - priceMax: Filter by maximum monthly price
 * - search: Search by DDI number
 * - orderBy: Sort field (ddi, monthlyPrice, setupPrice)
 * - orderDir: Sort direction (ASC, DESC)
 * - _page: Page number (default: 1)
 * - _itemsPerPage: Items per page (default: 20, max: 100)
 */
class GetDidMarketplaceAction
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

        // Extract query parameters
        $filters = [
            'country' => $request->query->get('country'),
            'countryId' => $request->query->get('countryId'),
            'type' => $request->query->get('type'),
            'priceMin' => $request->query->get('priceMin'),
            'priceMax' => $request->query->get('priceMax'),
            'search' => $request->query->get('search'),
            'orderBy' => $request->query->get('orderBy', 'monthlyPrice'),
            'orderDir' => strtoupper($request->query->get('orderDir', 'ASC')),
        ];

        // Remove null values
        $filters = array_filter($filters, fn($v) => $v !== null);

        $page = max(1, (int) $request->query->get('_page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('_itemsPerPage', 20)));

        // Get available DIDs
        $result = $this->didInventoryService->getAvailableDdis(
            $brand,
            $filters,
            $page,
            $itemsPerPage
        );

        // Transform DDIs to API response format
        $members = array_map(
            fn(DdiInterface $ddi) => $this->transformDdi($ddi, $request->getBasePath()),
            $result['items']
        );

        // Check Accept header - ivoz-ui expects plain array for application/json
        // API Platform returns Hydra format only for application/ld+json
        $acceptHeader = $request->headers->get('Accept', 'application/json');
        $wantsHydra = str_contains($acceptHeader, 'application/ld+json');

        if (!$wantsHydra) {
            // Return plain array with pagination headers (like API Platform does)
            $response = new JsonResponse($members);
            $response->headers->set('X-Total-Count', (string) $result['total']);
            $response->headers->set('X-Page', (string) $page);
            $response->headers->set('X-Items-Per-Page', (string) $itemsPerPage);
            return $response;
        }

        // Build Hydra response for ld+json requests
        $totalPages = (int) ceil($result['total'] / $itemsPerPage);
        $basePath = '/api/client/dids/marketplace';
        $queryParams = $request->query->all();
        unset($queryParams['_page']);

        $hydraResponse = [
            '@context' => '/api/client/contexts/AvailableDdi',
            '@id' => $basePath,
            '@type' => 'hydra:Collection',
            'hydra:member' => $members,
            'hydra:totalItems' => $result['total'],
        ];

        // Add pagination view
        if ($result['total'] > 0) {
            $buildUrl = function (int $p) use ($basePath, $queryParams): string {
                $params = array_merge($queryParams, ['_page' => $p]);
                return $basePath . '?' . http_build_query($params);
            };

            $hydraResponse['hydra:view'] = [
                '@id' => $buildUrl($page),
                '@type' => 'hydra:PartialCollectionView',
                'hydra:first' => $buildUrl(1),
                'hydra:last' => $buildUrl($totalPages),
            ];

            if ($page > 1) {
                $hydraResponse['hydra:view']['hydra:previous'] = $buildUrl($page - 1);
            }

            if ($page < $totalPages) {
                $hydraResponse['hydra:view']['hydra:next'] = $buildUrl($page + 1);
            }
        }

        return new JsonResponse($hydraResponse);
    }

    /**
     * Transform a DDI entity to the API response format
     *
     * @param DdiInterface $ddi
     * @param string $basePath
     * @return array<string, mixed>
     */
    private function transformDdi(DdiInterface $ddi, string $basePath): array
    {
        $country = $ddi->getCountry();
        $countryName = null;
        if ($country) {
            $name = $country->getName();
            $countryName = $name instanceof \Ivoz\Provider\Domain\Model\Country\Name ? $name->getEn() : (string) $name;
        }

        return [
            '@id' => '/api/client/dids/marketplace/' . $ddi->getId(),
            'id' => $ddi->getId(),
            'ddi' => $ddi->getDdi(),
            'ddiE164' => $ddi->getDdie164(),
            'description' => $ddi->getDescription(),
            'country' => $country?->getCode(),
            'countryId' => $country?->getId(),
            'countryName' => $countryName,
            'ddiType' => $ddi->getType(),
            'setupPrice' => number_format($ddi->getSetupPrice(), 2, '.', ''),
            'monthlyPrice' => number_format($ddi->getMonthlyPrice(), 2, '.', ''),
            'inventoryStatus' => $ddi->getInventoryStatus(),
        ];
    }
}
