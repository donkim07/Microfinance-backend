<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'message_id',
        'message_type',
        'sender',
        'receiver',
        'fsp_code',
        'request_payload',
        'response_payload',
        'direction',
        'status_code',
        'error_message',
        'ip_address',
        'endpoint',
        'duration_ms',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'duration_ms' => 'integer',
    ];
}
