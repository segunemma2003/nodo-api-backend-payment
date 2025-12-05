<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'business_name',
        'address',
        'contact_name',
        'contact_phone',
        'contact_email',
        'minimum_purchase_amount',
        'payment_plan_duration',
        'registration_number',
        'tax_id',
        'verification_documents',
        'notes',
        'status',
        'linked_customer_id',
        'linked_at',
    ];

    protected $casts = [
        'minimum_purchase_amount' => 'decimal:2',
        'verification_documents' => 'array',
        'linked_at' => 'datetime',
    ];

    /**
     * Get the business that owns this customer
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the linked main customer (if registered)
     */
    public function linkedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'linked_customer_id');
    }

    /**
     * Get all invoices for this business customer
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'business_customer_id');
    }

    /**
     * Check if this business customer is linked to a main customer
     */
    public function isLinked(): bool
    {
        return !is_null($this->linked_customer_id);
    }

    /**
     * Link this business customer to a main customer account
     */
    public function linkToCustomer(Customer $customer): void
    {
        $this->linked_customer_id = $customer->id;
        $this->linked_at = now();
        $this->save();
    }
}

