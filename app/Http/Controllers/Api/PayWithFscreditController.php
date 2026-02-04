<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Business;
use App\Models\BusinessCustomer;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Services\InterestService;
use App\Services\PaymentService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PayWithFscreditController extends Controller
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

            $purchaseDate = $request->purchase_date 
                ? \Carbon\Carbon::parse($request->purchase_date) 
                : \Carbon\Carbon::now();

            $paymentPlanDuration = $customer->payment_plan_duration ?? 6;
            $dueDate = $purchaseDate->copy()->addMonths($paymentPlanDuration);

            $invoice = $this->invoiceService->createInvoice(
                $customer,
                $request->amount,
                $business->business_name,
                $purchaseDate,
                $dueDate,
                $business->id
            );

            $this->interestService->updateInvoiceStatus($invoice);
            $invoice->refresh();

            if ($invoice->status === 'pending') {
                $invoice->status = 'in_grace';
                $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;
                $invoice->save();
            }

            $invoice->refresh();
            $customer->updateBalances();

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

    /**
     * Create invoice and return payment link
     * Accepts purchase request and returns a payment link that user can visit to complete payment
     */
    public function createInvoiceLink(Request $request)
    {
        // Authentication handled by middleware

        $request->validate([
            'customer_email' => 'required|email',
            'amount' => 'required|numeric|min:0.01',
            'purchase_date' => 'nullable|date',
            'order_reference' => 'nullable|string',
            'callback_url' => 'nullable|url|max:500',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
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

            // Find or create business customer by email
            $businessCustomer = BusinessCustomer::where('business_id', $business->id)
                ->where('contact_email', $request->customer_email)
                ->first();

            if (!$businessCustomer) {
                // Create new business customer
                $businessCustomer = BusinessCustomer::create([
                    'business_id' => $business->id,
                    'business_name' => $request->business_name ?? 'Customer',
                    'contact_email' => $request->customer_email,
                    'contact_name' => $request->contact_name,
                    'contact_phone' => $request->contact_phone,
                    'address' => $request->address,
                    'status' => 'active',
                ]);
            } else {
                // Update existing business customer with provided information (if any)
                $updateData = [];
                if ($request->has('business_name') && $request->business_name) {
                    $updateData['business_name'] = $request->business_name;
                }
                if ($request->has('contact_name') && $request->contact_name) {
                    $updateData['contact_name'] = $request->contact_name;
                }
                if ($request->has('contact_phone') && $request->contact_phone) {
                    $updateData['contact_phone'] = $request->contact_phone;
                }
                if ($request->has('address') && $request->address) {
                    $updateData['address'] = $request->address;
                }
                
                if (!empty($updateData)) {
                    $businessCustomer->update($updateData);
                }

                // Check if customer is active
                if ($businessCustomer->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'The customer account is not active',
                    ], 400);
                }
            }

            // Get callback URL from request or business profile
            $callbackUrl = $request->callback_url ?? $business->callback_url;

            // Parse purchase date
            $purchaseDate = $request->purchase_date 
                ? \Carbon\Carbon::parse($request->purchase_date) 
                : \Carbon\Carbon::now();

            // Create invoice
            $invoice = $this->invoiceService->createInvoiceForBusinessCustomer(
                $businessCustomer,
                $request->amount,
                $business->business_name,
                $purchaseDate,
                null, // due_date will be calculated automatically
                $business->id,
                null, // redirect_url (not used for payment link)
                $callbackUrl
            );

            // Create transaction record with items metadata
            Transaction::create([
                'customer_id' => $businessCustomer->linked_customer_id, // Will be null if not linked yet
                'business_id' => $business->id,
                'invoice_id' => $invoice->id,
                'type' => 'credit_purchase',
                'amount' => $invoice->principal_amount,
                'metadata' => [
                    'order_reference' => $request->order_reference,
                    'items' => $request->items,
                ],
                'status' => 'completed',
                'description' => $request->order_reference ? "Order: {$request->order_reference}" : "Invoice {$invoice->invoice_id}",
                'processed_at' => now(),
            ]);

            // Generate payment link
            $frontendUrl = env('FRONTEND_URL', 'https://fsscredit.foodstuff.store');
            $invoiceLink = rtrim($frontendUrl, '/') . '/checkout/' . $invoice->slug;

            // Send webhook notification
            try {
                $this->webhookService->sendWebhook($business, 'invoice.created', [
                    'invoice_id' => $invoice->invoice_id,
                    'slug' => $invoice->slug,
                    'payment_link' => $invoiceLink,
                    'payment_link_expires_at' => $invoice->slug_expires_at ? $invoice->slug_expires_at->toIso8601String() : null,
                    'amount' => $invoice->principal_amount,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                    'order_reference' => $request->order_reference,
                    'items' => $request->items,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to send invoice created webhook: ' . $e->getMessage(), [
                    'invoice_id' => $invoice->id,
                ]);
            }

            return response()->json([
                'message' => 'Invoice link generated successfully',
                'invoice_link' => $invoiceLink,
                'payment_link_expires_at' => $invoice->slug_expires_at ? $invoice->slug_expires_at->toIso8601String() : null,
                'slug' => $invoice->slug,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create invoice link failed: ' . $e->getMessage(), [
                'request' => $request->all(),
            ]);
            
            if (isset($business)) {
                try {
                    $this->webhookService->sendError($business, [
                        'error' => 'Invoice creation failed',
                        'message' => $e->getMessage(),
                        'customer_email' => $request->customer_email ?? null,
                        'amount' => $request->amount ?? null,
                    ]);
                } catch (\Exception $webhookError) {
                    Log::warning('Failed to send error webhook: ' . $webhookError->getMessage());
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}

