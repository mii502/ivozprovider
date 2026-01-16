<?php

declare(strict_types=1);

namespace Ivoz\Provider\Infrastructure\Api\Whmcs;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * HTTP client for WHMCS API integration
 *
 * Handles communication with WHMCS for invoice creation and management.
 * Uses WHMCS API identifier/secret authentication.
 *
 * @see https://developers.whmcs.com/api/api-index/
 */
class WhmcsApiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiUrl,
        private string $identifier,
        private string $secret
    ) {
    }

    /**
     * Create an invoice in WHMCS
     *
     * @param int $clientId WHMCS client ID
     * @param string $description Invoice line item description
     * @param float $amount Invoice amount
     * @param \DateTime $dueDate Invoice due date
     * @param string $notes Internal notes (used for invoice identification: IvozProvider:{invoice_id})
     * @param bool $sendEmail Whether to send invoice notification email
     * @param string|null $paymentMethod Payment method (default: banktransfer)
     *
     * @return int WHMCS invoice ID
     *
     * @throws WhmcsApiException
     */
    public function createInvoice(
        int $clientId,
        string $description,
        float $amount,
        \DateTime $dueDate,
        string $notes,
        bool $sendEmail = true,
        ?string $paymentMethod = 'banktransfer'
    ): int {
        $this->logger->info(sprintf(
            'Creating WHMCS invoice for client %d: %s (%.2f)',
            $clientId,
            $description,
            $amount
        ));

        $response = $this->request('CreateInvoice', [
            'userid' => $clientId,
            'status' => 'Unpaid',
            'sendinvoice' => $sendEmail ? '1' : '0',
            'paymentmethod' => $paymentMethod ?? 'banktransfer',
            'date' => (new \DateTime())->format('Y-m-d'),
            'duedate' => $dueDate->format('Y-m-d'),
            'itemdescription1' => $description,
            'itemamount1' => number_format($amount, 2, '.', ''),
            'itemtaxed1' => '0',
            'notes' => $notes,
        ]);

        if ($response['result'] !== 'success') {
            $errorMessage = $response['message'] ?? 'Unknown WHMCS API error';
            $this->logger->error(sprintf(
                'WHMCS CreateInvoice failed for client %d: %s',
                $clientId,
                $errorMessage
            ));
            throw new WhmcsApiException($errorMessage, $response);
        }

        $whmcsInvoiceId = (int) $response['invoiceid'];

        $this->logger->info(sprintf(
            'WHMCS invoice #%d created for client %d',
            $whmcsInvoiceId,
            $clientId
        ));

        return $whmcsInvoiceId;
    }

    /**
     * Get invoice details from WHMCS
     *
     * @param int $invoiceId WHMCS invoice ID
     *
     * @return array Invoice data
     *
     * @throws WhmcsApiException
     */
    public function getInvoice(int $invoiceId): array
    {
        $response = $this->request('GetInvoice', [
            'invoiceid' => $invoiceId,
        ]);

        if ($response['result'] !== 'success') {
            $errorMessage = $response['message'] ?? 'Unknown WHMCS API error';
            throw new WhmcsApiException($errorMessage, $response);
        }

        return $response;
    }

    /**
     * Get client details from WHMCS
     *
     * @param int $clientId WHMCS client ID
     *
     * @return array Client data
     *
     * @throws WhmcsApiException
     */
    public function getClient(int $clientId): array
    {
        $response = $this->request('GetClientsDetails', [
            'clientid' => $clientId,
        ]);

        if ($response['result'] !== 'success') {
            $errorMessage = $response['message'] ?? 'Unknown WHMCS API error';
            throw new WhmcsApiException($errorMessage, $response);
        }

        return $response;
    }

    /**
     * Send API request to WHMCS
     *
     * @param string $action WHMCS API action
     * @param array $params Additional parameters
     *
     * @return array API response
     *
     * @throws WhmcsApiException
     */
    private function request(string $action, array $params): array
    {
        $params['identifier'] = $this->identifier;
        $params['secret'] = $this->secret;
        $params['action'] = $action;
        $params['responsetype'] = 'json';

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'body' => $params,
                'timeout' => 30,
            ]);

            $content = $response->getContent();
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new WhmcsApiException(
                    'Invalid JSON response from WHMCS API: ' . json_last_error_msg(),
                    ['raw_response' => $content]
                );
            }

            return $data;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error(sprintf(
                'WHMCS API transport error: %s',
                $e->getMessage()
            ));
            throw new WhmcsApiException(
                'WHMCS API connection error: ' . $e->getMessage(),
                [],
                $e
            );
        }
    }
}
