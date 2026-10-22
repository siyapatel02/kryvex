<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'admin@kryvex.local';

        $existingAdmin = DB::connection('mongodb')
            ->table('admins')
            ->where('email', $email)
            ->first();

        $payload = [
            'name' => 'Kryvex Super Admin',
            'email' => $email,
            'password' => Hash::make('Admin@123'),
            'role' => 'super_admin',
            'permissions' => [
                'dashboard.view',
                'users.manage',
                'kyc.manage',
                'wallet.manage',
                'withdrawals.manage',
                'futures.manage',
                'options.manage',
                'reports.view',
                'settings.manage',
                'audit_logs.view',
            ],
            'status' => 'active',
            'updated_at' => now(),
        ];

        if ($existingAdmin) {
            DB::connection('mongodb')
                ->table('admins')
                ->where('email', $email)
                ->update($payload);

            return;
        }

        DB::connection('mongodb')
            ->table('admins')
            ->insert(array_merge($payload, [
                'uid' => (string) Str::uuid(),
                'last_login_at' => null,
                'last_login_ip' => null,
                'created_at' => now(),
            ]));
    }
}