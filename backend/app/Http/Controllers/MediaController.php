<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RestrictAdminNetwork;
use App\Models\Letter;
use App\Models\LetterLink;
use App\Support\MediaAccess;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function owner(Letter $letter, string $media, MediaAccess $files)
    {
        $this->authorize('view', $letter);
        $this->restrictAdmin();

        return $files->response($letter, $media);
    }

    public function public(string $token, string $media, MediaAccess $files)
    {
        $link = LetterLink::with('letter')->where('token', $token)->first();
        abort_unless($link?->letter?->isPubliclyAvailable(), 404);

        return $files->response($link->letter, $media);
    }

    public function avatar(Request $request, MediaAccess $files)
    {
        $this->restrictAdmin();
        abort_unless($request->user()->avatar_path, 404);

        return $files->file($request->user()->avatar_path);
    }

    private function restrictAdmin(): void
    {
        if (request()->user()?->isAdmin()) {
            app(RestrictAdminNetwork::class)->handle(request(), fn () => response()->noContent());
        }
    }
}
