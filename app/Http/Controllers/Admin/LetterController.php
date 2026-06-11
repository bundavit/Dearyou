<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LetterRequest;
use App\Models\Letter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    public function create()
    {
        return view('admin.letters.form', ['letter' => new Letter]);
    }

    public function store(LetterRequest $request)
    {
        $data = $this->letterData($request);
        $letter = auth()->user()->letters()->create($data);

        return redirect()->route('admin.letters.edit', $letter)->with('success', 'Letter created.');
    }

    public function show(Letter $letter)
    {
        $this->own($letter);

        return view('admin.letters.show', [
            'letter' => $letter->load(['link', 'memories'])->loadCount('responses'),
        ]);
    }

    public function edit(Letter $letter)
    {
        $this->own($letter);

        return view('admin.letters.form', ['letter' => $letter->load('memories')]);
    }

    public function update(LetterRequest $request, Letter $letter)
    {
        $this->own($letter);
        $letter->update($this->letterData($request, $letter));

        return back()->with('success', 'Letter saved.');
    }

    public function preview(Letter $letter)
    {
        $this->own($letter);

        return view('public.letter', compact('letter'))->with('preview', true);
    }

    public function publish(Letter $letter)
    {
        $this->own($letter);
        $letter->update(['status' => 'published', 'published_at' => now()]);
        $letter->link()->updateOrCreate([], ['token' => Str::random(64), 'is_active' => true, 'expires_at' => $letter->expires_at]);

        return back()->with('success', 'Letter published.');
    }

    public function unpublish(Letter $letter)
    {
        $this->own($letter);
        $letter->update(['status' => 'unpublished']);

        return back()->with('success', 'Letter unpublished.');
    }

    public function regenerate(Letter $letter)
    {
        $this->own($letter);
        $letter->link()->updateOrCreate([], ['token' => Str::random(64), 'is_active' => true, 'last_regenerated_at' => now()]);

        return back()->with('success', 'Private link regenerated.');
    }

    public function disable(Letter $letter)
    {
        $this->own($letter);
        $letter->link?->update(['is_active' => false]);

        return back()->with('success', 'Private link disabled.');
    }

    public function destroy(Letter $letter)
    {
        $this->own($letter);
        Storage::disk('public')->delete(array_filter([
            $letter->image_path,
            $letter->sender_profile_path,
            $letter->recipient_profile_path,
            ...$letter->memories()->pluck('image_path')->filter()->all(),
        ]));
        $letter->delete();

        return redirect()->route('admin.letters.index')->with('success', 'Letter deleted.');
    }

    private function own(Letter $letter): void
    {
        abort_unless($letter->user_id === auth()->id(), 403);
    }

    private function letterData(LetterRequest $request, ?Letter $letter = null): array
    {
        $data = $request->safe()->except([
            'image',
            'remove_image',
            'sender_profile',
            'recipient_profile',
            'remove_sender_profile',
            'remove_recipient_profile',
        ]);
        $data['allow_response'] = $request->boolean('allow_response');

        $this->handleUpload($request, $letter, $data, 'image', 'image_path', 'remove_image', 'letters');
        $this->handleUpload($request, $letter, $data, 'sender_profile', 'sender_profile_path', 'remove_sender_profile', 'letters/profiles');
        $this->handleUpload($request, $letter, $data, 'recipient_profile', 'recipient_profile_path', 'remove_recipient_profile', 'letters/profiles');

        return $data;
    }

    private function handleUpload(
        LetterRequest $request,
        ?Letter $letter,
        array &$data,
        string $input,
        string $column,
        string $removeInput,
        string $directory,
    ): void {
        if ($letter && ($request->boolean($removeInput) || $request->hasFile($input))) {
            if ($letter->{$column}) {
                Storage::disk('public')->delete($letter->{$column});
            }
            $data[$column] = null;
        }

        if ($request->hasFile($input)) {
            $data[$column] = $request->file($input)->store($directory, 'public');
        }
    }
}
