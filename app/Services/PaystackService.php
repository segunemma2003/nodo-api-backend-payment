<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
        $this->publicKey = config('services.paystack.public_key');
    }

    /**
     * Check if Paystack is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && !empty($this->publicKey);
    }

    /**
     * Create a dedicated virtual account for a customer
     */
    public function createVirtualAccount(Customer $customer): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Paystack is not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/dedicated_account", [
                'email' => $customer->email,
                'first_name' => $customer->business_name,
                'last_name' => $customer->business_name,
                'phone' => $customer->phone ?? '',
                'preferred_bank' => '', // Let Paystack assign a bank
                'country' => 'NG', // Nigeria
            ]);

            $data = $response->json();

            if (!$response->successful() || !isset($data['status']) || !$data['status']) {
                Log::error('Paystack virtual account creation failed', [
                    'customer_id' => $customer->id,
                    'response' => $data,
                ]);
                throw new \Exception('Failed to create virtual account: ' . ($data['message'] ?? 'Unknown error'));
            }

            // Extract virtual account details
            $accountDetails = $data['data']['dedicated_account'] ?? null;
            
            if (!$accountDetails) {
                throw new \Exception('Virtual account details not found in response');
            }

            return [
                'account_number' => $accountDetails['account_number'] ?? null,
                'account_name' => $accountDetails['account_name'] ?? null,
                'bank' => $accountDetails['bank']['name'] ?? null,
                'bank_code' => $accountDetails['bank']['id'] ?? null,
                'paystack_customer_code' => $data['data']['customer']['customer_code'] ?? null,
                'paystack_dedicated_account_id' => $data['data']['dedicated_account']['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Paystack virtual account creation exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Verify webhook signature from Paystack
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $hash = hash_hmac('sha512', $payload, $this->secretKey);
        return hash_equals($hash, $signature);
    }

    /**
     * Get virtual account details
     */
    public function getVirtualAccount(string $dedicatedAccountId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/dedicated_account/{$dedicatedAccountId}");

            $data = $response->json();

            if (!$response->successful() || !isset($data['status']) || !$data['status']) {
                throw new \Exception('Failed to get virtual account: ' . ($data['message'] ?? 'Unknown error'));
            }

            return $data['data'] ?? [];
        } catch (\Exception $e) {
            Log::error('Paystack get virtual account exception', [
                'dedicated_account_id' => $dedicatedAccountId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

