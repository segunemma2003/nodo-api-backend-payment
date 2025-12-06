<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\PaymentService;
use App\Services\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvoiceCheckoutController extends Controller
{
    protected PaymentService $paymentService;
    protected InterestService $interestService;

    public function __construct(PaymentService $paymentService, InterestService $interestService)
    {
        $this->paymentService = $paymentService;
        $this->interestService = $interestService;
    }

    /**
     * Get invoice details by slug (public endpoint)
     */
    public function getInvoiceBySlug($slug)
    {
        $invoice = Invoice::with(['customer', 'supplier'])
            ->where('slug', $slug)
            ->firstOrFail();

        if ($invoice->is_used) {
            return response()->json([
                'message' => 'This invoice link has already been used',
                'invoice' => [
                    'invoice_id' => $invoice->invoice_id,
                    'status' => $invoice->status,
                ],
            ], 400);
        }

        $invoice->load('transactions');

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'invoice_id' => $invoice->invoice_id,
                'amount' => $invoice->total_amount,
                'remaining_balance' => $invoice->remaining_balance,
                'status' => $invoice->status,
                'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                'supplier' => [
                    'id' => $invoice->supplier?->id,
                    'business_name' => $invoice->supplier?->business_name ?? $invoice->supplier_name,
                ],
                'description' => $invoice->getDescription(),
                'items' => $invoice->getItems(),
            ],
        ]);
    }

    /**
     * Process payment via invoice link
     */
    public function payInvoice(Request $request, $slug)
    {
        $request->validate([
            'account_number' => 'required|string|digits:16|exists:customers,account_number',
            'cvv' => 'required|string|size:3',
            'pin' => 'required|string|size:4',
        ]);

        $invoice = Invoice::where('slug', $slug)
            ->firstOrFail();

        if ($invoice->is_used) {
            return response()->json([
                'message' => 'This invoice link has already been used',
            ], 400);
        }

        $customer = Customer::where('account_number', $request->account_number)->firstOrFail();

        // Check approval status - customer must be approved by admin
        if ($customer->approval_status !== 'approved') {
            return response()->json([
                'message' => 'Your account is pending approval. Please wait for admin approval before making payments.',
            ], 400);
        }

        if ($customer->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active',
            ], 400);
        }

        if (!$customer->verifyCvv($request->cvv)) {
            return response()->json([
                'message' => 'Invalid CVV',
            ], 400);
        }

        if (!$customer->verifyPinForPayment($request->pin)) {
            return response()->json([
                'message' => 'Invalid PIN. Please use your payment PIN (not the default 0000)',
            ], 400);
        }

        // If invoice has a customer_id, verify it matches
        if ($invoice->customer_id && $invoice->customer_id !== $customer->id) {
            return response()->json([
                'message' => 'This invoice does not belong to the provided account',
            ], 400);
        }

        // If invoice was created from business_customer and not linked yet, we'll link it during payment
        $businessCustomer = $invoice->businessCustomer;

        if ($invoice->status === 'paid') {
            $invoice->is_used = true;
            $invoice->save();
            return response()->json([
                'message' => 'Invoice is already paid',
                'invoice' => $invoice,
            ], 400);
        }

        // Calculate interest BEFORE getting payment amount to ensure it's included
        // Interest is always calculated (3.5% base), even if due_date is null
        if ($invoice->status !== 'paid') {
            $this->interestService->updateInvoiceStatus($invoice);
            $invoice->refresh();
        }

        // Load supplier relationship to ensure it's available
        $invoice->load('supplier');

        $paymentAmount = $invoice->remaining_balance;

        // Link business customer to main customer if invoice was from business customer
        if ($businessCustomer && !$businessCustomer->isLinked()) {
            $businessCustomer->linkToCustomer($customer);
        }
        
        // Ensure invoice is linked to customer (important for showing in customer's invoice list)
        if (!$invoice->customer_id) {
            $invoice->customer_id = $customer->id;
            $invoice->save();
        }

        // Load supplier relationship before processing payment
        $invoice->load('supplier');

        // Process payment for this specific invoice
        // Invoice is now linked to customer, so it will appear in customer's invoice list
        $this->paymentService->processInvoicePayment($customer, $invoice, $paymentAmount);

        $invoice->refresh();
        $invoice->is_used = true;
        $invoice->save();

        return response()->json([
            'message' => 'Payment processed successfully',
            'invoice' => [
                'id' => $invoice->id,
                'invoice_id' => $invoice->invoice_id,
                'status' => $invoice->status,
                'paid_amount' => $invoice->paid_amount,
                'remaining_balance' => $invoice->remaining_balance,
            ],
        ]);
    }
}

