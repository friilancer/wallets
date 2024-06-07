<?php
use Illuminate\Http\Request;
use App\HTTP\Controllers\WalletController;
use App\HTTP\Controllers\AdminActionsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;


#AUTH

Route::post('auth/register', [RegisteredUserController::class, 'store'])
                ->middleware('guest')
                ->name('register');

Route::post('auth/login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('guest')
                ->name('login');

Route::post('auth/logout', [AuthenticatedSessionController::class, 'destroy'])
                ->middleware('auth')
                ->name('logout');

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => ['auth:sanctum']], function(){
    Route::post('wallet/transfer', [WalletController::class, 'sendCash']);
    Route::post('admin/wallet/debit', [AdminActionsController::class, 'debitControlCreditUser']);
    Route::post('admin/wallet/credit', [AdminActionsController::class, 'debitUserCreditControl']);
    Route::post('admin/wallet/top-up', [AdminActionsController::class, 'topUpControlBalance']);
    Route::get('admin/get-weekly-report', [AdminActionsController::class, 'weeklyReport']);
    Route::post('admin/create-user', [AdminActionsController::class, 'createUser']);
    Route::post('admin/create-admin', [AdminActionsController::class, 'createAdmin']);   
});