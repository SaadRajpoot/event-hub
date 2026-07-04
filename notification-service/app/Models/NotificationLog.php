<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'channel',
        'recipient',
        'subject',
        'payload',
        'status',
        'provider_reference',
        'failure_reason',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'  => 'array',
            'sent_at'  => 'datetime',
        ];
    }
}
