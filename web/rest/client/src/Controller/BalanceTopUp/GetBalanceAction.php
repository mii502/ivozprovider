<?php

declare(strict_types=1);

namespace Controller\BalanceTopUp;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\BalanceTopUp\BalanceTopUpService;
use Ivoz\Provider\Domain\Service\Company\CompanyBalanceServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * GET /balance - Get current balance and top-up configuration
 *
 * Returns real-time balance from CGRates (with MySQL fallback on error).
 */
class GetBalanceAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private BalanceTopUpService $balanceTopUpService,
        private CompanyBalanceServiceInterface $companyBalanceService,
        private LoggerInterface $logger
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

        // Get real-time balance from CGRates, with MySQL fallback
        $balance = $company->getBalance() ?? 0.0; // Default fallback
        try {
            $brandId = $company->getBrand()?->getId();
            $companyId = $company->getId();
            if ($brandId && $companyId) {
                $balance = $this->companyBalanceService->getBalance($brandId, $companyId);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('CGRates balance lookup failed, using MySQL fallback', [
                'company_id' => $company->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return new JsonResponse([
            'balance' => $balance,
            'currency' => $currency,
            'billingMethod' => $billingMethod,
            'showTopUp' => $showTopUp,
            'minAmount' => $this->balanceTopUpService->getMinAmount(),
            'maxAmount' => $this->balanceTopUpService->getMaxAmount(),
        ]);
    }
}
