<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_number',
        'cvv',
        'pin',
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
        'approval_status',
    ];

    protected $hidden = [
        'password',
        'pin',
        'cvv',
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
        // Calculate current balance (amount customer owes the platform):
        // 1. Unpaid invoices (status != 'paid' and != 'pending') - customer hasn't paid business yet
        // 2. Paid invoices where credit is not fully repaid - customer paid business using credit, but hasn't repaid platform
        //    For paid invoices: remaining_balance = total_amount - credit_repaid_amount
        
        $unpaidInvoices = $this->invoices()
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'pending')
            ->sum('remaining_balance');
        
        // For paid invoices, calculate what customer still owes: total_amount - credit_repaid_amount
        // Use DB::raw to calculate in the database for better performance
        $creditNotRepaid = $this->invoices()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('credit_repaid_status')
                      ->orWhere('credit_repaid_status', '!=', 'fully_paid');
            })
            ->selectRaw('SUM(GREATEST(0, total_amount - COALESCE(credit_repaid_amount, 0))) as total')
            ->value('total') ?? 0;
        
        // Update remaining_balance for paid invoices in bulk
        // Only update if the calculated value would be different to avoid unnecessary writes
        $this->invoices()
            ->where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('credit_repaid_status')
                      ->orWhere('credit_repaid_status', '!=', 'fully_paid');
            })
            ->whereRaw('remaining_balance != GREATEST(0, total_amount - COALESCE(credit_repaid_amount, 0))')
            ->update([
                'remaining_balance' => DB::raw('GREATEST(0, total_amount - COALESCE(credit_repaid_amount, 0))')
            ]);
        
        $this->current_balance = $unpaidInvoices + $creditNotRepaid;
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

    /**
     * Generate a 3-digit CVV
     */
    public static function generateCVV(): string
    {
        return str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Verify PIN for payment (cannot use default 0000)
     */
    public function verifyPinForPayment(string $pin): bool
    {
        if ($this->pin === '0000') {
            return false;
        }
        return $this->pin === $pin;
    }

    /**
     * Verify PIN for changing PIN (can use default 0000)
     */
    public function verifyPinForChange(string $pin): bool
    {
        return $this->pin === $pin;
    }

    /**
     * Verify CVV
     */
    public function verifyCvv(string $cvv): bool
    {
        return $this->cvv === $cvv;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->account_number)) {
                $customer->account_number = self::generateAccountNumber();
            }
            if (empty($customer->cvv)) {
                $customer->cvv = self::generateCVV();
            }
            if (empty($customer->pin)) {
                $customer->pin = '0000';
            }
        });
    }
}

