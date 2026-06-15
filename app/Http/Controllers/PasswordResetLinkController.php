<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $email = strtolower($validated['email']);

        $user = User::where('email', $email)
            ->whereNull('disabled_at')
            ->first();

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_codes')->updateOrInsert(
                ['email' => $user->email],
                [
                    'code' => Hash::make($code),
                    'attempts' => 0,
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $user->notify(new PasswordResetCode($code));
        }

        $request->session()->put('password_reset_email', $email);

        return redirect()->route('password.code')
            ->with('status', 'If an active account uses that email, a six-digit code has been sent.');
    }
}
