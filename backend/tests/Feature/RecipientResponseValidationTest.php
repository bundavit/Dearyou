<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use App\Support\LetterPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipientResponseValidationTest extends TestCase
{
    use RefreshDatabase;

    private function publishedLetter(string $mode, bool $allowNotes = true): Letter
    {
        $letter = User::factory()->create()->letters()->create([
            'category' => 'custom',
            'title' => 'Response validation',
            'body' => 'A private letter',
            'theme' => 'warm',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'none',
            'status' => 'draft',
            'allow_response' => $allowNotes,
            'response_mode' => $mode,
            'expiry_minutes' => 60,
        ]);

        return app(LetterPublisher::class)->publish($letter);
    }

    public static function invalidChoices(): array
    {
        return [
            'unknown button choice' => ['buttons', 'qa-invalid-choice'],
            'reaction in button mode' => ['buttons_with_message', 'happy'],
            'button in reaction mode' => ['reactions', 'positive'],
            'button in message mode' => ['message', 'positive'],
            'array value' => ['buttons', ['positive']],
        ];
    }

    #[DataProvider('invalidChoices')]
    public function test_rejects_values_outside_the_configured_response_mode(string $mode, mixed $value): void
    {
        $letter = $this->publishedLetter($mode);

        $this->postJson("/l/{$letter->link->token}/response", [
            'response_value' => $value,
            'message' => 'A valid note',
        ])->assertUnprocessable()->assertJsonValidationErrors('response_value');

        $this->assertDatabaseCount('responses', 0);
    }

    public static function validChoices(): array
    {
        return [
            'positive button' => ['buttons', 'positive'],
            'negative button' => ['buttons', 'negative'],
            'button with optional note' => ['buttons_with_message', 'positive'],
            'happy reaction' => ['reactions', 'happy'],
            'loved reaction' => ['reactions', 'loved'],
            'surprised reaction' => ['reactions', 'surprised'],
            'emotional reaction' => ['reactions', 'emotional'],
            'thankful reaction' => ['reactions', 'thankful'],
            'hopeful reaction' => ['reactions', 'hopeful'],
        ];
    }

    #[DataProvider('validChoices')]
    public function test_accepts_supported_choices_without_requiring_an_optional_note(string $mode, string $value): void
    {
        $letter = $this->publishedLetter($mode);

        $this->postJson("/l/{$letter->link->token}/response", [
            'response_value' => $value,
        ])->assertOk();

        $this->assertDatabaseHas('responses', [
            'letter_id' => $letter->id,
            'response_value' => $value,
            'message' => null,
        ]);
    }

    public function test_message_mode_requires_nonblank_text(): void
    {
        $letter = $this->publishedLetter('message');

        foreach ([null, '', '   '] as $message) {
            $this->postJson("/l/{$letter->link->token}/response", [
                'response_value' => 'message',
                'message' => $message,
            ])->assertUnprocessable()->assertJsonValidationErrors('message');
        }

        $this->assertDatabaseCount('responses', 0);

        $this->postJson("/l/{$letter->link->token}/response", [
            'response_value' => 'message',
            'message' => 'A meaningful reply',
        ])->assertOk();

        $this->assertDatabaseHas('responses', ['message' => 'A meaningful reply']);
    }

    public function test_button_choices_discard_notes_when_private_notes_are_disabled(): void
    {
        $letter = $this->publishedLetter('buttons_with_message', false);

        $this->postJson("/l/{$letter->link->token}/response", [
            'response_value' => 'negative',
            'message' => 'This note must not be saved',
        ])->assertOk();

        $this->assertDatabaseHas('responses', [
            'letter_id' => $letter->id,
            'response_value' => 'negative',
            'message' => null,
        ]);
    }

    public function test_disabled_message_responses_remain_unavailable(): void
    {
        $letter = $this->publishedLetter('message', false);

        $this->postJson("/l/{$letter->link->token}/response", [
            'response_value' => 'message',
            'message' => 'Not allowed',
        ])->assertNotFound();

        $this->assertDatabaseCount('responses', 0);
    }
}
