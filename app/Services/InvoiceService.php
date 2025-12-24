<?php

namespace App\Services;

use App\Models\BusinessCustomer;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payout;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    protected InterestService $interestService;
    protected PaymentService $paymentService;

    public function __construct(InterestService $interestService, PaymentService $paymentService)
    {
        $this->interestService = $interestService;
        $this->paymentService = $paymentService;
    }

    public function createInvoice(
        Customer $customer,
        float $amount,
        string $supplierName = 'Foodstuff Store',
        ?Carbon $purchaseDate = null,
        ?Carbon $dueDate = null,
        ?int $supplierId = null
    ): Invoice {
        DB::beginTransaction();

        try {
            $purchaseDate = $purchaseDate ?? Carbon::now();
            $paymentPlanDuration = $customer->payment_plan_duration ?? 6;
            
            // Calculate due_date from purchase_date + payment_plan_duration months
            if (!$dueDate) {
                $dueDate = $purchaseDate->copy()->addMonths($paymentPlanDuration);
            }

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'principal_amount' => $amount,
                'interest_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'remaining_balance' => $amount,
                'purchase_date' => $purchaseDate,
                'payment_plan_duration' => $paymentPlanDuration,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            // Do NOT update customer balances or process payout when invoice is created
            // Balance will only be deducted when customer actually pays via checkout
            // Payout will be processed when payment is made

            DB::commit();

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function hasAvailableCredit(Customer $customer, float $amount): bool
    {
        $customer->updateBalances();
        return $customer->available_balance >= $amount && $customer->status === 'active';
    }

    /**
     * Create invoice for a business customer
     * This invoice will be linked to the main customer when payment is made
     */
    public function createInvoiceForBusinessCustomer(
        BusinessCustomer $businessCustomer,
        float $amount,
        string $supplierName = 'Foodstuff Store',
        ?Carbon $purchaseDate = null,
        ?Carbon $dueDate = null,
        ?int $supplierId = null
    ): Invoice {
        DB::beginTransaction();

        try {
            $purchaseDate = $purchaseDate ?? Carbon::now();
            
            // Get payment plan duration from linked customer if available, otherwise default to 6
            $paymentPlanDuration = 6;
            if ($businessCustomer->linked_customer_id) {
                $customer = Customer::find($businessCustomer->linked_customer_id);
                if ($customer) {
                    $paymentPlanDuration = $customer->payment_plan_duration ?? 6;
                }
            }
            
            // Calculate due_date from purchase_date + payment_plan_duration months
            if (!$dueDate) {
                $dueDate = $purchaseDate->copy()->addMonths($paymentPlanDuration);
            }

            // Generate slug for payment link
            $slug = Invoice::generateSlug();

            $invoice = Invoice::create([
                'customer_id' => $businessCustomer->linked_customer_id, // Will be null if not linked yet
                'business_customer_id' => $businessCustomer->id,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'principal_amount' => $amount,
                'interest_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => 0,
                'remaining_balance' => $amount,
                'purchase_date' => $purchaseDate,
                'payment_plan_duration' => $paymentPlanDuration,
                'due_date' => $dueDate,
                'slug' => $slug,
                'status' => 'pending',
            ]);

            // Do NOT update customer balances or process payout when invoice is created
            // Balance will only be deducted when customer actually pays via checkout
            // Payout will be processed when payment is made

            DB::commit();

            return $invoice;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Business customer invoice creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

