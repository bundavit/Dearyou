<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit()
    {
        return view('admin.account');
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|current_password',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return back()->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->user()->tokens()->delete();
        $request->session()->regenerate();

        return back()->with('success', 'Password changed and API tokens revoked.');
    }
}
