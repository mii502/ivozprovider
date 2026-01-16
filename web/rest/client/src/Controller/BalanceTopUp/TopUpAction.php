<?php

declare(strict_types=1);

namespace Controller\BalanceTopUp;

use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use Ivoz\Provider\Domain\Model\Administrator\AdministratorInterface;
use Ivoz\Provider\Domain\Service\BalanceTopUp\BalanceTopUpService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * POST /balance/topup - Request a balance top-up
 */
class TopUpAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private BalanceTopUpService $balanceTopUpService,
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

        // Parse request body
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON in request body');
        }

        if (!isset($data['amount'])) {
            throw new BadRequestHttpException('Amount is required');
        }

        $amount = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);

        if ($amount === false) {
            throw new BadRequestHttpException('Amount must be a valid number');
        }

        try {
            // Create the top-up invoice
            $invoice = $this->balanceTopUpService->createTopUpInvoice($company, $amount);

            // Get payment URL (may be null if not yet synced to WHMCS)
            $paymentUrl = $this->balanceTopUpService->getPaymentUrl($invoice);

            $this->logger->info('Balance top-up requested successfully', [
                'company_id' => $company->getId(),
                'invoice_id' => $invoice->getId(),
                'amount' => $amount,
            ]);

            return new JsonResponse([
                'success' => true,
                'invoiceId' => $invoice->getId(),
                'amount' => $amount,
                'whmcsRedirectUrl' => $paymentUrl,
            ]);
        } catch (\DomainException $e) {
            $this->logger->warning('Balance top-up validation failed', [
                'company_id' => $company->getId(),
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Balance top-up failed unexpectedly', [
                'company_id' => $company->getId(),
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again later.',
            ], 500);
        }
    }
}
