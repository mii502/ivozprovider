<?php

declare(strict_types=1);

/**
 * WHMCS Overdue Webhook Controller
 * Server path: /opt/irontec/ivozprovider/web/rest/brand/src/Controller/Webhook/WhmcsOverdueWebhookController.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-did-renewal (Phase 5)
 */

namespace Controller\Webhook;

use Ivoz\Core\Infrastructure\Persistence\Doctrine\ORM\EntityManager;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceInterface;
use Ivoz\Provider\Domain\Model\Invoice\InvoiceRepository;
use Ivoz\Provider\Domain\Service\Ddi\DidRenewalOverdueHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook controller for WHMCS InvoiceOverdue notifications
 *
 * Receives POST requests from WHMCS when an invoice is marked overdue:
 * - Verifies HMAC-SHA256 signature
 * - Validates timestamp (5-minute window)
 * - Looks up invoice via notes field pattern: IvozProvider:{invoice_id}
 * - Routes to appropriate handler based on invoice type
 * - For did_renewal invoices: releases DIDs back to inventory
 *
 * @see DidRenewalOverdueHandler For the DID release logic
 * @see WhmcsPaidWebhookController For the payment webhook (similar pattern)
 */
class WhmcsOverdueWebhookController
{
    /** Maximum allowed timestamp skew in seconds (5 minutes) */
    private const MAX_TIMESTAMP_SKEW = 300;

    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private EntityManager $entityManager,
        private LoggerInterface $logger,
        private string $webhookSecret,
        private ?DidRenewalOverdueHandlerInterface $didRenewalOverdueHandler = null
    ) {
    }

    public function __invoke(Request $request): Response
    {
        try {
            return $this->processWebhook($request);
        } catch (\Exception $e) {
            $this->logger->error('WHMCS overdue webhook processing failed', [
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
            $this->logger->warning('WHMCS overdue webhook missing required headers');
            return new JsonResponse(
                ['error' => 'Missing signature or timestamp header'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        // 3. Verify HMAC-SHA256 signature
        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->webhookSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->warning('WHMCS overdue webhook signature mismatch', [
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
            $this->logger->warning('WHMCS overdue webhook timestamp expired', [
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
            $this->logger->warning('WHMCS overdue webhook invalid JSON', [
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
            $this->logger->warning('WHMCS overdue webhook missing invoice ID in notes', [
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
            $this->logger->warning('WHMCS overdue webhook invoice not found', [
                'invoice_id' => $invoiceId,
                'whmcs_invoice_id' => $data['whmcs_invoice_id'] ?? 'unknown',
            ]);
            return new JsonResponse(
                ['error' => 'Invoice not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        // 8. Idempotency check - skip if invoice already paid
        if ($invoice->isPaidViaWhmcs()) {
            $this->logger->info('WHMCS overdue webhook: Invoice already paid, ignoring', [
                'invoice_id' => $invoiceId,
                'paid_at' => $invoice->getWhmcsPaidAt()?->format('c'),
            ]);
            return new JsonResponse([
                'status' => 'already_paid',
                'invoice_id' => $invoiceId,
                'message' => 'Invoice was already paid, overdue webhook ignored',
            ]);
        }

        // 9. Route to appropriate handler based on invoice type
        $invoiceType = $invoice->getInvoiceType();
        $handlerResult = $this->routeToHandler($invoice, $data);

        // 10. Persist changes
        $this->entityManager->flush();

        $this->logger->info('WHMCS overdue webhook processed successfully', [
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
     * Currently only handles did_renewal invoices for DID release.
     * Other invoice types log a warning and return no-op.
     *
     * @param InvoiceInterface $invoice
     * @param array $data Webhook payload data
     * @return array Handler result for response
     */
    private function routeToHandler(InvoiceInterface $invoice, array $data): array
    {
        $invoiceType = $invoice->getInvoiceType();

        // Handle DID renewal overdue - release DIDs back to inventory
        if ($invoiceType === InvoiceInterface::INVOICE_TYPE_DID_RENEWAL) {
            if ($this->didRenewalOverdueHandler === null) {
                $this->logger->error('WHMCS overdue webhook: DidRenewalOverdueHandler not available');
                return [
                    'action' => 'handler_unavailable',
                    'message' => 'DID renewal overdue handler not configured',
                ];
            }

            $this->logger->debug('WHMCS overdue webhook: Routing to DidRenewalOverdueHandler', [
                'invoice_id' => $invoice->getId(),
                'invoice_type' => $invoiceType,
            ]);

            return $this->didRenewalOverdueHandler->handle($invoice);
        }

        // Other invoice types - log and ignore
        // Balance top-up doesn't have an overdue scenario (no balance to add)
        // DID purchase invoices that go overdue - DIDs were never provisioned
        // Standard invoices - handled by WHMCS suspension logic
        $this->logger->info('WHMCS overdue webhook: No handler for invoice type', [
            'invoice_id' => $invoice->getId(),
            'invoice_type' => $invoiceType,
        ]);

        return [
            'action' => 'no_handler',
            'message' => sprintf('No overdue handler for invoice type: %s', $invoiceType),
            'invoice_type' => $invoiceType,
        ];
    }
}
