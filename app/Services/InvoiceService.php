<?php

namespace App\Services;

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
            $dueDate = $dueDate ?? $purchaseDate->copy()->addMonths($customer->payment_plan_duration);

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
}

