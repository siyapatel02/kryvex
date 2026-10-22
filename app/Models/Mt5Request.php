<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Mt5Request extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'mt5_requests';

    protected $fillable = [
        'request_uid',
        'user_uid',
        'plan_id',
        'plan_name',
        'minimum_balance',
        'status', // pending, approved, rejected
        'requested_at',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'minimum_balance' => 'float',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
