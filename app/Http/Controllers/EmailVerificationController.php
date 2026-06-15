<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmailVerificationController extends Controller
{
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->isAdmin() ? 'admin.platform' : 'letters.index');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->isAdmin() ? 'admin.platform' : 'letters.index');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);
        $record = DB::table('email_verification_codes')
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $record || now()->greaterThan(Carbon::parse($record->expires_at)) || $record->attempts >= 5) {
            DB::table('email_verification_codes')->where('user_id', $request->user()->id)->delete();

            return back()->withErrors(['code' => 'This code is invalid or expired. Request a new code.']);
        }

        if (! Hash::check($validated['code'], $record->code)) {
            DB::table('email_verification_codes')
                ->where('user_id', $request->user()->id)
                ->increment('attempts');

            return back()->withErrors(['code' => 'This code is invalid or expired.']);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }
        DB::table('email_verification_codes')->where('user_id', $request->user()->id)->delete();

        return redirect()->route($request->user()->isAdmin() ? 'admin.platform' : 'letters.index')
            ->with('success', 'Your email address is verified.');
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route($request->user()->isAdmin() ? 'admin.platform' : 'letters.index');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-code-sent');
    }
}
