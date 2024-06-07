<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CreditTransaction;
use App\Models\DebitTransaction;
use App\Models\AuditLogs;
use App\Models\User;
use App\Models\CurrentBalance;
use App\Models\HashedBalance;
use App\Models\ControlBalance;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use App\Services\RiskService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class AdminActionsController extends Controller
{
    public function debitControlCreditUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:10.00',
            'description' => 'nullable|string',
        ]);

        $admin = $request->user();
        $adminId = $admin->id;
        $userId = $request->user_id;
        $amount = $request->amount;
        $description = $request->description ? $request->description : 'Top-up by admin';

        DB::beginTransaction();

        try {

            if (!$request->user()->tokenCan('admin:access') || ($adminId == $userId)) {
                return response(['message' => 'Account does not have access level.'], 403);
            }
            
            // Fetch and lock the control balance row for update
            $controlBalance = ControlBalance::where('user_id', $adminId)->lockForUpdate()->firstOrFail();
            $user = User::lockForUpdate()->findOrFail($userId);

            // Ensure the control balance has sufficient funds
            if ($controlBalance->amount < $amount) {
                return response(['message' => 'Insufficient funds in user balance.'], 400);    
            }

            // Perform the debit transaction for control balance
            $controlBalance->amount -= $amount;
            $controlBalance->save();

            // Perform the credit transaction for the user
            $creditTransaction = CreditTransaction::create([
                'user_id' => $userId,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);
            

            // Create debit transaction record
            $debitTransaction = DebitTransaction::create([
                'user_id' => $adminId,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);

            // Log the actions
            AuditLogs::create([
                'user_id' => $adminId,
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'action' => 'debit_control_balance',
                'description' => 'Debited '. $amount .'from control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            AuditLogs::create([
                'user_id' => $userId,
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'action' => 'credit_user',
                'description' => 'Credited '. $amount .'from control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);


            DB::commit();

            return response(['message' => 'Transaction completed successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction failed: ' . $e->getMessage());
            echo $e;
            return response(['message' => 'Transaction failed. Please try again later.'], 500);
        }
    }

    public function debitUserCreditControl(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:10.00',
            'description' => 'nullable|string',
        ]);

        $admin = $request->user();
        $adminId = $admin->id;
        $userId = $request->user_id;
        $amount = $request->amount;
        $description = $request->description ? $request->description : 'Top-up by admin';

        DB::beginTransaction();

        try {
            if (!$request->user()->tokenCan('admin:access') || ($adminId == $userId)) {
                return response(['message' => 'Account does not have access level.'], 403);
            }
            // Fetch and lock the control balance row for update
            $controlBalance = ControlBalance::where('user_id', $adminId)->lockForUpdate()->firstOrFail();

            // Fetch and lock the user row for update
            $user = User::lockForUpdate()->findOrFail($userId);

            // Ensure the user has sufficient funds
            $userBalance = $user->calculateBalance();
            if ($userBalance < $amount) {
                return response(['message' => 'Insufficient funds in user balance.'], 400);
            }

            // Perform the credit transaction for the control balance
            $controlBalance->amount += $amount;
            $controlBalance->save();

            // Create debit transaction record
            $debitTransaction = DebitTransaction::create([
                'user_id' => $userId,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);

            // Perform the credit transaction
            $creditTransaction = CreditTransaction::create([
                'user_id' => $adminId,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);

            // Log the actions
            AuditLogs::create([
                'user_id' => $adminId,
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'action' => 'credit_control_balance',
                'description' => 'Credited'. $amount .'to control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            AuditLogs::create([
                'user_id' => $userId,
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'action' => 'debit_user',
                'description' => 'Debited '. $amount .'to control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            DB::commit();

            return response(['message' => 'Transaction completed successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction failed: ' . $e->getMessage());
            echo $e;
            return response(['message' => 'Transaction failed. Please try again later.'], 500);
        }
    }

    public function weeklyReport(Request $request)
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $creditTransactions = CreditTransaction::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->get();

        $debitTransactions = DebitTransaction::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->get();

        // Aggregate the transactions
        $report = [
            'credits' => $creditTransactions,
            'debits' => $debitTransactions,
        ];

        // Format the report as needed (e.g., convert to JSON, CSV, etc.)
        // For simplicity, we'll just return the raw data here
        return response($report);
    }

    public function topUpControlBalance(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $admin = $request->user();
        $adminId = $admin->id;
        $amount = $request->amount;
        $description ='Top-up by admin';

        DB::beginTransaction();

        try {

            if (!$request->user()->tokenCan('admin:access')) {
                return response(['message' => 'Account does not have access level.'], 403);
            }

            $controlBalance = ControlBalance::where('user_id', $adminId)->lockForUpdate()->firstOrFail();
            $controlBalance->amount += $amount;
            $controlBalance->save();

            $creditTransaction = CreditTransaction::create([
                'user_id' => $adminId,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);


            // Log the action in audit log
            AuditLogs::create([
                'user_id' => $adminId,
                'credit_transaction_id' => $creditTransaction->id,
                'action' => 'top_up_control_balance',
                'description' => $description,
                'status' => 'success',
                'anomaly' => false,
            ]);

            DB::commit();

            return response(['message' => 'Control balance topped up successfully.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Control balance top-up failed: ' . $e->getMessage());

            return response(['message' => 'Control balance top-up failed. Please try again later.'], 500);
        }
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $admin = $request->user();
        $adminId = $admin->id;

        DB::beginTransaction();

        try {
            if (!$request->user()->tokenCan('admin:access')) {
                return response(['message' => 'Account does not have access level.'], 403);
            }
            // Retrieve and lock the control balance row for update
            $controlBalance = ControlBalance::where('user_id', $adminId)->lockForUpdate()->firstOrFail(); 

            // Ensure the control balance has sufficient funds
            if ($controlBalance->amount < 50) {
                throw new \Exception('Insufficient funds in control balance.');
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => Carbon::now()
            ]);

            // Debit 10 from the control balance
            $controlBalance->amount -= 50;
            $controlBalance->save();

            // Record debit transaction for control balance
            $debitTransaction = DebitTransaction::create([
                'user_id' => $adminId, 
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => 50,
                'description' => 'Debit for new user creation',
            ]);

            // Record credit transaction for user
            $creditTransaction = CreditTransaction::create([
                'user_id' => $user->id,
                'control_balance_id' => $controlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => 50,
                'description' => 'Credit for new user creation',
            ]);

            // Log the action in audit log
            AuditLogs::create([
                'user_id' => $adminId, 
                'action' => 'create_user',
                'description' => 'Created user ' . $user->id,
                'status' => 'success',
                'anomaly' => false,
            ]);

            // Log the actions
            AuditLogs::create([
                'user_id' => $adminId,
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'action' => 'debit_control_balance',
                'description' => 'Debited 50 from control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            AuditLogs::create([
                'user_id' => $user->id,
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'action' => 'credit_user',
                'description' => 'Credited 50 from control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            DB::commit();

            return response(['message' => 'User created successfully.'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User creation failed: ' . $e->getMessage());
            echo $e;
            return response(['message' => 'User creation failed. Please try again later.'], 500);
        }
    }

    public function createAdmin(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        DB::beginTransaction();

        try {
            // Create the admin user
            if (!$request->user()->tokenCan('admin:access')) {
                return response(['message' => 'Account does not have access level.'], 403);
            }
            $adminUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_super_admin' => true,
                'email_verified_at' => Carbon::now()
            ]);

            $adminControlBalance = ControlBalance::create([
                'user_id' => $adminUser->id,
                'amount' => 10000.00,
            ]);

            // Record credit transaction for admin
            $creditTransaction = CreditTransaction::create([
                'user_id' => $adminUser->id,
                'control_balance_id' => $adminControlBalance->id,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => 10000,
                'description' => 'Credit for new admin creation',
            ]);

            $admin = $request->user();
            $adminId = $admin->id;

            // Log the action in audit log
            AuditLogs::create([
                'user_id' => $adminId, // Assuming the currently authenticated user is the admin
                'action' => 'create_admin',
                'description' => 'Created admin user: ' . $adminUser->email,
                'status' => 'success',
                'anomaly' => false,
            ]);

            AuditLogs::create([
                'user_id' => $adminUser->id,
                'credit_transaction_id' => $creditTransaction->id,
                'action' => 'credit_control_balance',
                'description' => 'Credited 1000 to control balance',
                'status' => 'success',
                'anomaly' => false,
            ]);

            DB::commit();

            return response(['message' => 'Admin created successfully.'], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin creation failed: ' . $e->getMessage());

            return response(['message' => 'Admin creation failed. Please try again later.'], 500);
        }
    }
}
