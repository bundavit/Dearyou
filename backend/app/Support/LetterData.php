<?php

namespace App\Support;

use App\Http\Requests\LetterRequest;
use App\Models\Letter;
use Illuminate\Support\Facades\Storage;

class LetterData
{
    private const UPLOADS = [
        ['image', 'image_path', 'remove_image', 'letters'],
        ['audio', 'audio_path', 'remove_audio', 'letters/audio'],
        ['sender_profile', 'sender_profile_path', 'remove_sender_profile', 'letters/profiles'],
        ['recipient_profile', 'recipient_profile_path', 'remove_recipient_profile', 'letters/profiles'],
    ];

    public function __construct(private readonly CreatorStorage $storage) {}

    public function prepare(LetterRequest $request, ?Letter $letter = null): array
    {
        $files = [];
        $replacedPaths = [];
        $mediaInputs = [];

        foreach (self::UPLOADS as [$input, $column, $removeInput]) {
            $files[] = $request->file($input);
            $mediaInputs[] = $input;
            $mediaInputs[] = $removeInput;

            if ($letter && ($request->hasFile($input) || $request->boolean($removeInput))) {
                $replacedPaths[] = $letter->{$column};
            }
        }

        $this->storage->ensureWithinQuota($request->user(), $files, $replacedPaths);

        $data = $request->safe()->except($mediaInputs);
        if (array_key_exists('allow_response', $data)) {
            $data['allow_response'] = $request->boolean('allow_response');
        }

        foreach (self::UPLOADS as [$input, $column, $removeInput, $directory]) {
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

        return $data;
    }
}
