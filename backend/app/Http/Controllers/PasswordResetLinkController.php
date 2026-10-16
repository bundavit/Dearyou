<?php

namespace App\Http\Controllers;

use App\Notifications\PasswordResetCode;
use App\Support\EmailIdentity;
use App\Support\LocalSecurityCodeLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $email = EmailIdentity::normalize($validated['email']);
        $user = EmailIdentity::findActive($email);

        $request->session()->forget('password_reset_authorized');

        if ($user) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $hash = Hash::make($code);
            $previous = null;

            try {
                DB::transaction(function () use ($user, $code, $hash, &$previous) {
                    $previous = DB::table('password_reset_codes')->where('email', $user->email)->lockForUpdate()->first();

                    DB::table('password_reset_codes')->updateOrInsert(
                        ['email' => $user->email],
                        [
                            'code' => $hash,
                            'attempts' => 0,
                            'expires_at' => now()->addMinutes(10),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );

                    $notification = new PasswordResetCode($code, EmailIdentity::normalize($user->email), $hash);
                    $queue = config('queue.connections.'.config('queue.default'), []);
                    $sameDatabase = ($queue['driver'] ?? null) === 'database'
                        && ($queue['connection'] ?? config('database.default')) === config('database.default');

                    if ($sameDatabase || ($queue['driver'] ?? null) === 'sync') {
                        // Database queue insertion and the code commit together.
                        $notification->beforeCommit();
                    } else {
                        // External workers must only observe committed codes.
                        $notification->afterCommit();
                    }

                    $user->notify($notification);
                });
            } catch (Throwable $exception) {
                // An external queue can reject a request after its transaction
                // committed. Restore only this issuance, never a newer request.
                $current = DB::table('password_reset_codes')->where('email', $user->email)->where('code', $hash);
                if ($previous !== null) {
                    $current->update((array) $previous);
                } else {
                    $current->delete();
                }

                Log::warning('Password reset email could not be requested.', ['exception_type' => $exception::class]);

                return $this->requested($request, $email);
            }

            LocalSecurityCodeLogger::record('password reset', $user->email, $code);
        }

        return $this->requested($request, $email);
    }

    private function requested(Request $request, string $email)
    {
        $request->session()->put('password_reset_email', $email);

        return redirect()->route('password.code')
            ->with('status', 'If an active account uses that email, a six-digit code has been requested. If it does not arrive, try requesting a new code.');
    }
}
