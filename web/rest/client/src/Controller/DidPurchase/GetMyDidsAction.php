<?php

declare(strict_types=1);

/**
 * Get My DIDs Action - List customer's purchased DIDs
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/DidPurchase/GetMyDidsAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-purchase
 */

namespace Controller\DidPurchase;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /my/dids
 *
 * List all DIDs owned by the current company.
 * Returns DIDs that were purchased from the marketplace.
 *
 * Query Parameters:
 * - _page: Page number (default: 1)
 * - _itemsPerPage: Items per page (default: 20, max: 100)
 * - orderBy: Sort field (ddi, monthlyPrice, nextRenewalAt, assignedAt)
 * - orderDir: Sort direction (ASC, DESC)
 *
 * Response:
 * [
 *   {
 *     "id": 123,
 *     "ddi": "912345678",
 *     "ddiE164": "+34912345678",
 *     "description": "Main office",
 *     "country": "ES",
 *     "countryId": 68,
 *     "countryName": "Spain",
 *     "ddiType": "inout",
 *     "monthlyPrice": "2.00",
 *     "inventoryStatus": "assigned",
 *     "assignedAt": "2026-01-17T12:00:00+00:00",
 *     "nextRenewalAt": "2026-02-01T00:00:00+00:00",
 *     "routeType": "user",
 *     "target": "John Doe"
 *   }
 * ]
 *
 * Headers:
 * - X-Total-Count: Total number of DIDs
 * - X-Page: Current page
 * - X-Items-Per-Page: Items per page
 */
class GetMyDidsAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $entityManager
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
            throw new NotFoundHttpException('Company not found');
        }

        // Extract query parameters
        $page = max(1, (int) $request->query->get('_page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('_itemsPerPage', 20)));
        $orderBy = $request->query->get('orderBy', 'ddi');
        $orderDir = strtoupper($request->query->get('orderDir', 'ASC'));

        // Validate order fields
        $allowedOrderBy = ['ddi', 'monthlyPrice', 'nextRenewalAt', 'assignedAt', 'inventoryStatus'];
        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'ddi';
        }
        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        // Build query for company's DDIs
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
            ->from('Ivoz\Provider\Domain\Model\Ddi\Ddi', 'd')
            ->leftJoin('d.country', 'c')
            ->where('d.company = :company')
            ->setParameter('company', $company)
            ->orderBy('d.' . $orderBy, $orderDir);

        // Get total count
        $countQb = clone $qb;
        $countQb->select('COUNT(d.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Apply pagination
        $offset = ($page - 1) * $itemsPerPage;
        $qb->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        /** @var DdiInterface[] $ddis */
        $ddis = $qb->getQuery()->getResult();

        // Transform to response format
        $members = array_map(
            fn(DdiInterface $ddi) => $this->transformDdi($ddi),
            $ddis
        );

        // Return plain array with pagination headers (ivoz-ui format)
        $response = new JsonResponse($members);
        $response->headers->set('X-Total-Count', (string) $total);
        $response->headers->set('X-Page', (string) $page);
        $response->headers->set('X-Items-Per-Page', (string) $itemsPerPage);

        return $response;
    }

    /**
     * Transform a DDI entity to the API response format
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
            if (is_object($name) && method_exists($name, 'getEn')) {
                $countryName = $name->getEn();
            } elseif (is_array($name)) {
                $countryName = $name['en'] ?? reset($name);
            } else {
                $countryName = (string) $name;
            }
        }

        // Get route target description
        $target = $this->getRouteTarget($ddi);

        return [
            '@id' => '/api/client/my/dids/' . $ddi->getId(),
            'id' => $ddi->getId(),
            'ddi' => $ddi->getDdi(),
            'ddiE164' => $ddi->getDdie164(),
            'description' => $ddi->getDescription(),
            'country' => $country?->getCode(),
            'countryId' => $country?->getId(),
            'countryName' => $countryName,
            'ddiType' => $ddi->getType(),
            'monthlyPrice' => number_format($ddi->getMonthlyPrice(), 2, '.', ''),
            'inventoryStatus' => $ddi->getInventoryStatus(),
            'assignedAt' => $ddi->getAssignedAt()?->format('c'),
            'nextRenewalAt' => $ddi->getNextRenewalAt()?->format('c'),
            'routeType' => $ddi->getRouteType(),
            'target' => $target,
            'displayName' => $ddi->getDisplayName(),
            'recordCalls' => $ddi->getRecordCalls(),
        ];
    }

    /**
     * Get a human-readable description of the DDI's route target
     *
     * @param DdiInterface $ddi
     * @return string|null
     */
    private function getRouteTarget(DdiInterface $ddi): ?string
    {
        $routeType = $ddi->getRouteType();

        if (!$routeType) {
            return null;
        }

        return match ($routeType) {
            DdiInterface::ROUTETYPE_USER => $ddi->getUser()?->getName() ?? 'User',
            DdiInterface::ROUTETYPE_IVR => $ddi->getIvr()?->getName() ?? 'IVR',
            DdiInterface::ROUTETYPE_HUNTGROUP => $ddi->getHuntGroup()?->getName() ?? 'Hunt Group',
            DdiInterface::ROUTETYPE_FAX => $ddi->getFax()?->getName() ?? 'Fax',
            DdiInterface::ROUTETYPE_CONFERENCEROOM => $ddi->getConferenceRoom()?->getName() ?? 'Conference',
            DdiInterface::ROUTETYPE_QUEUE => $ddi->getQueue()?->getName() ?? 'Queue',
            DdiInterface::ROUTETYPE_CONDITIONAL => $ddi->getConditionalRoute()?->getName() ?? 'Conditional',
            DdiInterface::ROUTETYPE_RESIDENTIAL => $ddi->getResidentialDevice()?->getName() ?? 'Residential',
            DdiInterface::ROUTETYPE_RETAIL => $ddi->getRetailAccount()?->getName() ?? 'Retail',
            DdiInterface::ROUTETYPE_FRIEND => $ddi->getFriendValue() ?? 'Friend',
            DdiInterface::ROUTETYPE_LOCUTION => $ddi->getLocution()?->getName() ?? 'Locution',
            default => null,
        };
    }
}
