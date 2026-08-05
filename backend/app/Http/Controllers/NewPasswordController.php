<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class NewPasswordController extends Controller
{
    public function create(Request $request)
    {
        $authorization = $request->session()->get('password_reset_authorized');

        if (! $this->validAuthorization($authorization)) {
            $request->session()->forget('password_reset_authorized');

            return redirect()->route('password.request');
        }

        return view('auth.reset-password', [
            'email' => $authorization['email'],
        ]);
    }

    public function store(Request $request)
    {
        $authorization = $request->session()->get('password_reset_authorized');

        if (! $this->validAuthorization($authorization)) {
            $request->session()->forget('password_reset_authorized');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your password reset session expired. Request a new code.']);
        }

        $credentials = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);

        $user = User::where('email', $authorization['email'])
            ->whereNull('disabled_at')
            ->first();

        if (! $user) {
            $request->session()->forget('password_reset_authorized');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'This password reset request is invalid.']);
        }

        $user->forceFill([
            'password' => Hash::make($credentials['password']),
            'remember_token' => Str::random(60),
        ])->save();
        $user->tokens()->delete();
        $request->session()->forget('password_reset_authorized');

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'Your password has been reset.');
    }

    private function validAuthorization(mixed $authorization): bool
    {
        return is_array($authorization)
            && isset($authorization['email'], $authorization['expires_at'])
            && (int) $authorization['expires_at'] >= now()->timestamp;
    }
}
