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

    public function createVirtualAccount(Customer $customer): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Paystack is not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file.');
        }

        try {
            $customerCode = $this->getOrCreatePaystackCustomer($customer);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/dedicated_account/assign", [
                'customer' => $customerCode,
                'preferred_bank' => 'wema-bank',
                'email' => $customer->email,
                'first_name' => $customer->business_name,
                'last_name' => $customer->business_name,
                'phone' => $customer->phone ?? '',
            ]);

            $data = $response->json();

            if (!$response->successful() || !isset($data['status']) || !$data['status']) {
                Log::error('Paystack virtual account creation failed', [
                    'customer_id' => $customer->id,
                    'customer_code' => $customerCode,
                    'response' => $data,
                ]);
                throw new \Exception('Failed to create virtual account: ' . ($data['message'] ?? 'Unknown error'));
            }

            Log::info('Paystack dedicated account response', [
                'customer_id' => $customer->id,
                'response_structure' => array_keys($data['data'] ?? []),
                'full_response' => $data,
            ]);

            $accountDetails = $data['data']['dedicated_account'] ?? $data['data'] ?? null;
            
            if (!$accountDetails || !isset($accountDetails['account_number'])) {
                if (isset($data['message']) && str_contains(strtolower($data['message']), 'in progress')) {
                    Log::info('Paystack virtual account assignment in progress, fetching account details', [
                        'customer_id' => $customer->id,
                        'customer_code' => $customerCode,
                    ]);
                    
                    sleep(3);
                    
                    $accountDetails = $this->fetchDedicatedAccountByCustomer($customerCode);
                    
                    if (!$accountDetails || !isset($accountDetails['account_number'])) {
                        Log::warning('Paystack virtual account still not available after wait', [
                            'customer_id' => $customer->id,
                            'customer_code' => $customerCode,
                        ]);
                        throw new \Exception('Virtual account is being created. Please check back in a few moments.');
                    }
                } else {
                    Log::error('Paystack virtual account response missing account details', [
                        'customer_id' => $customer->id,
                        'response' => $data,
                    ]);
                    throw new \Exception('Virtual account details not found in response');
                }
            }

            return [
                'account_number' => $accountDetails['account_number'] ?? null,
                'account_name' => $accountDetails['account_name'] ?? null,
                'bank' => $accountDetails['bank']['name'] ?? null,
                'bank_code' => $accountDetails['bank']['id'] ?? null,
                'paystack_customer_code' => $customerCode,
                'paystack_dedicated_account_id' => $accountDetails['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Paystack virtual account creation exception', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function getOrCreatePaystackCustomer(Customer $customer): string
    {
        $firstResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get("{$this->baseUrl}/customer/{$customer->email}");

        $firstData = $firstResponse->json();

        if ($firstResponse->successful() && isset($firstData['status']) && $firstData['status'] && isset($firstData['data']['customer_code'])) {
            $existingCustomer = $firstData['data'];
            $customerCode = $existingCustomer['customer_code'];

            $updateResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->put("{$this->baseUrl}/customer/{$customerCode}", [
                'first_name' => $customer->business_name,
                'last_name' => $customer->business_name,
                'phone' => $customer->phone ?? '',
            ]);

            if (!$updateResponse->successful()) {
                Log::warning('Failed to update Paystack customer details', [
                    'customer_id' => $customer->id,
                    'customer_code' => $customerCode,
                    'response' => $updateResponse->json(),
                ]);
            } else {
                Log::info('Paystack customer details updated', [
                    'customer_id' => $customer->id,
                    'customer_code' => $customerCode,
                    'first_name' => $customer->business_name,
                ]);
            }

            Log::info('Paystack customer already exists', [
                'customer_id' => $customer->id,
                'customer_code' => $customerCode,
            ]);
            return $customerCode;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/customer", [
            'email' => $customer->email,
            'first_name' => $customer->business_name,
            'last_name' => $customer->business_name,
            'phone' => $customer->phone ?? '',
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['status']) || !$data['status']) {
            Log::error('Paystack customer creation failed', [
                'customer_id' => $customer->id,
                'response' => $data,
            ]);
            throw new \Exception('Failed to create Paystack customer: ' . ($data['message'] ?? 'Unknown error'));
        }

        Log::info('Paystack customer created successfully', [
            'customer_id' => $customer->id,
            'customer_code' => $data['data']['customer_code'],
        ]);
        return $data['data']['customer_code'];
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

    /**
     * Fetch dedicated account by customer code
     */
    public function fetchDedicatedAccountByCustomer(string $customerCode): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->get("{$this->baseUrl}/dedicated_account", [
                'customer' => $customerCode,
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && $data['status'] && isset($data['data'])) {
                $accounts = $data['data'];
                
                if (is_array($accounts) && count($accounts) > 0) {
                    $account = $accounts[0];
                    return $account;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Paystack fetch dedicated account by customer exception', [
                'customer_code' => $customerCode,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

