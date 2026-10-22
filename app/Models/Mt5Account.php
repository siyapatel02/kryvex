<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Mt5Account extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'mt5_accounts';

    protected $fillable = [
        'account_uid',
        'user_uid',
        'plan_id',
        'plan_name',
        'login',
        'password',
        'investor_password',
        'server',
        'currency',
        'metaapi_account_id',
        'status', // active, suspended
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
