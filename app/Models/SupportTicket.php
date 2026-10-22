<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class SupportTicket extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'support_tickets';

    protected $fillable = [
        'ticket_uid',
        'user_uid',
        'user_name',
        'user_email',
        'topic',
        'subject',
        'description',
        'status', // OPEN, ANSWERED, CLOSED
        'replies',
    ];

    protected function casts(): array
    {
        return [
            'replies' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
