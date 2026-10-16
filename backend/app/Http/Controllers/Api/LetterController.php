<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LetterRequest;
use App\Models\Letter;
use App\Models\Response;
use App\Support\LetterData;
use App\Support\LetterPublisher;
use App\Support\MediaAccess;
use App\Support\MediaFiles;

class LetterController extends Controller
{
    public function index()
    {
        $this->requireAbility('letters:read');

        return auth()->user()->letters()->with('link')->latest()->paginate();
    }

    public function store(LetterRequest $request, LetterData $data)
    {
        $this->requireAbility('letters:write');

        return response()->json(auth()->user()->letters()->create($data->prepare($request)), 201);
    }

    public function show(Letter $letter)
    {
        $this->authorize('view', $letter);
        $this->requireAbility('letters:read');

        $letter->load('link', 'memories.images');

        if (request()->user()->tokenCan('responses:read')) {
            $letter->load('responses');
        } else {
            $letter->unsetRelation('responses');
        }

        return $letter;
    }

    public function update(LetterRequest $request, Letter $letter, LetterData $data)
    {
        $this->authorize('update', $letter);
        $this->requireAbility('letters:write');
        $letter->update($data->prepare($request, $letter));

        return $letter;
    }

    public function media(Letter $letter, string $media, MediaAccess $files)
    {
        $this->authorize('view', $letter);
        $this->requireAbility('letters:read');

        return $files->response($letter, $media);
    }

    public function destroy(Letter $letter)
    {
        $this->authorize('delete', $letter);
        $this->requireAbility('letters:write');
        app(MediaFiles::class)->detachLetter($letter);
        $letter->delete();

        return response()->noContent();
    }

    public function responses()
    {
        $this->requireAbility('responses:read');

        return Response::with('letter')->whereHas('letter', fn ($q) => $q->where('user_id', auth()->id()))->latest()->paginate();
    }

    public function publish(Letter $letter, LetterPublisher $publisher)
    {
        $this->authorize('update', $letter);
        $this->requireAbility('letters:write');

        return $publisher->publish($letter);
    }

    public function unpublish(Letter $letter, LetterPublisher $publisher)
    {
        $this->authorize('update', $letter);
        $this->requireAbility('letters:write');

        return $publisher->unpublish($letter);
    }

    private function requireAbility(string $ability): void
    {
        abort_unless(request()->user()->tokenCan($ability), 403, 'This token does not have the required ability.');
    }
}
