<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'uid',
        'client_id',
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'email_verified_at',
        'status',
        'kyc_status',
        'kyc_verified_at',
        'role',
        'broker_type',
        'deletion_status',
        'deletion_reason',
        'deletion_requested_at',
        'deletion_approved_at',
        'deletion_rejected_at',
        'deleted_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}