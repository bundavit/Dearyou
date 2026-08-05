<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetCodeController extends Controller
{
    public function create(Request $request)
    {
        if (! $request->session()->has('password_reset_email')) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-reset-code', [
            'email' => $request->session()->get('password_reset_email'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $email = (string) $request->session()->get('password_reset_email');

        if ($email === '') {
            return redirect()->route('password.request');
        }

        $record = DB::table('password_reset_codes')->where('email', $email)->first();

        if (! $record || now()->greaterThan(Carbon::parse($record->expires_at)) || $record->attempts >= 5) {
            DB::table('password_reset_codes')->where('email', $email)->delete();

            return back()->withErrors(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        if (! Hash::check($validated['code'], $record->code)) {
            DB::table('password_reset_codes')->where('email', $email)->increment('attempts');

            return back()->withErrors(['code' => 'This code is invalid or expired.']);
        }

        DB::table('password_reset_codes')->where('email', $email)->delete();
        $request->session()->forget('password_reset_email');
        $request->session()->put('password_reset_authorized', [
            'email' => $email,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('password.reset');
    }
}
