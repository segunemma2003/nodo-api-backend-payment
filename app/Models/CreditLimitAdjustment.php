<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLimitAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'previous_credit_limit',
        'new_credit_limit',
        'adjustment_amount',
        'reason',
        'admin_user_id',
    ];

    protected $casts = [
        'previous_credit_limit' => 'decimal:2',
        'new_credit_limit' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }
}

