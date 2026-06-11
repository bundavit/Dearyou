<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token_name' => 'required|string|max:80',
            'access' => ['required', Rule::in(['read', 'write'])],
        ]);
        $abilities = $data['access'] === 'write'
            ? ['letters:read', 'letters:write', 'responses:read']
            : ['letters:read', 'responses:read'];
        $token = $request->user()->createToken($data['token_name'], $abilities);

        return back()
            ->with('success', 'API token created. Copy it now; it will not be shown again.')
            ->with('new_api_token', $token->plainTextToken);
    }

    public function destroy(Request $request, PersonalAccessToken $token)
    {
        abort_unless((int) $token->tokenable_id === (int) $request->user()->id && $token->tokenable_type === $request->user()::class, 403);
        $token->delete();

        return back()->with('success', 'API token revoked.');
    }
}
