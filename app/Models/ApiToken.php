<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'client_name',
        'client_id',
        'webhook_url',
        'status',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return 'fscredit_' . bin2hex(random_bytes(32));
    }
}

