<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Throwable;

class RegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => User::ROLE_USER,
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            Log::warning('Registration verification email could not be requested.', ['user_id' => $user->id, 'exception_type' => $exception::class]);

            return redirect()->route('verification.notice')
                ->withErrors(['delivery' => 'Your account was created, but we could not request the verification email. Try sending a new code.']);
        }

        return redirect()->route('verification.notice');
    }
}
