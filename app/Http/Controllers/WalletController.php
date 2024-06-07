<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CreditTransaction;
use App\Models\DebitTransaction;
use App\Models\AuditLogs;
use App\Models\User;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function sendCash(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'to_user_id' => 'required',
            'amount' => 'required|numeric|min:10.00',
            'description' => 'nullable|string',
        ]);

        $fromUserId = $user->id;
        $toUserId = $request->to_user_id;
        $amount = $request->amount;
        $description = $request->description;

        DB::beginTransaction();

        try {
            // Lock the users' rows for update
            $fromUser = User::lockForUpdate()->findOrFail($fromUserId);
            $toUser = User::lockForUpdate()->findOrFail($toUserId);

            // Calculate current balances on the fly
            //$totalCreditsFromUser = CreditTransaction::where('user_id', $fromUserId)->sum('amount');
            //$totalDebitsFromUser = DebitTransaction::where('user_id', $fromUserId)->sum('amount');
            $fromUserBalance = $fromUser->calculateBalance();

            // Verify acccount
            if (!$request->user()->tokenCan('user:access') || ($fromUserId == $toUserId)) {
                return response(['message' => 'Account does not have access level.'], 403);
            }

            // Ensure the balance is enough
            if ($fromUserBalance < $amount) {
                return response(['message' => 'Insufficient funds.'], 400);
            }

            // Perform the debit transaction
            $debitTransaction = DebitTransaction::create([
                'user_id' => $fromUserId,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);

            // Perform the credit transaction
            $creditTransaction = CreditTransaction::create([
                'user_id' => $toUserId,
                'transaction_reference' => 'TRN-' . Str::uuid(),
                'amount' => $amount,
                'description' => $description,
            ]);


            // Create audit log for the debit transaction
            AuditLogs::create([
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'user_id' => $fromUserId,
                'action' => 'debit',
                'description' => $description,
                'status' => 'success',
                'anomaly' => false,
            ]);

            // Create audit log for the credit transaction
            AuditLogs::create([
                'credit_transaction_id' => $creditTransaction->id,
                'debit_transaction_id' => $debitTransaction->id,
                'user_id' => $toUserId,
                'action' => 'credit',
                'description' => $description,
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
}
