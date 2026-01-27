<?php

declare(strict_types=1);

/**
 * BYON DDI Release Controller (Brand API)
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/Byon/ReleaseByonAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon (Phase 4)
 */

namespace Controller\Byon;

use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Service\Byon\ByonException;
use Ivoz\Provider\Domain\Service\Byon\ByonServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Release a BYON DDI (brand admin only)
 *
 * POST /byon/release
 * Body: { "ddiId": 123 }
 */
class ReleaseByonAction
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ByonServiceInterface $byonService,
        private readonly DdiRepository $ddiRepository,
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

        // Parse request body
        $data = json_decode($request->getContent(), true);
        $ddiId = $data['ddiId'] ?? null;

        if ($ddiId === null) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'MISSING_DDI_ID',
                'message' => 'ddiId is required',
            ], 400);
        }

        $ddiId = (int) $ddiId;

        // Verify DDI belongs to this brand
        $ddi = $this->ddiRepository->find($ddiId);

        if ($ddi === null) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'DDI_NOT_FOUND',
                'message' => 'DDI not found',
            ], 404);
        }

        // Check brand ownership
        $ddiBrand = $ddi->getBrand();
        if ($ddiBrand === null || $ddiBrand->getId() !== $brand->getId()) {
            $this->logger->warning('BYON: Unauthorized release attempt', [
                'adminId' => $admin->getId(),
                'adminBrandId' => $brand->getId(),
                'ddiId' => $ddiId,
                'ddiBrandId' => $ddiBrand?->getId(),
            ]);
            throw new AccessDeniedHttpException('DDI does not belong to your brand');
        }

        // Attempt release
        try {
            $this->byonService->release($ddiId);

            $this->logger->info('BYON: DDI released by brand admin', [
                'adminId' => $admin->getId(),
                'ddiId' => $ddiId,
                'ddiE164' => $ddi->getDdie164(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'BYON DDI released',
            ]);

        } catch (ByonException $e) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getHttpStatusCode());

        } catch (\Throwable $e) {
            $this->logger->error('BYON: Release failed', [
                'adminId' => $admin->getId(),
                'ddiId' => $ddiId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'errorCode' => 'RELEASE_FAILED',
                'message' => 'Failed to release BYON DDI',
            ], 500);
        }
    }
}
