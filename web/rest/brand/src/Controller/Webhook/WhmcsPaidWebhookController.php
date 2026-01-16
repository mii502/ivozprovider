<?php

namespace Controller\Webhook;

use Ivoz\Core\Infrastructure\Persistence\Doctrine\ORM\EntityManager;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceRepository;
use Ivoz\Provider\Domain\Service\Invoice\Handler\InvoicePaidHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook controller for WHMCS InvoicePaid notifications
 *
 * Receives POST requests from WHMCS when an invoice is paid:
 * - Verifies HMAC-SHA256 signature
 * - Validates timestamp (5-minute window)
 * - Looks up invoice via notes field pattern: IvozProvider:{invoice_id}
 * - Marks invoice as paid
 * - Routes to appropriate handler based on invoice type
 *
 * @see integration/research/ivozprovider-whmcs-integration-analysis.md
 */
class WhmcsPaidWebhookController
{
    /** Maximum allowed timestamp skew in seconds (5 minutes) */
    private const MAX_TIMESTAMP_SKEW = 300;

    /**
     * @param iterable<InvoicePaidHandlerInterface> $handlers
     */
    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private EntityManager $entityManager,
        private LoggerInterface $logger,
        private string $webhookSecret,
        private iterable $handlers = []
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            return $this->processWebhook($request);
        } catch (\Exception $e) {
            $this->logger->error('WHMCS webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(
                ['error' => $e->getMessage()],
                $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    private function processWebhook(Request $request): JsonResponse
    {
        // 1. Get signature headers
        $signature = $request->headers->get('X-Webhook-Signature');
        $timestamp = $request->headers->get('X-Webhook-Timestamp');
        $payload = $request->getContent();

        // 2. Verify required headers are present
        if (empty($signature) || empty($timestamp)) {
            $this->logger->warning('WHMCS webhook missing required headers');
            return new JsonResponse(
                ['error' => 'Missing signature or timestamp header'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // 3. Verify HMAC-SHA256 signature
        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('WHMCS webhook signature mismatch', [
                'received' => substr($signature, 0, 16) . '...',
            ]);
            return new JsonResponse(
                ['error' => 'Invalid signature'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // 4. Validate timestamp (5-minute window to prevent replay attacks)
        $timestampInt = (int) $timestamp;
        $timeDiff = abs(time() - $timestampInt);

        if ($timeDiff > self::MAX_TIMESTAMP_SKEW) {
            $this->logger->warning('WHMCS webhook timestamp expired', [
                'timestamp' => $timestampInt,
                'diff_seconds' => $timeDiff,
            ]);
            return new JsonResponse(
                ['error' => 'Timestamp expired'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // 5. Parse payload
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('WHMCS webhook invalid JSON', [
                'error' => json_last_error_msg(),
            ]);
            return new JsonResponse(
                ['error' => 'Invalid JSON payload'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // 6. Extract IvozProvider invoice ID from notes field
        $notes = $data['notes'] ?? '';
        $invoiceId = $this->extractInvoiceId($notes);

        if (!$invoiceId) {
            $this->logger->warning('WHMCS webhook missing invoice ID in notes', [
                'notes' => $notes,
                'whmcs_invoice_id' => $data['whmcs_invoice_id'] ?? 'unknown',
            ]);
            return new JsonResponse(
                ['error' => 'Invoice ID not found in notes'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // 7. Find invoice in IvozProvider
        /** @var InvoiceInterface|null $invoice */
        $invoice = $this->invoiceRepository->find($invoiceId);

        if (!$invoice) {
            $this->logger->warning('WHMCS webhook invoice not found', [
                'invoice_id' => $invoiceId,
                'whmcs_invoice_id' => $data['whmcs_invoice_id'] ?? 'unknown',
            ]);
            return new JsonResponse(
                ['error' => 'Invoice not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // 8. Idempotency check - prevent double processing
        if ($invoice->isPaidViaWhmcs()) {
            $this->logger->info('WHMCS webhook duplicate payment notification', [
                'invoice_id' => $invoiceId,
                'paid_at' => $invoice->getWhmcsPaidAt()?->format('c'),
            ]);
            return new JsonResponse([
                'status' => 'already_processed',
                'invoice_id' => $invoiceId,
                'paid_at' => $invoice->getWhmcsPaidAt()?->format('c'),
            ]);
        }

        // 9. Mark invoice as paid
        $invoice->markAsPaid();

        // 10. Route to appropriate handler based on invoice type
        // NOTE: Handlers will be implemented in Phase E
        // For now, we just log and return success
        $invoiceType = $invoice->getInvoiceType();
        $handlerResult = $this->routeToHandler($invoice, $data);

        // 11. Persist changes
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        $this->logger->info('WHMCS webhook processed successfully', [
            'invoice_id' => $invoiceId,
            'invoice_type' => $invoiceType,
            'whmcs_invoice_id' => $data['whmcs_invoice_id'] ?? 'unknown',
            'handler_result' => $handlerResult,
        ]);

        return new JsonResponse([
            'status' => 'ok',
            'invoice_id' => $invoiceId,
            'invoice_type' => $invoiceType,
            ...$handlerResult,
        ]);
    }

    /**
     * Extract IvozProvider invoice ID from WHMCS invoice notes
     *
     * Notes field pattern: IvozProvider:{invoice_id}
     */
    private function extractInvoiceId(string $notes): ?int
    {
        if (preg_match('/IvozProvider:(\d+)/', $notes, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    /**
     * Route to appropriate handler based on invoice type
     *
     * Finds and invokes the handler that supports the invoice type:
     * - balance_topup: BalanceTopUpHandler - Add credits to Company.balance
     * - did_purchase: DidPurchaseHandler - Provision the DID
     * - did_renewal: DidRenewalHandler - Advance nextRenewalAt date
     * - standard: StandardInvoiceHandler - Record payment (postpaid monthly)
     *
     * @param InvoiceInterface $invoice
     * @param array $data Webhook payload data
     * @return array Handler result for response
     * @throws \DomainException If handler fails
     */
    private function routeToHandler(InvoiceInterface $invoice, array $data): array
    {
        $invoiceType = $invoice->getInvoiceType();

        // Find handler that supports this invoice type
        foreach ($this->handlers as $handler) {
            if ($handler->supports($invoiceType)) {
                $this->logger->debug('WHMCS webhook: Routing to handler', [
                    'invoice_id' => $invoice->getId(),
                    'invoice_type' => $invoiceType,
                    'handler' => get_class($handler),
                ]);

                return $handler->handle($invoice, $data);
            }
        }

        // No handler found - log warning but don't fail
        $this->logger->warning('WHMCS webhook: No handler found for invoice type', [
            'invoice_id' => $invoice->getId(),
            'invoice_type' => $invoiceType,
        ]);

        return [
            'action' => 'no_handler',
            'message' => sprintf('No handler registered for invoice type: %s', $invoiceType),
            'invoice_type' => $invoiceType,
            'company_id' => $invoice->getCompany()->getId(),
        ];
    }
}
