<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_number',
        'business_name',
        'email',
        'username',
        'password',
        'phone',
        'address',
        'minimum_purchase_amount',
        'payment_plan_duration',
        'credit_limit',
        'current_balance',
        'available_balance',
        'virtual_account_number',
        'virtual_account_bank',
        'kyc_documents',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'minimum_purchase_amount' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'kyc_documents' => 'array',
        'password' => 'hashed',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function updateBalances(): void
    {
        $this->current_balance = $this->invoices()
            ->where('status', '!=', 'paid')
            ->sum('remaining_balance');
        
        $this->available_balance = max(0, $this->credit_limit - $this->current_balance);
        $this->save();
    }

    /**
     * Generate a unique 16-digit account number
     */
    public static function generateAccountNumber(): string
    {
        do {
            $accountNumber = str_pad(rand(1000000000000000, 9999999999999999), 16, '0', STR_PAD_LEFT);
        } while (self::where('account_number', $accountNumber)->exists());

        return $accountNumber;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->account_number)) {
                $customer->account_number = self::generateAccountNumber();
            }
        });
    }
}

