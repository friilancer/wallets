<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Log;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): Response
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', Rules\Password::defaults()],
        ]);

        $fields = $request->all();

        
        try {
            $user = User::where('email', $fields['email'])->first();
    
            if(!$user || !Hash::check($fields['password'], $user->password)){
                return response([
                    'message' => 'Email or password incorrect',
                ], 400);
            }
            $user->tokens()->delete();

            if($user->is_super_admin){
                $token = $user->createToken('apptoken', ['admin:access'])->plainTextToken;        
            }else{
                $token = $user->createToken('apptoken', ['user:access'])->plainTextToken;
            }

            $response = [
                'message' => 'Login successful',
                'user' => $user,
                'balance' => $user->calculateBalance(),
                'token' => $token
            ];

            return response($response, 200);

        } catch (\Exception $e) {
            Log::error('User Login failed: ' . $e->getMessage());
            return response(['message' => 'Login failed. Please try again later.'], 500);
        }
    
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): Response
    {
        
        auth()->user()->tokens()->delete();

        $response = [
            'message' =>  'Logged out'
        ];

        return response($response, 200);
    }
}
