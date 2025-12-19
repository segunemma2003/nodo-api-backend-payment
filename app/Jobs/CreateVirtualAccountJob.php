<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\PaystackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateVirtualAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Customer $customer;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 120, 300]; // 1 minute, 2 minutes, 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * Execute the job.
     */
    public function handle(PaystackService $paystackService): void
    {
        // Check if customer already has a virtual account
        if (!empty($this->customer->virtual_account_number)) {
            Log::info('Customer already has virtual account, skipping job', [
                'customer_id' => $this->customer->id,
                'virtual_account_number' => $this->customer->virtual_account_number,
            ]);
            return;
        }

        // Check if Paystack is configured
        if (!$paystackService->isConfigured()) {
            Log::warning('Paystack not configured, cannot create virtual account', [
                'customer_id' => $this->customer->id,
            ]);
            return;
        }

        try {
            // Create virtual account via Paystack
            $virtualAccount = $paystackService->createVirtualAccount($this->customer);

            // Refresh customer to get latest data
            $this->customer->refresh();

            // Double-check customer doesn't have virtual account (race condition)
            if (empty($this->customer->virtual_account_number)) {
                // Update customer with virtual account details
                $this->customer->virtual_account_number = $virtualAccount['account_number'] ?? null;
                $this->customer->virtual_account_bank = $virtualAccount['bank'] ?? null;
                $this->customer->paystack_customer_code = $virtualAccount['paystack_customer_code'] ?? $this->customer->paystack_customer_code;
                $this->customer->paystack_dedicated_account_id = $virtualAccount['paystack_dedicated_account_id'] ?? null;
                $this->customer->save();

                Log::info('Virtual account created successfully via queue job', [
                    'customer_id' => $this->customer->id,
                    'virtual_account_number' => $this->customer->virtual_account_number,
                    'virtual_account_bank' => $this->customer->virtual_account_bank,
                ]);
            } else {
                Log::info('Virtual account was created by another process, skipping update', [
                    'customer_id' => $this->customer->id,
                    'existing_virtual_account_number' => $this->customer->virtual_account_number,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create virtual account via queue job', [
                'customer_id' => $this->customer->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CreateVirtualAccountJob failed after all retries', [
            'customer_id' => $this->customer->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally notify admin or customer about the failure
        // You can add notification logic here if needed
    }
}

