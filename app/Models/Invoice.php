<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'slug',
        'is_used',
        'customer_id',
        'supplier_id',
        'supplier_name',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'paid_amount',
        'remaining_balance',
        'purchase_date',
        'due_date',
        'grace_period_end_date',
        'status',
        'months_overdue',
        'notes',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
        'purchase_date' => 'date',
        'due_date' => 'date',
        'grace_period_end_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'supplier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Generate a unique slug for invoice link
     */
    public static function generateSlug(): string
    {
        do {
            $slug = 'inv-' . strtolower(uniqid() . '-' . bin2hex(random_bytes(4)));
        } while (self::where('slug', $slug)->exists());

        return $slug;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_id)) {
                $invoice->invoice_id = 'NODO-' . strtoupper(uniqid());
            }
            if (empty($invoice->grace_period_end_date)) {
                $invoice->grace_period_end_date = Carbon::parse($invoice->due_date)->addDays(30);
            }
            $invoice->remaining_balance = $invoice->total_amount;
            $invoice->is_used = false;
        });
    }
}

