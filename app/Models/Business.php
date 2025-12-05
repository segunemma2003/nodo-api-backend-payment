<?php

namespace App\Models;

use App\Models\BusinessCustomer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function getAvailableBalance(): float
    {
        $totalRevenue = $this->invoices()
            ->where('status', '!=', 'pending')
            ->sum('principal_amount');
        
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
        $token = 'nodo_biz_' . bin2hex(random_bytes(32));
        $this->api_token = $token;
        $this->save();
        return $token;
    }
}

