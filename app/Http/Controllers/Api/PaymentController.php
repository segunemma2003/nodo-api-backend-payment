<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Process payment webhook from payment provider
     * This endpoint receives payment notifications from virtual account providers
     */
    public function paymentWebhook(Request $request)
    {
        // Validate webhook signature (implement based on your payment provider)
        // For now, we'll accept the payment data

        $request->validate([
            'account_number' => 'required|string|size:16',
            'amount' => 'required|numeric|min:0.01',
            'transaction_reference' => 'required|string',
            'payment_date' => 'required|date',
        ]);

        $customer = Customer::where('account_number', $request->account_number)
            ->firstOrFail();

        $payment = $this->paymentService->processRepayment(
            $customer,
            $request->amount,
            $request->transaction_reference
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
            'payment' => [
                'payment_reference' => $payment->payment_reference,
                'amount' => $payment->amount,
                'status' => $payment->status,
            ],
            'customer' => [
                'available_balance' => $customer->fresh()->available_balance,
                'current_balance' => $customer->fresh()->current_balance,
            ],
        ]);
    }

    /**
     * Manually record payment (for admin use)
     */
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

    /**
     * Get payment history for customer
     */
    public function getPaymentHistory(Request $request, $customerId)
    {
        $customer = Customer::findOrFail($customerId);

        $payments = $customer->payments()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($payments);
    }
}

