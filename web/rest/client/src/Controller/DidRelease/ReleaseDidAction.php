<?php

declare(strict_types=1);

/**
 * DID Release API Controller
 * Server path: /opt/irontec/ivozprovider/web/rest/client/src/Controller/DidRelease/ReleaseDidAction.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-release
 */

namespace Controller\DidRelease;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\Ddi\DdiRepository;
use Ivoz\Provider\Domain\Service\Ddi\DidReleaseServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /api/client/dids/release
 *
 * Allows customers to release their purchased DIDs back to the marketplace.
 *
 * Request Body:
 * {
 *   "ddiId": 123
 * }
 *
 * Success Response (200):
 * {
 *   "success": true,
 *   "message": "DID released successfully",
 *   "ddiNumber": "+34911234567",
 *   "newDdiId": 456
 * }
 *
 * Error Responses:
 * - 400: ddi_not_assigned (DID is not currently assigned)
 * - 403: ddi_not_owned (DID belongs to different company)
 * - 404: ddi_not_found (DID ID doesn't exist)
 * - 500: release_failed (UnlinkDdi operation failed)
 */
class ReleaseDidAction
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly DdiRepository $ddiRepository,
        private readonly DidReleaseServiceInterface $didReleaseService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): Response
    {
        // Get authenticated user
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

        // Parse request body
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        $ddiId = $data['ddiId'] ?? null;

        if (!$ddiId) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'invalid_request',
                'message' => 'ddiId is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $ddiId = filter_var($ddiId, FILTER_VALIDATE_INT);

        if ($ddiId === false || $ddiId <= 0) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'invalid_request',
                'message' => 'ddiId must be a positive integer',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Load DDI
        $ddi = $this->ddiRepository->find($ddiId);
        if (!$ddi) {
            return new JsonResponse([
                'success' => false,
                'errorCode' => 'ddi_not_found',
                'message' => 'DID not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Execute release
        $result = $this->didReleaseService->release($company, $ddi);

        // Map error codes to HTTP status
        if (!$result->isSuccess()) {
            $statusCode = match ($result->getErrorCode()) {
                'ddi_not_found' => Response::HTTP_NOT_FOUND,
                'ddi_not_owned' => Response::HTTP_FORBIDDEN,
                'ddi_not_assigned' => Response::HTTP_BAD_REQUEST,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };

            $this->logger->warning('DID release failed', [
                'company_id' => $company->getId(),
                'ddi_id' => $ddiId,
                'error_code' => $result->getErrorCode(),
                'error_message' => $result->getErrorMessage(),
            ]);

            return new JsonResponse($result->toArray(), $statusCode);
        }

        $this->logger->info('DID released successfully', [
            'company_id' => $company->getId(),
            'ddi_id' => $ddiId,
            'new_ddi_id' => $result->getNewDdiId(),
            'ddi_number' => $result->getDdiNumber(),
        ]);

        return new JsonResponse($result->toArray(), Response::HTTP_OK);
    }
}
