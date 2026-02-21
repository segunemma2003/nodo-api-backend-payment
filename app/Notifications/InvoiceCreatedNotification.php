<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\HtmlString;

class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invoice;
    protected $customerEmail;

    public function __construct(Invoice $invoice, $customerEmail = null)
    {
        $this->invoice = $invoice;
        $this->customerEmail = $customerEmail;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware()
    {
        return [new RateLimited('emails')];
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $customer = $this->invoice->customer;
        $businessCustomer = $this->invoice->businessCustomer;
        $supplier = $this->invoice->supplier;

        $mail = (new MailMessage)
            ->subject('New Invoice Created - ' . $this->invoice->invoice_id)
            ->greeting('Hello!')
            ->line('A new invoice has been created.');

        if ($customer) {
            $mail->line('**Customer:** ' . $customer->business_name)
                ->line('**Customer Account Number:** ' . $customer->account_number)
                ->line('**Customer Email:** ' . $customer->email);
        } elseif ($businessCustomer) {
            $mail->line('**Business Customer:** ' . $businessCustomer->business_name)
                ->line('**Contact Email:** ' . ($businessCustomer->contact_email ?? 'N/A'))
                ->line('**Contact Phone:** ' . ($businessCustomer->contact_phone ?? 'N/A'));
        }

        $mail->line('**Invoice Details:**')
            ->line('**Invoice ID:** ' . $this->invoice->invoice_id)
            ->line('**Principal Amount:** ₦' . number_format($this->invoice->principal_amount, 2))
            ->line('**Interest Amount:** ₦' . number_format($this->invoice->interest_amount, 2))
            ->line('**Total Amount:** ₦' . number_format($this->invoice->total_amount, 2))
            ->line('**Purchase Date:** ' . ($this->invoice->purchase_date ? $this->invoice->purchase_date->format('F d, Y') : 'N/A'))
            ->line('**Due Date:** ' . ($this->invoice->due_date ? $this->invoice->due_date->format('F d, Y') : 'N/A'));
        
        // Show grace period end date if available
        if ($this->invoice->grace_period_end_date) {
            $mail->line('**Grace Period Ends:** ' . $this->invoice->grace_period_end_date->format('F d, Y'));
        }
        
        $mail->line('**Payment Plan Duration:** ' . $this->invoice->payment_plan_duration . ' months')
            ->line('**Interest Rate:** ' . round(0.035 * $this->invoice->payment_plan_duration * 100, 2) . '% (' . $this->invoice->payment_plan_duration . ' months × 3.5% per month)')
            ->line('**Status:** ' . ucfirst($this->invoice->status));
        
        // If invoice is paid by FSCredit but customer still owes, show repayment info
        if ($this->invoice->status === 'paid' && $this->invoice->credit_repaid_status === 'pending') {
            $mail->line('')
                ->line('**⚠️ Payment Information:**')
                ->line('This invoice has been paid by FSCredit on your behalf.')
                ->line('**Amount Owed to FSCredit:** ₦' . number_format($this->invoice->total_amount, 2))
                ->line('**Repayment Due Date:** ' . ($this->invoice->due_date ? $this->invoice->due_date->format('F d, Y') : 'N/A'))
                ->line('Please ensure you repay the full amount (₦' . number_format($this->invoice->total_amount, 2) . ') to FSCredit by the due date.');
        }

        if ($supplier) {
            $mail->line('**Supplier:** ' . $supplier->business_name)
                ->line('**Supplier Email:** ' . $supplier->email);
        }

        // Add items/products information as HTML table
        $items = $this->invoice->getItems();
        if (!empty($items) && is_array($items)) {
            $totalItemsAmount = 0;
            
            // Build HTML table
            $tableHtml = '<h3 style="margin-top: 20px; margin-bottom: 10px;">Items/Products</h3>';
            $tableHtml .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #ddd;">';
            $tableHtml .= '<thead>';
            $tableHtml .= '<tr style="background-color: #f5f5f5;">';
            $tableHtml .= '<th style="padding: 12px; text-align: left; border: 1px solid #ddd;">#</th>';
            $tableHtml .= '<th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Item Name</th>';
            $tableHtml .= '<th style="padding: 12px; text-align: left; border: 1px solid #ddd;">Description</th>';
            $tableHtml .= '<th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Quantity</th>';
            $tableHtml .= '<th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Unit Price</th>';
            $tableHtml .= '<th style="padding: 12px; text-align: right; border: 1px solid #ddd;">Total</th>';
            $tableHtml .= '</tr>';
            $tableHtml .= '</thead>';
            $tableHtml .= '<tbody>';
            
            $itemNumber = 1;
            foreach ($items as $item) {
                $itemName = htmlspecialchars($item['name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                $quantity = $item['quantity'] ?? 0;
                $unitPrice = $item['price'] ?? 0;
                $itemTotal = $quantity * $unitPrice;
                $totalItemsAmount += $itemTotal;
                $uom = htmlspecialchars($item['uom'] ?? '', ENT_QUOTES, 'UTF-8');
                $description = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
                
                $quantityDisplay = $quantity . ($uom ? ' ' . $uom : '');
                
                $tableHtml .= '<tr>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd;">' . $itemNumber . '</td>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . $itemName . '</strong></td>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd;">' . ($description ?: 'N/A') . '</td>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">' . $quantityDisplay . '</td>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₦' . number_format($unitPrice, 2) . '</td>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;"><strong>₦' . number_format($itemTotal, 2) . '</strong></td>';
                $tableHtml .= '</tr>';
                
                $itemNumber++;
            }
            
            // Add subtotal row
            if ($totalItemsAmount > 0) {
                $tableHtml .= '<tr style="background-color: #f9f9f9; font-weight: bold;">';
                $tableHtml .= '<td colspan="5" style="padding: 10px; border: 1px solid #ddd; text-align: right;">Items Subtotal:</td>';
                $tableHtml .= '<td style="padding: 10px; border: 1px solid #ddd; text-align: right;">₦' . number_format($totalItemsAmount, 2) . '</td>';
                $tableHtml .= '</tr>';
            }
            
            $tableHtml .= '</tbody>';
            $tableHtml .= '</table>';
            
            $mail->line(new HtmlString($tableHtml));
        }

        if ($this->invoice->slug) {
            // Use the frontend URL or default to the specified root URL
            $frontendUrl = env('FRONTEND_URL', 'https://fsscredit.foodstuff.store');
            $paymentLink = rtrim($frontendUrl, '/') . '/checkout/' . $this->invoice->slug;
            $mail->line('**Payment Link:** ' . $paymentLink)
                ->action('Pay Invoice', $paymentLink);
        }

        $mail->line('Thank you!');

        return $mail;
    }
}
