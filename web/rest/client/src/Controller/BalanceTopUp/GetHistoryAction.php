<?php

declare(strict_types=1);

namespace Controller\BalanceTopUp;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Model\BalanceMovement\BalanceMovementRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /balance/history - Get balance transaction history
 *
 * Returns a paginated list of balance movements for the company.
 * This shows credits, debits, and their resulting balances.
 *
 * Query parameters:
 * - page: Page number (default: 1)
 * - per_page: Items per page (default: 20, max: 100)
 */
class GetHistoryAction
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private BalanceMovementRepository $balanceMovementRepository
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            throw new ResourceClassNotFoundException('User not found');
        }

        /** @var AdministratorInterface $user */
        $user = $token->getUser();
        $company = $user->getCompany();

        if (!$company) {
            throw new NotFoundHttpException('Company not found');
        }

        // Parse pagination parameters
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) $request->query->get('per_page', self::DEFAULT_PER_PAGE))
        );

        // Use Doctrine Criteria for pagination with Selectable interface
        $criteria = \Doctrine\Common\Collections\Criteria::create()
            ->where(\Doctrine\Common\Collections\Criteria::expr()->eq('company', $company))
            ->orderBy(['createdOn' => 'DESC'])
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        // Get total count using separate criteria without pagination
        $countCriteria = \Doctrine\Common\Collections\Criteria::create()
            ->where(\Doctrine\Common\Collections\Criteria::expr()->eq('company', $company));

        $totalCollection = $this->balanceMovementRepository->matching($countCriteria);
        $total = $totalCollection->count();

        // Get paginated results
        $movements = $this->balanceMovementRepository->matching($criteria)->toArray();

        // Convert to transaction array
        // Note: BalanceMovement entity has no 'type' or 'comment' fields
        // We determine type based on amount: positive = topup, negative = deduct
        $transactions = array_map(
            function($movement) {
                $amount = $movement->getAmount() ?? 0;
                $type = $amount >= 0 ? 'manual_topup' : 'call_deduct';

                return [
                    'id' => $movement->getId(),
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $movement->getBalance() ?? 0,
                    'created_at' => $movement->getCreatedOn()?->format(\DateTimeInterface::ATOM) ?? date(\DateTimeInterface::ATOM),
                    'reference' => null,
                ];
            },
            $movements
        );

        return new JsonResponse([
            'transactions' => $transactions,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }
}
