<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'business_id',
        'event',
        'url',
        'payload',
        'attempts',
        'status',
        'http_status',
        'response_body',
        'error_message',
        'delivered_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function markAsDelivered(int $httpStatus, ?string $responseBody = null): void
    {
        $this->update([
            'status' => 'delivered',
            'http_status' => $httpStatus,
            'response_body' => $responseBody,
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage, ?int $httpStatus = null, ?string $responseBody = null): void
    {
        $this->increment('attempts');
        $this->update([
            'status' => 'failed',
            'http_status' => $httpStatus,
            'response_body' => $responseBody,
            'error_message' => $errorMessage,
            'next_retry_at' => now()->addHour(), // Retry in 1 hour
        ]);
    }

    public function shouldRetry(): bool
    {
        return $this->status === 'failed' 
            && $this->next_retry_at 
            && $this->next_retry_at <= now();
    }
}
