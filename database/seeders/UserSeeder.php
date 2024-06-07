<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ControlBalance;
use App\Models\CreditTransaction;
use App\Models\AuditLogs;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create an admin user
        $adminUser = User::create([
            'name' => 'Big Joe',
            'email' => 'admin@fincra.com',
            'password' => Hash::make('wordispass123'),
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        // Create a control balance for the admin user
        $adminControlBalance = ControlBalance::create([
            'user_id' => $adminUser->id,
            'amount' => 10000.00,
        ]);

        $creditTransaction = CreditTransaction::create([
            'user_id' => $adminUser->id,
            'control_balance_id' => $adminControlBalance->id,
            'transaction_reference' => 'TRN-' . Str::uuid(),
            'amount' => 10000,
            'description' => 'Credit for new user creation',
        ]);

        // Log the action in audit log
        AuditLogs::create([
            'user_id' => $adminUser->id, 
            'action' => 'create_admin',
            'description' => 'Created admin user: ' . $adminUser->email,
            'status' => 'success',
            'anomaly' => false,
        ]);
    }
}
