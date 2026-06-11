<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LetterRequest;
use App\Models\Letter;
use App\Models\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LetterController extends Controller
{
    public function index()
    {
        $this->requireAbility('letters:read');

        return auth()->user()->letters()->with('link')->latest()->paginate();
    }

    public function store(LetterRequest $request)
    {
        $this->requireAbility('letters:write');

        return response()->json(auth()->user()->letters()->create($request->validated()), 201);
    }

    public function show(Letter $letter)
    {
        $this->own($letter);
        $this->requireAbility('letters:read');

        return $letter->load('link', 'responses', 'memories.images');
    }

    public function update(LetterRequest $request, Letter $letter)
    {
        $this->own($letter);
        $this->requireAbility('letters:write');
        $letter->update($request->validated());

        return $letter;
    }

    public function destroy(Letter $letter)
    {
        $this->own($letter);
        $this->requireAbility('letters:write');
        $memoryImages = $letter->memories()->with('images')->get()
            ->flatMap(fn ($memory) => $memory->images->pluck('image_path'))
            ->all();

        Storage::disk('public')->delete(array_filter([
            $letter->image_path,
            $letter->audio_path,
            $letter->sender_profile_path,
            $letter->recipient_profile_path,
            ...$letter->memories()->pluck('image_path')->filter()->all(),
            ...$memoryImages,
        ]));
        $letter->delete();

        return response()->noContent();
    }

    public function responses()
    {
        $this->requireAbility('responses:read');

        return Response::with('letter')->whereHas('letter', fn ($q) => $q->where('user_id', auth()->id()))->latest()->paginate();
    }

    public function publish(Letter $letter)
    {
        $this->own($letter);
        $this->requireAbility('letters:write');
        $letter->update(['status' => 'published', 'published_at' => now()]);
        $letter->link()->updateOrCreate([], ['token' => Str::random(64), 'is_active' => true]);

        return $letter->load('link');
    }

    public function unpublish(Letter $letter)
    {
        $this->own($letter);
        $this->requireAbility('letters:write');
        $letter->update(['status' => 'unpublished']);

        return $letter;
    }

    private function own(Letter $letter): void
    {
        abort_unless($letter->user_id === auth()->id(), 403);
    }

    private function requireAbility(string $ability): void
    {
        abort_unless(request()->user()->tokenCan($ability), 403, 'This token does not have the required ability.');
    }
}
