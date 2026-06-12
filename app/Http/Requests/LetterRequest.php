<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LetterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'font_style' => $this->input('font_style', 'classic'),
            'positive_button_text' => $this->input('positive_button_text') ?: 'Yes',
            'negative_button_text' => $this->input('negative_button_text') ?: 'No',
            'chapter_heading' => $this->input('chapter_heading') ?: 'A beautiful new chapter begins.',
        ]);
    }

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['confession', 'apology', 'birthday', 'anniversary', 'valentine', 'congratulations', 'thank-you', 'friendship', 'graduation', 'celebration', 'custom'])],
            'title' => 'required|string|max:150',
            'recipient_name' => 'nullable|string|max:100',
            'sender_name' => 'nullable|string|max:100',
            'body' => 'required|string|max:20000',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4|max:10240',
            'image_alt' => 'nullable|string|max:150',
            'remove_image' => 'boolean',
            'audio' => 'nullable|file|mimes:mp3,wav,ogg,m4a,aac|max:12288',
            'remove_audio' => 'boolean',
            'relationship_started_at' => 'nullable|date',
            'chapter_heading' => 'nullable|string|max:150',
            'sender_profile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'recipient_profile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240',
            'remove_sender_profile' => 'boolean',
            'remove_recipient_profile' => 'boolean',
            'theme' => 'required|string|max:40',
            'font_style' => ['required', Rule::in(['classic', 'elegant', 'modern', 'friendly', 'typewriter', 'handwritten', 'formal'])],
            'primary_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'secondary_color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'decoration_type' => ['required', Rule::in(['hearts', 'stars', 'balloons', 'confetti', 'flowers', 'sparkles', 'none'])],
            'allow_response' => 'boolean',
            'response_mode' => ['required', Rule::in(['none', 'message', 'buttons', 'buttons_with_message'])],
            'positive_button_text' => 'nullable|string|max:50',
            'negative_button_text' => 'nullable|string|max:50',
            'question_text' => 'nullable|string|max:200',
            'expires_at' => 'nullable|date|after:now',
        ];
    }
}
