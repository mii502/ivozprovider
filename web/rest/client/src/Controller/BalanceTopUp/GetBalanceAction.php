<?php

declare(strict_types=1);

namespace Controller\BalanceTopUp;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\BalanceTopUp\BalanceTopUpService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /balance - Get current balance and top-up configuration
 */
class GetBalanceAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private BalanceTopUpService $balanceTopUpService
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

        $billingMethod = $company->getBillingMethod();
        $showTopUp = in_array($billingMethod, ['prepaid', 'pseudoprepaid'], true);

        // Get currency from company or default to EUR
        $currency = 'EUR';
        if (method_exists($company, 'getCurrencySymbol') && $company->getCurrencySymbol()) {
            $currency = $company->getCurrencySymbol();
        }

        return new JsonResponse([
            'balance' => $company->getBalance() ?? 0.0,
            'currency' => $currency,
            'billingMethod' => $billingMethod,
            'showTopUp' => $showTopUp,
            'minAmount' => $this->balanceTopUpService->getMinAmount(),
            'maxAmount' => $this->balanceTopUpService->getMaxAmount(),
        ]);
    }
}
