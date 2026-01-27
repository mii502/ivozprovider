<?php

declare(strict_types=1);

/**
 * BYON Get Status Action - Get BYON limits and usage
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/Byon/GetStatusAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Controller\Byon;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\Byon\ByonServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /byon/status
 *
 * Get BYON feature status, limits and current usage.
 *
 * Success Response (200):
 * {
 *   "byonMaxNumbers": 10,
 *   "currentByonCount": 3,
 *   "remainingByonSlots": 7,
 *   "dailyVerificationLimit": 10,
 *   "verificationsToday": 2,
 *   "remainingVerificationsToday": 8,
 *   "canAddByon": true
 * }
 */
class GetStatusAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ByonServiceInterface $byonService,
        private LoggerInterface $logger
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

        try {
            // Get BYON status
            $status = $this->byonService->getStatus($company);

            return new JsonResponse($status->toArray());

        } catch (\Exception $e) {
            $this->logger->error('Failed to get BYON status', [
                'company_id' => $company->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'errorCode' => 'INTERNAL_ERROR',
                'message' => 'Failed to retrieve BYON status.',
            ], 500);
        }
    }
}
