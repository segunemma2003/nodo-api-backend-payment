<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Transaction;
use App\Notifications\PaymentSuccessNotification;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected InterestService $interestService;
    protected WebhookService $webhookService;

    public function __construct(InterestService $interestService, WebhookService $webhookService)
    {
        $this->interestService = $interestService;
        $this->webhookService = $webhookService;
    }

    public function processRepayment(Customer $customer, float $amount, ?string $transactionReference = null): Payment
    {
        DB::beginTransaction();

        try {
            $payment = Payment::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'payment_type' => 'repayment',
                'status' => 'completed',
                'transaction_reference' => $transactionReference,
                'paid_at' => now(),
            ]);

            // Get invoices that are either unpaid OR paid but credit not fully repaid
            $invoices = $customer->invoices()
                ->where(function ($query) {
                    $query->where('status', '!=', 'paid')
                        ->orWhere(function ($q) {
                            $q->where('status', 'paid')
                              ->where(function ($subQ) {
                                  $subQ->whereNull('credit_repaid_status')
                                       ->orWhere('credit_repaid_status', '!=', 'fully_paid');
                              });
                        });
                })
                ->orderByRaw('due_date IS NULL, due_date ASC')
                ->get();

            $remainingAmount = $amount;

            foreach ($invoices as $invoice) {
                if ($remainingAmount <= 0) {
                    break;
                }

                if ($invoice->status === 'paid') {
                    $remainingCreditToRepay = $invoice->total_amount - ($invoice->credit_repaid_amount ?? 0);
                    $paymentAmount = min($remainingAmount, $remainingCreditToRepay);
                    
                    $invoice->credit_repaid_amount = ($invoice->credit_repaid_amount ?? 0) + $paymentAmount;
                    
                    if ($invoice->credit_repaid_amount >= $invoice->total_amount) {
                        $invoice->credit_repaid_status = 'fully_paid';
                        $invoice->credit_repaid_at = now();
                        $invoice->credit_repaid_amount = $invoice->total_amount;
                        $invoice->remaining_balance = 0;
                    } elseif ($invoice->credit_repaid_amount > 0) {
                        $invoice->credit_repaid_status = 'partially_paid';
                        $invoice->remaining_balance = $invoice->total_amount - $invoice->credit_repaid_amount;
                    } else {
                        $invoice->credit_repaid_status = 'pending';
                        $invoice->remaining_balance = $invoice->total_amount;
                    }
                } else {
                    $paymentAmount = min($remainingAmount, $invoice->remaining_balance);
                    $invoice->paid_amount += $paymentAmount;
                    $invoice->remaining_balance -= $paymentAmount;

                    if ($invoice->remaining_balance <= 0) {
                        $invoice->status = 'paid';
                        $invoice->remaining_balance = 0;
                        if ($invoice->credit_repaid_status === null) {
                            $invoice->credit_repaid_status = 'pending';
                            $invoice->credit_repaid_amount = 0;
                        }
                    }
                }

                $invoice->save();
                $payment->invoice_id = $invoice->id;
                $payment->save();
                
                $remainingAmount -= $paymentAmount;
            }

            $customer->updateBalances();

            Transaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $payment->invoice_id,
                'type' => 'repayment',
                'amount' => $amount,
                'status' => 'completed',
                'description' => "Repayment for invoice {$payment->invoice_id}",
                'processed_at' => now(),
            ]);

            if ($payment->invoice_id) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice && $invoice->supplier) {
                    $customer->notify(new PaymentSuccessNotification($invoice, $amount, 'repayment'));
                    $this->webhookService->sendPaymentUpdate($invoice->supplier, [
                        'payment_reference' => $payment->payment_reference,
                        'invoice_id' => $invoice->invoice_id,
                        'amount' => $amount,
                        'customer_id' => $customer->id,
                        'status' => 'completed',
                    ]);
                }
            }

            Cache::forget('customer_credit_' . $customer->id);
            Cache::forget('customer_invoices_' . $customer->id);
            Cache::forget('admin_customer_' . $customer->id);
            Cache::forget('admin_dashboard_stats');

            DB::commit();

            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function processPayout(Invoice $invoice): Payout
    {
        $payout = Payout::create([
            'invoice_id' => $invoice->id,
            'supplier_name' => $invoice->supplier_name,
            'amount' => $invoice->principal_amount,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        if ($invoice->supplier) {
            $invoice->supplier->notify(new PaymentSuccessNotification($invoice, $invoice->principal_amount, 'credit_purchase'));
            $this->webhookService->sendPaymentUpdate($invoice->supplier, [
                'payout_reference' => $payout->payout_reference,
                'invoice_id' => $invoice->invoice_id,
                'amount' => $invoice->principal_amount,
                'customer_id' => $invoice->customer_id,
                'status' => 'completed',
            ]);
        }

        if ($invoice->customer) {
            $invoice->customer->notify(new PaymentSuccessNotification($invoice, $invoice->principal_amount, 'credit_purchase'));
        }

        return $payout;
    }

    public function processInvoicePayment(Customer $customer, Invoice $invoice, float $amount): Payment
    {
        DB::beginTransaction();

        try {
            if ($invoice->status !== 'paid') {
                $this->interestService->updateInvoiceStatus($invoice);
                $invoice->refresh();
                $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;
            }

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'payment_type' => 'repayment',
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            if ($invoice->total_amount <= $invoice->principal_amount) {
                $invoice->total_amount = $invoice->principal_amount + $invoice->interest_amount;
            }

            $invoice->paid_amount = $invoice->principal_amount;
            $invoice->status = 'paid';

            if ($invoice->credit_repaid_status === null) {
                $invoice->credit_repaid_status = 'pending';
                $invoice->credit_repaid_amount = 0;
            }
            
            $invoice->remaining_balance = $invoice->total_amount;
            $invoice->save();

            $invoice->load('supplier');
            $customer->updateBalances();

            Transaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'type' => 'repayment',
                'amount' => $amount,
                'status' => 'completed',
                'description' => "Payment for invoice {$invoice->invoice_id}",
                'processed_at' => now(),
            ]);

            if ($invoice->supplier_id && !$invoice->payouts()->exists()) {
                $this->processPayout($invoice);
            }

            // Send notifications
            if ($invoice->supplier) {
                $customer->notify(new PaymentSuccessNotification($invoice, $amount, 'repayment'));
                $this->webhookService->sendPaymentUpdate($invoice->supplier, [
                    'payment_reference' => $payment->payment_reference,
                    'invoice_id' => $invoice->invoice_id,
                    'amount' => $amount,
                    'customer_id' => $customer->id,
                    'account_number' => $customer->account_number,
                    'status' => 'completed',
                ]);
            }

            Cache::forget('customer_credit_' . $customer->id);
            Cache::forget('customer_invoices_' . $customer->id);
            Cache::forget('admin_customer_' . $customer->id);
            Cache::forget('admin_dashboard_stats');

            DB::commit();

            return $payment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Invoice payment processing failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

