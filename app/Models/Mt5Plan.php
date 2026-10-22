<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Mt5Plan extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'mt5_plans';

    protected $fillable = [
        'plan_key',
        'name',
        'minimum_balance',
        'max_leverage',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'minimum_balance' => 'float',
        ];
    }
}
