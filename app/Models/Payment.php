<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference',
        'customer_id',
        'invoice_id',
        'amount',
        'payment_type',
        'status',
        'admin_confirmation_status',
        'admin_confirmed_by',
        'admin_confirmed_at',
        'admin_rejection_reason',
        'payment_proof_url',
        'payment_method',
        'transaction_reference',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'admin_confirmed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function adminConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_confirmed_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_reference)) {
                $payment->payment_reference = 'PAY-' . strtoupper(uniqid());
            }
        });
    }
}

