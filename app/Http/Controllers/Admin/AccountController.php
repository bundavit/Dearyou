<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CreatorStorage;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit()
    {
        return view('admin.account', [
            'profileImageLimitMb' => app(PlatformSettings::class)->profileImageLimitMb(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $settings = app(PlatformSettings::class);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'required|current_password',
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$settings->kilobytes($settings->profileImageLimitMb())],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);
        $emailChanged = $data['email'] !== $user->email;
        $oldAvatar = $user->avatar_path;
        $avatarPath = $oldAvatar;

        if ($request->boolean('remove_avatar')) {
            $avatarPath = null;
        } elseif ($request->hasFile('avatar')) {
            app(CreatorStorage::class)->ensureWithinQuota($user, [$request->file('avatar')], [$oldAvatar]);
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'avatar_path' => $avatarPath,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
        ])->save();

        if ($oldAvatar && $oldAvatar !== $avatarPath) {
            Storage::disk('public')->delete($oldAvatar);
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

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

        return back()->with('success', 'Password changed successfully.');
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);
        $user = $request->user();

        abort_if($user->isAdmin(), 422, 'Platform administrator accounts cannot be deleted here.');

        $user->tokens()->delete();
        $user->delete();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Your account was deleted.');
    }
}
