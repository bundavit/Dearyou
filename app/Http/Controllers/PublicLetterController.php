<?php

namespace App\Http\Controllers;

use App\Models\LetterLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicLetterController extends Controller
{
    public function show(string $token)
    {
        $link = LetterLink::with('letter.memories.images')->where('token', $token)->first();
        abort_unless($link?->letter?->isPubliclyAvailable(), 404);
        DB::table('letters')->where('id', $link->letter->id)->update([
            'open_count' => DB::raw('open_count + 1'),
            'opened_at' => DB::raw('COALESCE(opened_at, CURRENT_TIMESTAMP)'),
        ]);

        return view('public.letter', ['letter' => $link->letter, 'link' => $link]);
    }

    public function download(Request $request, string $token)
    {
        $link = LetterLink::with('letter.memories.images')->where('token', $token)->first();
        abort_unless($link?->letter?->isPubliclyAvailable(), 404);

        $letter = $link->letter;
        $filename = Str::slug($letter->title ?: 'dearyou-letter') ?: 'dearyou-letter';

        if ($request->query('format') === 'html') {
            return response()
                ->view('public.download', ['letter' => $letter], 200, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'-keepsake.html"',
                ]);
        }

        $content = trim(implode("\n\n", array_filter([
            'Dear '.$letter->recipientLabel().',',
            $letter->title,
            $letter->body,
            "With care,\n".$letter->senderLabel(),
        ])))."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.txt"',
        ]);
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
