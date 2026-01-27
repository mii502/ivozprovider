<?php

declare(strict_types=1);

/**
 * Company BYON Limit Update Controller (Brand API)
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/Byon/UpdateByonLimitAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon (Phase 4)
 */

namespace Controller\Byon;

use Doctrine\ORM\EntityManagerInterface;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Company\CompanyRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Update company BYON limit (brand admin only)
 *
 * PUT /companies/{id}/byon-limit
 * Body: { "byonMaxNumbers": 20 }
 */
class UpdateByonLimitAction
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly CompanyRepository $companyRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        /** @var TokenInterface $token */
        $token = $this->tokenStorage->getToken();

        /** @var AdministratorInterface $admin */
        $admin = $token->getUser();
        $brand = $admin->getBrand();

        if ($brand === null) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'BRAND_NOT_FOUND',
                'message' => 'Brand not found for admin',
            ], 404);
        }

        // Get company ID from route
        $companyId = (int) $request->attributes->get('id');

        // Parse request body
        $data = json_decode($request->getContent(), true);
        $byonMaxNumbers = $data['byonMaxNumbers'] ?? null;

        if ($byonMaxNumbers === null) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'MISSING_BYON_MAX_NUMBERS',
                'message' => 'byonMaxNumbers is required',
            ], 400);
        }

        $byonMaxNumbers = (int) $byonMaxNumbers;

        // Validate range
        if ($byonMaxNumbers < 0 || $byonMaxNumbers > 1000) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'INVALID_BYON_MAX_NUMBERS',
                'message' => 'byonMaxNumbers must be between 0 and 1000',
            ], 400);
        }

        // Find company
        $company = $this->companyRepository->find($companyId);

        if ($company === null) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'COMPANY_NOT_FOUND',
                'message' => 'Company not found',
            ], 404);
        }

        // Check brand ownership
        $companyBrand = $company->getBrand();
        if ($companyBrand === null || $companyBrand->getId() !== $brand->getId()) {
            $this->logger->warning('BYON: Unauthorized limit update attempt', [
                'adminId' => $admin->getId(),
                'adminBrandId' => $brand->getId(),
                'companyId' => $companyId,
                'companyBrandId' => $companyBrand?->getId(),
            ]);
            throw new AccessDeniedHttpException('Company does not belong to your brand');
        }

        try {
            // Update company BYON limit using direct database update
            // Note: Using direct query because EntityTools persistDto doesn't work
            // for updating scalar fields on existing entities in all cases
            $conn = $this->entityManager->getConnection();
            $conn->executeStatement(
                'UPDATE Companies SET byonMaxNumbers = :limit WHERE id = :id',
                [
                    'limit' => $byonMaxNumbers,
                    'id' => $companyId,
                ]
            );

            $this->logger->info('BYON: Company limit updated by brand admin', [
                'adminId' => $admin->getId(),
                'companyId' => $companyId,
                'byonMaxNumbers' => $byonMaxNumbers,
            ]);

            return new JsonResponse([
                'success' => true,
                'byonMaxNumbers' => $byonMaxNumbers,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('BYON: Limit update failed', [
                'adminId' => $admin->getId(),
                'companyId' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'errorCode' => 'UPDATE_FAILED',
                'message' => 'Failed to update BYON limit',
            ], 500);
        }
    }
}
