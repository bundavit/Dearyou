<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LetterRequest;
use App\Models\Letter;
use App\Support\CreatorRoute;
use App\Support\LetterData;
use App\Support\LetterPublisher;
use App\Support\MediaFiles;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;

class LetterController extends Controller
{
    public function index(Request $request)
    {
        $letters = auth()->user()->letters()->with('link');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $letters->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('recipient_name', 'like', "%{$search}%")
                ->orWhere('sender_name', 'like', "%{$search}%"));
        }
        if ($request->filled('status')) {
            $letters->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $letters->where('category', $request->category);
        }

        return view('admin.letters.index', [
            'letters' => $letters->latest()->paginate(12)->withQueryString(),
        ]);
    }

    public function create(PlatformSettings $settings)
    {
        return view('admin.letters.form', [
            'letter' => new Letter(['expiry_minutes' => $settings->defaultExpiryMinutes()]),
            'expiryOptions' => $settings->expiryOptions(),
            'creationSettings' => $settings->all(),
            'categories' => $settings->categoryOptions(),
        ]);
    }

    public function store(LetterRequest $request, LetterData $data)
    {
        $letter = auth()->user()->letters()->create($data->prepare($request) + [
            'allow_response' => false,
        ]);

        return redirect()->route(CreatorRoute::name('letters.edit'), $letter)->with('success', 'Letter created.');
    }

    public function show(Letter $letter)
    {
        $this->authorize('view', $letter);

        return view('admin.letters.show', [
            'letter' => $letter->load(['link', 'memories.images'])->loadCount('responses'),
        ]);
    }

    public function edit(Letter $letter, PlatformSettings $settings)
    {
        $this->authorize('update', $letter);

        return view('admin.letters.form', [
            'letter' => $letter->load('memories.images'),
            'expiryOptions' => $settings->expiryOptions(),
            'creationSettings' => $settings->all(),
            'categories' => $settings->categoryOptions($letter->category),
        ]);
    }

    public function update(LetterRequest $request, Letter $letter, LetterData $data)
    {
        $this->authorize('update', $letter);
        $letter->update($data->prepare($request, $letter) + [
            'allow_response' => false,
        ]);

        return back()->with('success', 'Letter saved.');
    }

    public function preview(Letter $letter)
    {
        $this->authorize('view', $letter);

        $letter->load('memories.images');

        return view('public.letter', compact('letter'))->with('preview', true);
    }

    public function publish(Letter $letter, LetterPublisher $publisher)
    {
        $this->authorize('update', $letter);
        $publisher->publish($letter);

        return back()->with('success', "Letter published for {$letter->expiryDurationLabel()}.");
    }

    public function unpublish(Letter $letter, LetterPublisher $publisher)
    {
        $this->authorize('update', $letter);
        $publisher->unpublish($letter);

        return back()->with('success', 'Letter unpublished.');
    }

    public function regenerate(Letter $letter, LetterPublisher $publisher)
    {
        $this->authorize('update', $letter);
        $publisher->regenerate($letter);

        return back()->with('success', "Private link regenerated for {$letter->expiryDurationLabel()}.");
    }

    public function disable(Letter $letter, LetterPublisher $publisher)
    {
        $this->authorize('update', $letter);
        $publisher->disable($letter);

        return back()->with('success', 'Private link disabled.');
    }

    public function destroy(Letter $letter)
    {
        $this->authorize('delete', $letter);
        app(MediaFiles::class)->detachLetter($letter);
        $letter->delete();

        return redirect()->route(CreatorRoute::name('letters.index'))->with('success', 'Letter deleted.');
    }
}
