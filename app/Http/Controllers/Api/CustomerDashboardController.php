<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerDashboardController extends Controller
{
    protected InterestService $interestService;

    public function __construct(InterestService $interestService)
    {
        $this->interestService = $interestService;
    }

    /**
     * Get customer credit overview
     */
    public function getCreditOverview(Request $request)
    {
        $customer = $this->getCustomer($request);
        $customer->updateBalances();

        $cacheKey = 'customer_credit_' . $customer->id;
        $data = Cache::remember($cacheKey, 60, function () use ($customer) {
            return [
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_balance' => $customer->available_balance,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get all customer invoices
     */
    public function getInvoices(Request $request)
    {
        $customer = $this->getCustomer($request);
        $cacheKey = 'customer_invoices_' . $customer->id;

        $invoices = Cache::remember($cacheKey, 120, function () use ($customer) {
            $this->interestService->updateAllInvoices();

            return $customer->invoices()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'status' => $invoice->status,
                    'principal_amount' => $invoice->principal_amount,
                    'interest_amount' => $invoice->interest_amount,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'remaining_balance' => $invoice->remaining_balance,
                    'supplier_name' => $invoice->supplier_name,
                    'months_overdue' => $invoice->months_overdue,
                ];
            });
        });

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get single invoice details
     */
    public function getInvoice(Request $request, $invoiceId)
    {
        $customer = $this->getCustomer($request);
        
        $invoice = $customer->invoices()
            ->where('invoice_id', $invoiceId)
            ->firstOrFail();

        $this->interestService->updateInvoiceStatus($invoice);
        $invoice->refresh();

        return response()->json([
            'invoice' => [
                'invoice_id' => $invoice->invoice_id,
                'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'grace_period_end_date' => $invoice->grace_period_end_date->format('Y-m-d'),
                'status' => $invoice->status,
                'principal_amount' => $invoice->principal_amount,
                'interest_amount' => $invoice->interest_amount,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'remaining_balance' => $invoice->remaining_balance,
                'supplier_name' => $invoice->supplier_name,
                'months_overdue' => $invoice->months_overdue,
            ],
        ]);
    }

    /**
     * Get repayment bank account details
     */
    public function getRepaymentAccount(Request $request)
    {
        $customer = $this->getCustomer($request);

        return response()->json([
            'virtual_account_number' => $customer->virtual_account_number,
            'virtual_account_bank' => $customer->virtual_account_bank,
        ]);
    }

    /**
     * Get customer transactions
     */
    public function getTransactions(Request $request)
    {
        $customer = $this->getCustomer($request);

        $transactions = $customer->transactions()
            ->with(['business', 'invoice'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Get customer profile
     */
    public function getProfile(Request $request)
    {
        $customer = $this->getCustomer($request);
        $customer->updateBalances();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_balance' => $customer->available_balance,
                'status' => $customer->status,
            ],
        ]);
    }

    /**
     * Helper to get customer from request
     */
    protected function getCustomer(Request $request): Customer
    {
        // In production, get from authenticated user or token
        $customerId = $request->input('customer_id') ?? $request->user()?->id;
        
        if (!$customerId) {
            abort(401, 'Unauthenticated');
        }

        return Customer::findOrFail($customerId);
    }
}

