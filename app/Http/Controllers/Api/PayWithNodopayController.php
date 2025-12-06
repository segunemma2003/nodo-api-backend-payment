<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Services\InterestService;
use App\Services\PaymentService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PayWithNodopayController extends Controller
{
    protected InvoiceService $invoiceService;
    protected InterestService $interestService;
    protected PaymentService $paymentService;
    protected WebhookService $webhookService;

    public function __construct(InvoiceService $invoiceService, InterestService $interestService, PaymentService $paymentService, WebhookService $webhookService)
    {
        $this->invoiceService = $invoiceService;
        $this->interestService = $interestService;
        $this->paymentService = $paymentService;
        $this->webhookService = $webhookService;
    }

    public function purchaseRequest(Request $request)
    {
        // Authentication handled by middleware

        $request->validate([
            'account_number' => 'required|string|size:16|exists:customers,account_number',
            'customer_email' => 'required|email',
            'cvv' => 'required|string|size:3',
            'pin' => 'required|string|size:4',
            'amount' => 'required|numeric|min:0.01',
            'purchase_date' => 'nullable|date',
            'order_reference' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
            'items.*.uom' => 'nullable|string', // Unit of Measure (e.g., "kg", "pieces", "liters", "boxes")
        ]);

        try {
            $business = $request->input('business');
            
            if (!$business) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business not found',
                ], 401);
            }

            if ($business->approval_status !== 'approved' || $business->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Business is not approved or inactive',
                ], 400);
            }

            $customer = Customer::where('account_number', $request->account_number)->firstOrFail();

            if ($customer->email !== $request->customer_email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer email mismatch',
                ], 400);
            }

            if (!$customer->verifyCvv($request->cvv)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid CVV',
                ], 400);
            }

            if (!$customer->verifyPinForPayment($request->pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid PIN. Please use your payment PIN (not the default 0000)',
                ], 400);
            }

            // Check approval status - customer must be approved by admin
            if ($customer->approval_status !== 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer account is pending approval. Please wait for admin approval before making payments.',
                ], 400);
            }

            if ($customer->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer account is not active',
                ], 400);
            }

            if (!$this->invoiceService->hasAvailableCredit($customer, $request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient credit available',
                    'available_credit' => $customer->available_balance,
                ], 400);
            }

            // Calculate purchase date
            $purchaseDate = $request->purchase_date 
                ? \Carbon\Carbon::parse($request->purchase_date) 
                : \Carbon\Carbon::now();

            // Calculate due_date based on customer's payment plan duration
            // due_date = purchase_date + payment_plan_duration (in months)
            $paymentPlanDuration = $customer->payment_plan_duration ?? 6; // Default to 6 months if not set
            $dueDate = $purchaseDate->copy()->addMonths($paymentPlanDuration);

            $invoice = $this->invoiceService->createInvoice(
                $customer,
                $request->amount,
                $business->business_name,
                $purchaseDate,
                $dueDate,
                $business->id
            );

            // Update invoice status and calculate interest if needed
            // This will set the correct status (pending/in_grace/overdue) and calculate interest
            // Interest (3.5% base) is always added, and remaining_balance includes it
            $this->interestService->updateInvoiceStatus($invoice);
            $invoice->refresh();

            // Ensure invoice status is 'in_grace' (not 'pending') so it affects credit limit
            // Credit purchases should immediately reduce available credit by the full amount (principal + interest)
            if ($invoice->status === 'pending') {
                $invoice->status = 'in_grace';
                // Recalculate remaining_balance to ensure it includes interest
                $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;
                $invoice->save();
            }

            // Refresh invoice to ensure we have the latest remaining_balance
            $invoice->refresh();

            // Update customer balances to deduct from credit limit
            // This will reduce available_balance by the invoice's remaining_balance (principal + interest)
            // The credit limit represents loaned money, so when used, it must be deducted
            $customer->updateBalances();

            // Process payout to business since customer is using credit to make purchase
            // Business should receive payment immediately when credit is used
            if ($invoice->supplier_id && !$invoice->payouts()->exists()) {
                $this->paymentService->processPayout($invoice);
            }

            Transaction::create([
                'customer_id' => $customer->id,
                'business_id' => $business->id,
                'invoice_id' => $invoice->id,
                'type' => 'credit_purchase',
                'amount' => $invoice->principal_amount,
                'status' => 'completed',
                'description' => $request->order_reference ? "Order: {$request->order_reference}" : "Invoice {$invoice->invoice_id}",
                'metadata' => [
                    'order_reference' => $request->order_reference,
                    'items' => $request->items,
                ],
                'processed_at' => now(),
            ]);

            Cache::forget('customer_credit_' . $customer->id);
            Cache::forget('customer_invoices_' . $customer->id);
            Cache::forget('admin_customer_' . $customer->id);

            $this->webhookService->sendWebhook($business, 'invoice.created', [
                'invoice_id' => $invoice->invoice_id,
                'account_number' => $customer->account_number,
                'customer_id' => $customer->id,
                'amount' => $invoice->principal_amount,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                'order_reference' => $request->order_reference,
                'items' => $request->items,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase financed successfully',
                'invoice' => [
                    'invoice_id' => $invoice->invoice_id,
                    'principal_amount' => $invoice->principal_amount,
                    'interest_amount' => $invoice->interest_amount,
                    'total_amount' => $invoice->total_amount,
                    'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                    'grace_period_end_date' => $invoice->grace_period_end_date ? $invoice->grace_period_end_date->format('Y-m-d') : null,
                    'status' => $invoice->status,
                ],
                'order' => [
                    'order_reference' => $request->order_reference,
                    'items' => $request->items,
                ],
                'customer' => [
                    'available_balance' => $customer->fresh()->available_balance,
                    'current_balance' => $customer->fresh()->current_balance,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Purchase request failed: ' . $e->getMessage());
            
            if (isset($business)) {
                $this->webhookService->sendError($business, [
                    'error' => 'Purchase request failed',
                    'message' => $e->getMessage(),
                    'account_number' => $request->account_number ?? null,
                    'amount' => $request->amount ?? null,
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process purchase request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkCredit(Request $request)
    {
        // Authentication handled by middleware

        $request->validate([
            'account_number' => 'required|string|size:16|exists:customers,account_number',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $customer = Customer::where('account_number', $request->account_number)->firstOrFail();
        $customer->updateBalances();

        $hasCredit = $this->invoiceService->hasAvailableCredit($customer, $request->amount);

        return response()->json([
            'success' => true,
            'has_credit' => $hasCredit,
            'available_credit' => $customer->available_balance,
            'current_balance' => $customer->current_balance,
            'credit_limit' => $customer->credit_limit,
        ]);
    }

    public function getCustomerDetails(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string|size:16|exists:customers,account_number',
            'customer_email' => 'nullable|email',
            'customer_phone' => 'nullable|string',
        ]);

        $customer = Customer::where('account_number', $request->account_number)->firstOrFail();

        if ($request->has('customer_email') && $customer->email !== $request->customer_email) {
            return response()->json([
                'success' => false,
                'message' => 'Customer email mismatch',
            ], 400);
        }

        if ($request->has('customer_phone') && $customer->phone !== $request->customer_phone) {
            return response()->json([
                'success' => false,
                'message' => 'Customer phone mismatch',
            ], 400);
        }

        $customer->updateBalances();

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'account_number' => $customer->account_number,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'credit_limit' => $customer->credit_limit,
                'available_balance' => $customer->available_balance,
                'current_balance' => $customer->current_balance,
                'status' => $customer->status,
            ],
        ]);
    }

}

