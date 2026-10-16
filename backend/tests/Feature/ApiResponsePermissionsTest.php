<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiResponsePermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('readAbilities')]
    public function test_letter_details_only_include_responses_with_response_read_permission(array $abilities, bool $canReadResponses): void
    {
        $user = User::factory()->create();
        $letter = $user->letters()->create([
            'category' => 'custom', 'title' => 'Scoped letter', 'body' => 'Letter content.',
            'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8',
            'decoration_type' => 'sparkles', 'allow_response' => true, 'response_mode' => 'message',
        ]);
        $link = $letter->link()->create(['token' => str_repeat('s', 64)]);
        $letter->responses()->create([
            'letter_link_id' => $link->id, 'response_value' => 'message',
            'message' => 'Recipient response requires a separate permission.', 'submitted_at' => now(),
        ]);
        $token = $user->createToken('Scoped client', $abilities)->plainTextToken;

        $response = $this->withToken($token)->getJson("/api/letters/{$letter->id}")
            ->assertOk()->assertJsonPath('id', $letter->id)->assertJsonPath('body', 'Letter content.');

        if ($canReadResponses) {
            $response->assertJsonPath('responses.0.message', 'Recipient response requires a separate permission.');
            $this->withToken($token)->getJson('/api/responses')->assertOk()
                ->assertJsonPath('data.0.message', 'Recipient response requires a separate permission.');
        } else {
            $response->assertJsonMissingPath('responses')
                ->assertJsonMissing(['message' => 'Recipient response requires a separate permission.']);
            $this->withToken($token)->getJson('/api/responses')->assertForbidden();
        }
    }

    public static function readAbilities(): array
    {
        return [
            'letter read only' => [['letters:read'], false],
            'letter write is not response read' => [['letters:read', 'letters:write'], false],
            'combined explicit permissions' => [['letters:read', 'responses:read'], true],
            'wildcard permission' => [['*'], true],
        ];
    }

    public function test_response_read_alone_does_not_grant_letter_detail_access(): void
    {
        $user = User::factory()->create();
        $letter = $user->letters()->create([
            'category' => 'custom', 'title' => 'Scoped letter', 'body' => 'Letter content.',
            'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8',
            'decoration_type' => 'sparkles', 'response_mode' => 'message',
        ]);
        $token = $user->createToken('Responses only', ['responses:read'])->plainTextToken;

        $this->withToken($token)->getJson("/api/letters/{$letter->id}")->assertForbidden();
    }
}
