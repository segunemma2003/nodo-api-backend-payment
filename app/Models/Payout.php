<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'payout_reference',
        'invoice_id',
        'supplier_name',
        'amount',
        'status',
        'transaction_reference',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payout) {
            if (empty($payout->payout_reference)) {
                $payout->payout_reference = 'POUT-' . strtoupper(uniqid());
            }
        });
    }
}

