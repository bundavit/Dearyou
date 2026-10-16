<?php

namespace App\Http\Controllers;

use App\Support\EmailIdentity;
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

        $request->session()->forget('password_reset_authorized');
        $authorization = DB::transaction(function () use ($email, $validated) {
            $user = EmailIdentity::findActive($email);

            if ($user === null) {
                DB::table('password_reset_codes')->whereRaw('LOWER(TRIM(email)) = ?', [EmailIdentity::normalize($email)])->delete();

                return null;
            }

            $records = DB::table('password_reset_codes')->where('email', $user->email);
            $record = (clone $records)->lockForUpdate()->first();

            if ($record === null || now()->greaterThanOrEqualTo(Carbon::parse($record->expires_at)) || (int) $record->attempts >= 5) {
                $records->delete();

                return null;
            }

            if (! Hash::check($validated['code'], $record->code)) {
                $records->increment('attempts');

                return null;
            }

            // Consume the exact issuance before granting session authorization.
            if ($records->where('code', $record->code)->delete() !== 1) {
                return null;
            }

            return [
                'user_id' => $user->id,
                'email' => EmailIdentity::normalize($user->email),
                'password_fingerprint' => hash('sha256', $user->getAuthPassword()),
                'session_version' => (int) $user->session_version,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ];
        });

        if ($authorization === null) {
            return back()->withErrors(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        $request->session()->forget('password_reset_email');
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('password_reset_authorized', $authorization);

        return redirect()->route('password.reset');
    }
}
