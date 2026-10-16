<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Recaller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionSecurity
{
    public const SESSION_KEY = 'session_security_web';

    /** Record the credentials actually used to authenticate, never a newer snapshot. */
    public function establish(Request $request, User $user): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => (string) $user->getAuthIdentifier(),
            'version' => (int) $user->session_version,
            'password_fingerprint' => $this->fingerprint($user),
        ]);
    }

    public function isCurrent(Request $request, User $user): bool
    {
        $credentials = $request->session()->get(self::SESSION_KEY);

        // Sessions created before this feature have version zero. Never enroll an
        // unversioned session in a newer version merely because its user exists.
        if ($credentials === null) {
            return (int) $user->session_version === 0;
        }

        return is_array($credentials)
            && ($credentials['user_id'] ?? null) === (string) $user->getAuthIdentifier()
            && is_int($credentials['version'] ?? null)
            && $credentials['version'] === (int) $user->session_version
            && is_string($credentials['password_fingerprint'] ?? null)
            && hash_equals($this->fingerprint($user), $credentials['password_fingerprint']);
    }

    /**
     * All application password changes must use this operation. The user is the
     * snapshot whose current password/reset authorization the caller validated.
     * Passing the current request preserves only that authenticated browser.
     */
    public function changePassword(User $user, string $password, ?Request $currentRequest = null): void
    {
        $remember = $currentRequest !== null && $this->remembersBrowser($currentRequest, $user);

        $updated = $user->getConnection()->transaction(function () use ($user, $password) {
            $locked = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->first();

            if (! $locked
                || $locked->disabled_at !== null
                || $locked->email !== $user->email
                || (int) $locked->session_version !== (int) $user->session_version
                || ! hash_equals($locked->getAuthPassword(), $user->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'password' => 'Your account credentials changed. Sign in or request a new reset code and try again.',
                ]);
            }

            // The increment and credential rotation are a single SQL statement;
            // token revocation shares the same connection and transaction.
            $locked->newQuery()->whereKey($locked->getKey())->increment('session_version', 1, [
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'updated_at' => now(),
            ]);
            $locked->tokens()->delete();

            return $locked->refresh();
        });

        // Never preserve a session or change the caller's snapshot on rollback.
        $user->setRawAttributes($updated->getAttributes(), true);

        if ($currentRequest !== null) {
            $guard = Auth::guard('web');
            $guard->login($user, $remember);
            $currentRequest->session()->regenerate(true);
            $this->establish($currentRequest, $user);

            if (! $remember && $currentRequest->cookies->has($guard->getRecallerName())) {
                $guard->getCookieJar()->queue($guard->getCookieJar()->forget($guard->getRecallerName()));
            }
        }
    }

    private function fingerprint(User $user): string
    {
        return hash('sha256', $user->getAuthPassword());
    }

    private function remembersBrowser(Request $request, User $user): bool
    {
        $guard = Auth::guard('web');
        $value = $request->cookies->get($guard->getRecallerName());

        if (! is_string($value)) {
            return false;
        }

        $recaller = new Recaller($value);

        return $recaller->valid()
            && (string) $recaller->id() === (string) $user->getAuthIdentifier()
            && is_string($user->getRememberToken())
            && hash_equals($user->getRememberToken(), $recaller->token());
    }
}
