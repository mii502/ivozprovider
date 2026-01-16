<?php

declare(strict_types=1);

namespace Ivoz\Provider\Infrastructure\Api\Whmcs;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fake WHMCS API Service for testing
 *
 * Returns predefined responses without making actual HTTP requests.
 */
class FakeWhmcsApiService extends WhmcsApiService
{
    private int $nextInvoiceId = 1000;
    private array $createdInvoices = [];
    private ?string $forceError = null;

    public function __construct(
        ?HttpClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        string $apiUrl = 'https://test.local/includes/api.php',
        string $identifier = 'test_identifier',
        string $secret = 'test_secret'
    ) {
        // Create a mock HTTP client if not provided
        if ($httpClient === null) {
            $httpClient = new class implements HttpClientInterface {
                public function request(string $method, string $url, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
                {
                    return new class implements \Symfony\Contracts\HttpClient\ResponseInterface {
                        public function getStatusCode(): int
                        {
                            return 200;
                        }

                        public function getHeaders(bool $throw = true): array
                        {
                            return [];
                        }

                        public function getContent(bool $throw = true): string
                        {
                            return '{"result":"success"}';
                        }

                        public function toArray(bool $throw = true): array
                        {
                            return ['result' => 'success'];
                        }

                        public function cancel(): void
                        {
                        }

                        public function getInfo(?string $type = null): mixed
                        {
                            return null;
                        }
                    };
                }

                public function stream($responses, ?float $timeout = null): \Symfony\Contracts\HttpClient\ResponseStreamInterface
                {
                    throw new \RuntimeException('Not implemented');
                }

                public function withOptions(array $options): static
                {
                    return $this;
                }
            };
        }

        parent::__construct(
            $httpClient,
            $logger ?? new NullLogger(),
            $apiUrl,
            $identifier,
            $secret
        );
    }

    /**
     * Force the next API call to return an error
     */
    public function forceError(string $errorMessage): void
    {
        $this->forceError = $errorMessage;
    }

    /**
     * Clear any forced error
     */
    public function clearError(): void
    {
        $this->forceError = null;
    }

    /**
     * Get all invoices created during testing
     */
    public function getCreatedInvoices(): array
    {
        return $this->createdInvoices;
    }

    /**
     * Reset the fake service state
     */
    public function reset(): void
    {
        $this->nextInvoiceId = 1000;
        $this->createdInvoices = [];
        $this->forceError = null;
    }

    public function createInvoice(
        int $clientId,
        string $description,
        float $amount,
        \DateTime $dueDate,
        string $notes,
        bool $sendEmail = true,
        ?string $paymentMethod = 'banktransfer'
    ): int {
        if ($this->forceError !== null) {
            throw new WhmcsApiException($this->forceError);
        }

        $invoiceId = $this->nextInvoiceId++;

        $this->createdInvoices[$invoiceId] = [
            'invoiceid' => $invoiceId,
            'clientid' => $clientId,
            'description' => $description,
            'amount' => $amount,
            'duedate' => $dueDate->format('Y-m-d'),
            'notes' => $notes,
            'sendemail' => $sendEmail,
            'paymentmethod' => $paymentMethod,
            'status' => 'Unpaid',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        return $invoiceId;
    }

    public function getInvoice(int $invoiceId): array
    {
        if ($this->forceError !== null) {
            throw new WhmcsApiException($this->forceError);
        }

        if (!isset($this->createdInvoices[$invoiceId])) {
            throw new WhmcsApiException('Invoice not found');
        }

        return array_merge(
            ['result' => 'success'],
            $this->createdInvoices[$invoiceId]
        );
    }

    public function getClient(int $clientId): array
    {
        if ($this->forceError !== null) {
            throw new WhmcsApiException($this->forceError);
        }

        // Return a fake client
        return [
            'result' => 'success',
            'clientid' => $clientId,
            'firstname' => 'Test',
            'lastname' => 'Client',
            'email' => 'test@example.com',
            'status' => 'Active',
        ];
    }
}
