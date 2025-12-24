<?php

namespace App\Models;

use App\Models\BusinessCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Business extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'businesses';

    protected $fillable = [
        'business_name',
        'email',
        'username',
        'password',
        'phone',
        'address',
        'kyc_documents',
        'approval_status',
        'api_token',
        'webhook_url',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'kyc_documents' => 'array',
        'password' => 'hashed',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'supplier_id');
    }

    public function businessCustomers(): HasMany
    {
        return $this->hasMany(BusinessCustomer::class, 'business_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getAvailableBalance(): float
    {
        // Calculate total revenue from invoices (including interest)
        // Only count invoices that are not pending (i.e., paid or have payments)
        $totalRevenue = $this->invoices()
            ->where('status', '!=', 'pending')
            ->sum(\DB::raw('COALESCE(paid_amount, 0)'));
        
        $totalWithdrawn = $this->withdrawals()
            ->whereIn('status', ['approved', 'processed'])
            ->sum('amount');
        
        return max(0, $totalRevenue - $totalWithdrawn);
    }

    public function getTotalRevenue(): float
    {
        return $this->invoices()
            ->where('status', '!=', 'pending')
            ->sum('principal_amount');
    }

    public function getTotalWithdrawn(): float
    {
        return $this->withdrawals()
            ->whereIn('status', ['approved', 'processed'])
            ->sum('amount');
    }

    public function generateApiToken(): string
    {
        $token = 'fscredit_biz_' . bin2hex(random_bytes(32));
        $this->api_token = $token;
        $this->save();
        return $token;
    }
}

