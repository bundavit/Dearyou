<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmailIdentity;
use App\Support\LoginDestination;
use App\Support\SessionSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create(Request $request)
    {
        return view('auth.login', [
            'adminLogin' => $request->routeIs('login.legacy'),
        ]);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = EmailIdentity::findActive($credentials['email']);
        $credentials['email'] = $user?->email;
        $credentials['disabled_at'] = null;
        if ($request->routeIs('login.legacy.store')) {
            $credentials['role'] = User::ROLE_ADMIN;
        }

        if (! $user || ! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'The provided credentials are incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        app(SessionSecurity::class)->establish($request, Auth::user());

        return redirect()->to(app(LoginDestination::class)->resolve($request, Auth::user()));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been logged out.');
    }
}
