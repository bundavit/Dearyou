# Initial administrator provisioning

Use this only for a new, isolated installation that has no administrator. Existing production accounts are preserved; normal releases do not run these commands. The sample seeder is local/testing only and cannot provision staging or production.

The owner first verifies the checkout, environment and database are the intended new installation. Run `php artisan tinker` from that installation's `backend/` directory. Enter the following statements interactively; the password prompt hides input and keeps its value out of command history:

```php
(function () {
    if (! app()->environment(['staging', 'production'])) {
        throw new \RuntimeException('Use this provisioning procedure only for an initial staging or production installation.');
    }
    if (\App\Models\User::withTrashed()->where('role', \App\Models\User::ROLE_ADMIN)->exists()) {
        throw new \RuntimeException('An administrator already exists. Use the existing account or its recovery flow.');
    }
    $email = \App\Support\EmailIdentity::normalize(\Laravel\Prompts\text('Initial administrator email'));
    $name = \Laravel\Prompts\text('Administrator name');
    $password = \Laravel\Prompts\password('New password (at least 12 characters)');
    $password_confirmation = \Laravel\Prompts\password('Confirm password');
    validator(compact('email', 'name', 'password', 'password_confirmation'), ['email' => ['required', 'email', new \App\Rules\UniqueEmailIdentity], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols()]])->validate();
    \App\Models\User::create(['email' => $email, 'name' => $name, 'password' => $password, 'role' => \App\Models\User::ROLE_ADMIN, 'email_verified_at' => now()]);
})();
```

Paste the entire closure as one block. Validation failure exits before creation; the password stays inside the closure rather than becoming a printed Tinker assignment. Existing, disabled, deleted or ambiguous identities must not be overwritten or promoted. This explicitly provisions a trusted administrator without sending verification mail. Use a synthetic identity on staging. Other creators should use normal registration/verification, with staging's log-mail inspection where appropriate.

Exit Tinker, sign in through the ordinary page and verify `/admin/platform`. Do not save passwords or private account output in Git or QA reports. Repeated deployment does not require creating another administrator.
