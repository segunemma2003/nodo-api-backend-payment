<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Customer;
use App\Services\PaymentService;
use App\Services\InterestService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class InvoiceCheckoutController extends Controller
{
    protected PaymentService $paymentService;
    protected InterestService $interestService;
    protected WebhookService $webhookService;

    public function __construct(PaymentService $paymentService, InterestService $interestService, WebhookService $webhookService)
    {
        $this->paymentService = $paymentService;
        $this->interestService = $interestService;
        $this->webhookService = $webhookService;
    }

    /**
     * Get invoice details by slug (public endpoint)
     */
    public function getInvoiceBySlug($slug)
    {
        $invoice = Invoice::with(['customer', 'supplier', 'businessCustomer', 'businessCustomer.linkedCustomer'])
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

        // Check if payment link has expired (30 minutes)
        if ($invoice->isSlugExpired()) {
            return response()->json([
                'message' => 'This payment link has expired. Please request a new payment link from the business.',
                'invoice' => [
                    'invoice_id' => $invoice->invoice_id,
                    'status' => $invoice->status,
                ],
                'expired_at' => $invoice->slug_expires_at ? $invoice->slug_expires_at->toIso8601String() : null,
            ], 400);
        }

        $invoice->load('transactions');

        // Update invoice status to get current interest calculation
        $this->interestService->updateInvoiceStatus($invoice);
        $invoice->refresh();

        // Calculate interest information ONLY if customer has an FSCredit account
        $interestInfo = null;
        $customer = null;
        
        // Check if invoice has a direct customer or through businessCustomer
        if ($invoice->customer) {
            $customer = $invoice->customer;
        } elseif ($invoice->businessCustomer && $invoice->businessCustomer->linkedCustomer) {
            $customer = $invoice->businessCustomer->linkedCustomer;
        }
        
        // Only return interest info if customer has an FSCredit account
        if ($customer) {
            $paymentPlanDuration = $invoice->payment_plan_duration ?? $customer->payment_plan_duration ?? 6;
            $monthlyInterestRate = 0.035; // 3.5%
            
            // Calculate upfront interest: 3.5% * payment_plan_duration months * principal_amount
            $upfrontInterestAmount = $invoice->principal_amount * $monthlyInterestRate * $paymentPlanDuration;
            $totalInterestRate = $monthlyInterestRate * $paymentPlanDuration * 100; // Convert to percentage
            
            $interestInfo = [
                'has_account' => true,
                'payment_plan_duration_months' => $paymentPlanDuration,
                'interest_rate_per_month' => $monthlyInterestRate * 100, // 3.5%
                'total_interest_rate' => round($totalInterestRate, 2), // e.g., 21% for 6 months
                'principal_amount' => (string) number_format($invoice->principal_amount, 2, '.', ''),
                'interest_amount' => (string) number_format($upfrontInterestAmount, 2, '.', ''),
                'total_amount_with_interest' => (string) number_format($invoice->principal_amount + $upfrontInterestAmount, 2, '.', ''),
                'note' => "If you pay using FSCredit, an interest of " . round($totalInterestRate, 2) . "% ({$paymentPlanDuration} months × 3.5%) will be added to the principal amount.",
            ];
        }
        // If no customer account, don't include fscredit_payment_info at all

        $response = [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_id' => $invoice->invoice_id,
                'amount' => $invoice->total_amount,
                'remaining_balance' => $invoice->remaining_balance,
                'status' => $invoice->status,
                'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                'payment_plan_duration' => $invoice->payment_plan_duration,
                'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                'supplier' => [
                    'id' => $invoice->supplier?->id,
                    'business_name' => $invoice->supplier?->business_name ?? $invoice->supplier_name,
                ],
                'description' => $invoice->getDescription(),
                'items' => $invoice->getItems(),
            ],
        ];
        
        // Only include fscredit_payment_info if customer has an account
        if ($interestInfo) {
            $response['fscredit_payment_info'] = $interestInfo;
        }

        // Add business customer (billed customer) details if available
        if ($invoice->businessCustomer) {
            $response['billed_customer'] = [
                'id' => $invoice->businessCustomer->id,
                'business_name' => $invoice->businessCustomer->business_name,
                'contact_name' => $invoice->businessCustomer->contact_name,
                'contact_email' => $invoice->businessCustomer->contact_email,
                'contact_phone' => $invoice->businessCustomer->contact_phone,
                'address' => $invoice->businessCustomer->address,
                'has_fscredit_account' => $invoice->businessCustomer->isLinked(),
            ];
        }

        return response()->json($response);
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

        // Check if payment link has expired (30 minutes)
        if ($invoice->isSlugExpired()) {
            return response()->json([
                'message' => 'This payment link has expired. Please request a new payment link from the business.',
                'expired_at' => $invoice->slug_expires_at ? $invoice->slug_expires_at->toIso8601String() : null,
            ], 400);
        }

        $customer = Customer::where('account_number', $request->account_number)->firstOrFail();

        // Check approval status - customer must be approved by admin
        if ($customer->approval_status !== 'approved') {
            // Send webhook for payment failure
            $this->sendPaymentFailureWebhook($invoice, 'Account pending approval');
            return response()->json([
                'message' => 'Your account is pending approval. Please wait for admin approval before making payments.',
            ], 400);
        }

        if ($customer->status !== 'active') {
            // Send webhook for payment failure
            $this->sendPaymentFailureWebhook($invoice, 'Account not active');
            return response()->json([
                'message' => 'Your account is not active',
            ], 400);
        }

        if (!$customer->verifyCvv($request->cvv)) {
            // Send webhook for payment failure
            $this->sendPaymentFailureWebhook($invoice, 'Invalid CVV');
            return response()->json([
                'message' => 'Invalid CVV',
            ], 400);
        }

        if (!$customer->verifyPinForPayment($request->pin)) {
            // Send webhook for payment failure
            $this->sendPaymentFailureWebhook($invoice, 'Invalid PIN');
            return response()->json([
                'message' => 'Invalid PIN. Please use your payment PIN (not the default 0000)',
            ], 400);
        }

        $businessCustomer = $invoice->businessCustomer;

        if ($invoice->customer_id && $invoice->customer_id !== $customer->id) {
            if (!$invoice->business_customer_id) {
                return response()->json([
                    'message' => 'This invoice does not belong to the provided account',
                ], 400);
            }
        }

        if ($invoice->status === 'paid') {
            $invoice->is_used = true;
            $invoice->save();
            return response()->json([
                'message' => 'Invoice is already paid',
                'invoice' => $invoice,
            ], 400);
        }

        if ($invoice->status !== 'paid') {
            $this->interestService->updateInvoiceStatus($invoice);
            $invoice->refresh();
        }

        // Load supplier relationship to ensure it's available
        $invoice->load('supplier');

        $paymentAmount = $invoice->remaining_balance;

        if ($businessCustomer && !$businessCustomer->isLinked()) {
            $businessCustomer->linkToCustomer($customer);
        }
        
        $invoice->customer_id = $customer->id;
        $invoice->save();

        // Load supplier relationship before processing payment
        $invoice->load('supplier');

        // Process payment for this specific invoice
        // Invoice is now linked to customer, so it will appear in customer's invoice list
        try {
            $this->paymentService->processInvoicePayment($customer, $invoice, $paymentAmount);
            
            $invoice->refresh();
            $invoice->is_used = true;
            $invoice->save();
            
            // Webhook for success is sent in PaymentService::processInvoicePayment()
        } catch (\Exception $e) {
            // Send webhook for payment failure
            $this->sendPaymentFailureWebhook($invoice, $e->getMessage());
            throw $e;
        }

        // Get redirect URL from invoice or business
        $redirectUrl = $invoice->redirect_url;
        if (!$redirectUrl && $invoice->supplier) {
            $redirectUrl = $invoice->supplier->redirect_url;
        }

        return response()->json([
            'message' => 'Payment processed successfully',
            'invoice' => [
                'id' => $invoice->id,
                'invoice_id' => $invoice->invoice_id,
                'status' => $invoice->status,
                'paid_amount' => $invoice->paid_amount,
                'remaining_balance' => $invoice->remaining_balance,
            ],
            'redirect_url' => $redirectUrl, // Frontend will redirect to this URL after payment
        ]);
    }

    /**
     * Send webhook for payment failure
     */
    private function sendPaymentFailureWebhook(Invoice $invoice, string $reason): void
    {
        if (!$invoice->supplier || !$invoice->supplier->webhook_url) {
            return;
        }

        try {
            $invoice->load('businessCustomer');
            $businessCustomer = $invoice->businessCustomer;
            
            $this->webhookService->sendWebhook($invoice->supplier, 'payment.failed', [
                'invoice_id' => $invoice->invoice_id,
                'slug' => $invoice->slug,
                'status' => 'failed',
                'amount' => $invoice->principal_amount,
                'reason' => $reason,
                'customer_email' => $businessCustomer->contact_email ?? null,
                'customer_name' => $businessCustomer->business_name ?? null,
                'failed_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send payment failure webhook: ' . $e->getMessage(), [
                'business_id' => $invoice->supplier_id,
                'invoice_id' => $invoice->id,
            ]);
        }
    }
}

