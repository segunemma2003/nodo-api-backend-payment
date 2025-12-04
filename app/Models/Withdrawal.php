<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'withdrawal_reference',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'rejection_reason',
        'processed_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($withdrawal) {
            if (empty($withdrawal->withdrawal_reference)) {
                $withdrawal->withdrawal_reference = 'WDR-' . strtoupper(uniqid());
            }
        });
    }
}

