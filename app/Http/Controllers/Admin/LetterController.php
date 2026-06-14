<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LetterRequest;
use App\Models\Letter;
use App\Support\CreatorRoute;
use App\Support\CreatorStorage;
use App\Support\LetterPublisher;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function store(LetterRequest $request, CreatorStorage $storage)
    {
        $storage->ensureWithinQuota($request->user(), $this->uploadedFiles($request));
        $data = $this->letterData($request);
        $letter = auth()->user()->letters()->create($data);

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

    public function update(LetterRequest $request, Letter $letter, CreatorStorage $storage)
    {
        $this->authorize('update', $letter);
        $storage->ensureWithinQuota(
            $request->user(),
            $this->uploadedFiles($request),
            $this->replacedPaths($request, $letter),
        );
        $letter->update($this->letterData($request, $letter));

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

        return redirect()->route(CreatorRoute::name('letters.index'))->with('success', 'Letter deleted.');
    }

    private function letterData(LetterRequest $request, ?Letter $letter = null): array
    {
        $data = $request->safe()->except([
            'image',
            'remove_image',
            'audio',
            'remove_audio',
            'sender_profile',
            'recipient_profile',
            'remove_sender_profile',
            'remove_recipient_profile',
        ]);
        $data['allow_response'] = $request->boolean('allow_response');

        $this->handleUpload($request, $letter, $data, 'image', 'image_path', 'remove_image', 'letters');
        $this->handleUpload($request, $letter, $data, 'audio', 'audio_path', 'remove_audio', 'letters/audio');
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

    private function uploadedFiles(LetterRequest $request): array
    {
        return [
            $request->file('image'),
            $request->file('audio'),
            $request->file('sender_profile'),
            $request->file('recipient_profile'),
        ];
    }

    private function replacedPaths(LetterRequest $request, Letter $letter): array
    {
        return collect([
            ['image', 'remove_image', 'image_path'],
            ['audio', 'remove_audio', 'audio_path'],
            ['sender_profile', 'remove_sender_profile', 'sender_profile_path'],
            ['recipient_profile', 'remove_recipient_profile', 'recipient_profile_path'],
        ])->filter(fn (array $upload) => $request->hasFile($upload[0]) || $request->boolean($upload[1]))
            ->map(fn (array $upload) => $letter->{$upload[2]})
            ->filter()
            ->all();
    }
}
