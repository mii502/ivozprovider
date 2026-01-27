<?php

declare(strict_types=1);

/**
 * Somleng Verify API Client
 * Server path: /opt/irontec/ivozprovider/library/Ivoz/Provider/Domain/Service/Byon/SomlengVerifyClient.php
 * Server: vm-ivozprovider-lab (185.16.41.36)
 * Module: ivozprovider-byon
 */

namespace Ivoz\Provider\Domain\Service\Byon;

use Psr\Log\LoggerInterface;

/**
 * Client for Somleng Verify API (Twilio-compatible OTP verification)
 *
 * API Documentation: https://www.somleng.org/docs/verify
 *
 * Usage:
 * - sendVerification($phoneNumber) - Send OTP via SMS
 * - checkVerification($phoneNumber, $code) - Verify the OTP code
 */
class SomlengVerifyClient
{
    private string $accountSid;
    private string $authToken;
    private string $serviceSid;
    private string $verifyApiUrl;

    public function __construct(
        private LoggerInterface $logger,
        string $accountSid,
        string $authToken,
        string $serviceSid,
        string $verifyApiUrl = 'https://verify.cpaas.voip.ing'
    ) {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->serviceSid = $serviceSid;
        $this->verifyApiUrl = rtrim($verifyApiUrl, '/');
    }

    /**
     * Send OTP verification code via SMS
     *
     * @param string $phoneNumber E.164 format phone number (e.g., +34612345678)
     * @return array{success: bool, verificationSid: ?string, error: ?string}
     */
    public function sendVerification(string $phoneNumber): array
    {
        $url = sprintf(
            '%s/v2/Services/%s/Verifications',
            $this->verifyApiUrl,
            $this->serviceSid
        );

        try {
            $this->logger->info('Somleng: Sending verification', [
                'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
            ]);

            $response = $this->makeRequest('POST', $url, [
                'To' => $phoneNumber,
                'Channel' => 'sms',
            ]);

            $statusCode = $response['statusCode'];
            $data = $response['data'];

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Somleng: Verification sent', [
                    'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
                    'sid' => $data['sid'] ?? 'unknown',
                    'status' => $data['status'] ?? 'unknown',
                ]);

                return [
                    'success' => true,
                    'verificationSid' => $data['sid'] ?? null,
                    'error' => null,
                ];
            }

            $errorMessage = $data['message'] ?? 'Unknown error';
            $this->logger->warning('Somleng: Verification failed', [
                'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
                'statusCode' => $statusCode,
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'verificationSid' => null,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Somleng: Exception during verification', [
                'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'verificationSid' => null,
                'error' => 'Service temporarily unavailable',
            ];
        }
    }

    /**
     * Check/verify OTP code
     *
     * @param string $phoneNumber E.164 format phone number
     * @param string $code 6-digit verification code
     * @return array{success: bool, approved: bool, error: ?string}
     */
    public function checkVerification(string $phoneNumber, string $code): array
    {
        $url = sprintf(
            '%s/v2/Services/%s/VerificationCheck',
            $this->verifyApiUrl,
            $this->serviceSid
        );

        try {
            $this->logger->info('Somleng: Checking verification code', [
                'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
            ]);

            $response = $this->makeRequest('POST', $url, [
                'To' => $phoneNumber,
                'Code' => $code,
            ]);

            $statusCode = $response['statusCode'];
            $data = $response['data'];

            if ($statusCode >= 200 && $statusCode < 300) {
                $status = $data['status'] ?? 'unknown';
                $approved = ($status === 'approved');

                $this->logger->info('Somleng: Verification check result', [
                    'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
                    'status' => $status,
                    'approved' => $approved,
                ]);

                return [
                    'success' => true,
                    'approved' => $approved,
                    'error' => $approved ? null : 'Invalid verification code',
                ];
            }

            $errorMessage = $data['message'] ?? 'Verification check failed';
            $this->logger->warning('Somleng: Verification check failed', [
                'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
                'statusCode' => $statusCode,
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'approved' => false,
                'error' => $errorMessage,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Somleng: Exception during verification check', [
                'phoneNumber' => $this->maskPhoneNumber($phoneNumber),
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'approved' => false,
                'error' => 'Service temporarily unavailable',
            ];
        }
    }

    /**
     * Make HTTP request using cURL
     *
     * @param string $method HTTP method
     * @param string $url URL to request
     * @param array $body Request body for POST
     * @return array{statusCode: int, data: array}
     * @throws \RuntimeException on cURL error
     */
    private function makeRequest(string $method, string $url, array $body = []): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERPWD, $this->accountSid . ':' . $this->authToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        $data = json_decode($response, true) ?? [];

        return [
            'statusCode' => $statusCode,
            'data' => $data,
        ];
    }

    /**
     * Mask phone number for logging (show only last 4 digits)
     */
    private function maskPhoneNumber(string $phoneNumber): string
    {
        $length = strlen($phoneNumber);
        if ($length <= 4) {
            return $phoneNumber;
        }
        return str_repeat('*', $length - 4) . substr($phoneNumber, -4);
    }
}
