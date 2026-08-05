<?php

namespace App\Http\Requests;

use App\Support\PlatformSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $settings = app(PlatformSettings::class);

        $this->merge([
            'font_style' => $this->input('font_style', 'classic'),
            'envelope_style' => $this->input('envelope_style', 'classic'),
            'seal_style' => $this->input('seal_style', 'round'),
            'positive_button_text' => $this->input('positive_button_text') ?: 'Yes',
            'negative_button_text' => $this->input('negative_button_text') ?: 'No',
            'chapter_heading' => $this->input('chapter_heading') ?: 'A beautiful new chapter begins.',
            'expiry_minutes' => $this->input('expiry_minutes', $settings->defaultExpiryMinutes()),
        ]);
    }

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $settings = app(PlatformSettings::class);
        $currentCategory = $this->route('letter')?->category;

        return [
            'category' => ['required', Rule::in(array_keys($settings->categoryOptions($currentCategory)))],
            'title' => 'required|string|max:150',
            'recipient_name' => 'nullable|string|max:100',
            'sender_name' => 'nullable|string|max:100',
            'body' => 'required|string|max:20000',
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:'.$settings->kilobytes($settings->letterMediaLimitMb())],
            'image_alt' => 'nullable|string|max:150',
            'remove_image' => 'boolean',
            'audio' => [
                'nullable',
                'file',
                'extensions:mp3,wav,ogg,m4a,aac',
                'mimetypes:audio/mpeg,audio/mp3,audio/x-mp3,audio/mpeg3,audio/x-mpeg-3,audio/wav,audio/x-wav,audio/vnd.wave,audio/ogg,application/ogg,audio/mp4,video/mp4,audio/x-m4a,audio/aac,audio/x-aac,application/octet-stream',
                'max:'.$settings->kilobytes($settings->audioLimitMb()),
            ],
            'remove_audio' => 'boolean',
            'relationship_started_at' => 'nullable|date',
            'chapter_heading' => 'nullable|string|max:150',
            'sender_profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$settings->kilobytes($settings->profileImageLimitMb())],
            'recipient_profile' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$settings->kilobytes($settings->profileImageLimitMb())],
            'remove_sender_profile' => 'boolean',
            'remove_recipient_profile' => 'boolean',
            'theme' => 'required|string|max:40',
            'font_style' => ['required', Rule::in(['classic', 'elegant', 'modern', 'friendly', 'typewriter', 'handwritten', 'formal'])],
            'envelope_style' => ['required', Rule::in(['classic', 'rounded', 'airmail', 'vintage', 'gift', 'petal', 'pocket', 'ribbon', 'lace', 'postcard'])],
            'seal_style' => ['required', Rule::in(['round', 'heart', 'star', 'flower', 'diamond', 'square', 'scallop', 'moon', 'sparkle', 'sun'])],
            'primary_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'secondary_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'decoration_type' => ['required', Rule::in(['hearts', 'stars', 'balloons', 'confetti', 'flowers', 'sparkles', 'none'])],
            'allow_response' => 'boolean',
            'response_mode' => ['required', Rule::in(['none', 'message', 'buttons', 'buttons_with_message', 'reactions'])],
            'positive_button_text' => 'nullable|string|max:50',
            'negative_button_text' => 'nullable|string|max:50',
            'question_text' => 'nullable|string|max:200',
            'expiry_minutes' => ['required', 'integer', Rule::in(array_keys($settings->expiryOptions()))],
        ];
    }
}
