<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;
    protected PaystackService $paystackService;

    public function __construct(PaymentService $paymentService, PaystackService $paystackService)
    {
        $this->paymentService = $paymentService;
        $this->paystackService = $paystackService;
    }

    public function paystackWebhook(Request $request)
    {
        return $this->paymentWebhook($request);
    }

    public function paymentWebhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Paystack-Signature');

        if (!$this->paystackService->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Invalid Paystack webhook signature', [
                'signature' => $signature,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->json()->all();
        $event = $data['event'] ?? null;

        if ($event === 'charge.success' || $event === 'transfer.success') {
            return $this->handlePaymentSuccess($data);
        }

        if ($event === 'transfer.failed' || $event === 'charge.failed') {
            Log::warning('Paystack payment failed', [
                'event' => $event,
                'data' => $data,
            ]);
            return response()->json(['status' => 'logged']);
        }

        Log::info('Paystack webhook received', [
            'event' => $event,
            'data' => $data,
        ]);

        return response()->json(['status' => 'success']);
    }

    protected function handlePaymentSuccess(array $data): \Illuminate\Http\JsonResponse
    {
        try {
            $paymentData = $data['data'] ?? [];
            $accountNumber = null;
            $customer = null;
            
            if (isset($paymentData['authorization']['account_number'])) {
                $accountNumber = $paymentData['authorization']['account_number'];
            }
            
            if (!$accountNumber) {
                $accountNumber = $paymentData['account']['account_number'] ?? 
                               $paymentData['dedicated_account']['account_number'] ?? 
                               $paymentData['recipient']['account_number'] ??
                               null;
            }

            if (!$accountNumber && isset($paymentData['customer']['email'])) {
                $customerEmail = $paymentData['customer']['email'];
                $customer = Customer::where('email', $customerEmail)->first();
                if ($customer && $customer->virtual_account_number) {
                    $accountNumber = $customer->virtual_account_number;
                }
            }

            if (!$accountNumber && isset($paymentData['recipient']['details']['account_number'])) {
                $accountNumber = $paymentData['recipient']['details']['account_number'];
            }

            if ($accountNumber && !$customer) {
                $customer = Customer::where('virtual_account_number', $accountNumber)->first();
            }

            if (!$customer && isset($paymentData['customer']['email'])) {
                $customer = Customer::where('email', $paymentData['customer']['email'])->first();
            }

            if (!$customer) {
                Log::error('Paystack webhook: Customer not found', [
                    'account_number' => $accountNumber,
                    'email' => $paymentData['customer']['email'] ?? null,
                    'data' => $paymentData,
                ]);
                return response()->json(['error' => 'Customer not found'], 404);
            }

            $amount = ($paymentData['amount'] ?? 0) / 100;
            $transactionReference = $paymentData['reference'] ?? 
                                  $paymentData['transfer_code'] ?? 
                                  $paymentData['id'] ?? 
                                  null;
            $paidAt = $paymentData['paid_at'] ?? 
                     $paymentData['created_at'] ?? 
                     now();

            if ($amount <= 0) {
                Log::warning('Paystack webhook: Invalid amount', [
                    'amount' => $amount,
                    'data' => $paymentData,
                ]);
                return response()->json(['error' => 'Invalid amount'], 400);
            }

            $existingPayment = \App\Models\Payment::where('transaction_reference', $transactionReference)
                ->where('customer_id', $customer->id)
                ->first();

            if ($existingPayment) {
                Log::info('Paystack webhook: Payment already processed', [
                    'payment_id' => $existingPayment->id,
                    'transaction_reference' => $transactionReference,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Payment already processed',
                    'payment' => [
                        'payment_reference' => $existingPayment->payment_reference,
                        'amount' => $existingPayment->amount,
                        'status' => $existingPayment->status,
                    ],
                ]);
            }

            $payment = $this->paymentService->processRepayment(
                $customer,
                $amount,
                $transactionReference
            );

            $payment->paid_at = $paidAt;
            $payment->save();

            $customer->refresh();
            $customer->updateBalances();

            Log::info('Paystack payment processed successfully and repayment details updated', [
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
                'transaction_reference' => $transactionReference,
                'available_balance' => $customer->available_balance,
                'current_balance' => $customer->current_balance,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully and repayment details updated',
                'payment' => [
                    'payment_reference' => $payment->payment_reference,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'transaction_reference' => $transactionReference,
                ],
                'customer' => [
                    'id' => $customer->id,
                    'account_number' => $customer->account_number,
                    'available_balance' => $customer->available_balance,
                    'current_balance' => $customer->current_balance,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Paystack webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    public function recordPayment(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        $payment = $this->paymentService->processRepayment(
            $customer,
            $request->amount,
            $request->transaction_reference
        );

        if ($request->has('notes')) {
            $payment->notes = $request->notes;
            $payment->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'payment' => $payment,
        ], 201);
    }

    public function getPaymentHistory(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        $payments = $customer->payments()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($payments);
    }
}

