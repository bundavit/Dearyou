<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmailIdentity;
use App\Support\SessionSecurity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class NewPasswordController extends Controller
{
    public function create(Request $request)
    {
        $authorization = $request->session()->get('password_reset_authorized');

        if (! $this->authorizedUser($authorization)) {
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

        $user = $this->authorizedUser($authorization);

        if (! $user) {
            $request->session()->forget('password_reset_authorized');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Your password reset session expired. Request a new code.']);
        }

        $credentials = $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(10)->mixedCase()->numbers()],
        ]);

        try {
            app(SessionSecurity::class)->changePassword($user, $credentials['password']);
        } catch (ValidationException $exception) {
            $request->session()->forget('password_reset_authorized');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'This password reset request is invalid.']);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'Your password has been reset.');
    }

    private function authorizedUser(mixed $authorization): ?User
    {
        if (! is_array($authorization)
            || ! isset($authorization['user_id'], $authorization['email'], $authorization['password_fingerprint'], $authorization['session_version'], $authorization['expires_at'])
            || ! is_string($authorization['email'])
            || ! is_string($authorization['password_fingerprint'])
            || (int) $authorization['expires_at'] <= now()->timestamp) {
            return null;
        }

        $user = EmailIdentity::findActive($authorization['email']);

        return $user
            && (int) $authorization['user_id'] === (int) $user->id
            && (int) $authorization['session_version'] === $user->session_version
            && hash_equals(hash('sha256', $user->getAuthPassword()), $authorization['password_fingerprint'])
                ? $user : null;
    }
}
