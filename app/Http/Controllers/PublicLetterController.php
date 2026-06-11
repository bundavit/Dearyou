<?php

namespace App\Http\Controllers;

use App\Models\LetterLink;
use Illuminate\Http\Request;

class PublicLetterController extends Controller
{
    public function show(string $token)
    {
        $link = LetterLink::with('letter.memories.images')->where('token', $token)->first();
        abort_unless($link?->letter?->isPubliclyAvailable(), 404);
        $link->letter->updateQuietly(['opened_at' => $link->letter->opened_at ?? now()]);

        return view('public.letter', ['letter' => $link->letter, 'link' => $link]);
    }

    public function respond(Request $request, string $token)
    {
        $link = LetterLink::with('letter')->where('token', $token)->first();
        abort_unless($link?->letter?->isPubliclyAvailable() && $link->letter->allow_response, 404);
        $data = $request->validate(['response_value' => 'required|string|max:100', 'message' => 'nullable|string|max:3000']);
        $link->letter->responses()->create($data + ['letter_link_id' => $link->id, 'submitted_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('public.partials.response-result', [
                    'letter' => $link->letter,
                    'responseValue' => $data['response_value'],
                ])->render(),
            ]);
        }

        return back()
            ->with('response_sent', true)
            ->with('response_value', $data['response_value']);
    }
}
