<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\DB;
use App\Models\CreditTransaction;
use App\Models\DebitTransaction;
use App\Models\AuditLogs;
use App\Models\ControlBalance;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Carbon\Carbon;


class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => Carbon::now()
            ]);
    
            $token = $user->createToken('apptoken')->plainTextToken;
    

            // Loop through control balances to find one with sufficient funds
            $controlBalance = ControlBalance::where('amount', '>=', 50)->lockForUpdate()->first();
            $admin = User::where('id', $controlBalance->user_id)->first();

            if (!$controlBalance || !$admin) {
                DB::commit();
                $response = [
                    'message' => 'User created successfully.',
                    'user' => $user,
                    'balance' => 0,
                    'token' => $token
                ];
    
                return response($response, 201);
            }

            // Debit 50 from the selected control balance
            $controlBalance->amount -= 50;
            $controlBalance->save();

            // Record debit transaction for control balance
            $debitTransaction = DebitTransaction::create([
                'user_id' => $admin->id,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => 50,
                'description' => 'Debit for new user creation',
            ]);

            // Record credit transaction for the new user
            $creditTransaction = CreditTransaction::create([
                'user_id' => $user->id,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => 50,
                'description' => 'Credit for new user creation',
            ]);

            // Log the actions
            AuditLogs::create([
                'user_id' => $admin->id,
                'action' => 'debit_control_balance',
                'description' => 'Debited 50 from control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            AuditLogs::create([
                'user_id' => $user->id,
                'action' => 'credit_user',
                'description' => 'Credited 50 to new user',
                'status' => 'success',
                'anomaly' => false,
            ]);

            DB::commit();

            $response = [
                'message' => 'User created and credited successfully.',
                'user' => $user,
                'balance' => 50,
                'token' => $token
            ];

            return response($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User creation failed: ' . $e->getMessage());
            print $e;
            return response(['message' => 'User creation failed. Please try again later.'], 500);
        }
    }
}
