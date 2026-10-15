<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiMediaTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'custom',
            'title' => 'API media letter',
            'body' => 'A letter with media.',
            'theme' => 'warm',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'sparkles',
            'allow_response' => 1,
            'response_mode' => 'message',
        ], $overrides);
    }

    private function uploads(): array
    {
        return [
            'image' => UploadedFile::fake()->image('letter.png'),
            'audio' => UploadedFile::fake()->create('song.mp3', 10, 'audio/mpeg'),
            'sender_profile' => UploadedFile::fake()->image('sender.png'),
            'recipient_profile' => UploadedFile::fake()->image('recipient.png'),
        ];
    }

    public function test_api_creates_media_paths_instead_of_persisting_upload_inputs(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/letters', $this->payload([
            ...$this->uploads(),
            'remove_image' => 0,
            'remove_audio' => 0,
            'remove_sender_profile' => 0,
            'remove_recipient_profile' => 0,
            'image_path' => 'letters/someone-elses.png',
            'user_id' => 999999,
        ]))->assertCreated()->assertJsonPath('allow_response', true);

        $letter = $user->letters()->sole();
        $response->assertJsonPath('id', $letter->id);
        $this->assertNotSame('letters/someone-elses.png', $letter->image_path);
        foreach (['image_path', 'audio_path', 'sender_profile_path', 'recipient_profile_path'] as $column) {
            $this->assertNotEmpty($letter->{$column});
            $response->assertJsonPath($column, $letter->{$column});
            Storage::disk('public')->assertExists($letter->{$column});
        }
        $this->assertCount(4, Storage::disk('public')->allFiles());
    }

    public function test_api_replaces_and_removes_all_letter_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;
        $letter = $user->letters()->create($this->payload());

        // Multipart clients use POST with method override for an update containing files.
        $this->withToken($token)->withHeader('Accept', 'application/json')
            ->post("/api/letters/{$letter->id}", $this->payload([
                '_method' => 'PUT',
                ...$this->uploads(),
            ]))->assertOk();

        $columns = ['image_path', 'audio_path', 'sender_profile_path', 'recipient_profile_path'];
        $originalPaths = $letter->fresh()->only($columns);
        foreach ($originalPaths as $path) {
            Storage::disk('public')->assertExists($path);
        }

        $this->withToken($token)->putJson("/api/letters/{$letter->id}", $this->payload([
            ...$this->uploads(),
            'remove_image' => 1,
        ]))->assertOk();
        $letter->refresh();
        foreach ($originalPaths as $column => $path) {
            Storage::disk('public')->assertMissing($path);
            $this->assertNotSame($path, $letter->{$column});
            Storage::disk('public')->assertExists($letter->{$column});
        }
        $this->assertCount(4, Storage::disk('public')->allFiles());

        $this->withToken($token)->putJson("/api/letters/{$letter->id}", $this->payload([
            'remove_image' => 1,
            'remove_audio' => 1,
            'remove_sender_profile' => 1,
            'remove_recipient_profile' => 1,
            'allow_response' => 0,
        ]))->assertOk()->assertJsonPath('allow_response', false);

        foreach ($letter->fresh()->only($columns) as $path) {
            $this->assertNull($path);
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_api_metadata_update_preserves_existing_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;
        Storage::disk('public')->put('letters/keep.png', 'existing media');
        $letter = $user->letters()->create($this->payload(['image_path' => 'letters/keep.png']));

        $this->withToken($token)->putJson("/api/letters/{$letter->id}", $this->payload([
            'title' => 'Updated title',
        ]))->assertOk()->assertJsonPath('image_path', 'letters/keep.png');

        Storage::disk('public')->assertExists('letters/keep.png');
    }

    public function test_api_keeps_response_setting_when_optional_input_is_omitted(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;
        $letter = $user->letters()->create($this->payload(['allow_response' => true]));
        $payload = $this->payload();
        unset($payload['allow_response']);

        $this->withToken($token)->putJson("/api/letters/{$letter->id}", $payload)
            ->assertOk()->assertJsonPath('allow_response', true);

        $this->assertTrue($letter->fresh()->allow_response);
    }

    public function test_api_rejects_invalid_media_without_changing_existing_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;
        Storage::disk('public')->put('letters/keep.png', 'existing media');
        $letter = $user->letters()->create($this->payload(['image_path' => 'letters/keep.png']));

        $temporaryFile = tmpfile();
        fwrite($temporaryFile, '<?php echo "not an image";');
        $upload = new UploadedFile(stream_get_meta_data($temporaryFile)['uri'], 'attack.png', 'image/png', null, true);
        try {
            $this->withToken($token)->putJson("/api/letters/{$letter->id}", $this->payload([
                'image' => $upload,
                'remove_image' => 1,
            ]))->assertUnprocessable()->assertJsonValidationErrors('image');
        } finally {
            fclose($temporaryFile);
        }

        $this->assertSame('letters/keep.png', $letter->fresh()->image_path);
        Storage::disk('public')->assertExists('letters/keep.png');
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_api_create_enforces_creator_storage_quota(): void
    {
        Storage::fake('public');
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;

        $this->withToken($token)->postJson('/api/letters', $this->payload([
            'image' => UploadedFile::fake()->image('large.png')->size(1100),
        ]))->assertUnprocessable()->assertJsonValidationErrors('media');

        $this->assertSame(0, $user->letters()->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_api_update_counts_replaced_media_against_storage_quota(): void
    {
        Storage::fake('public');
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create();
        $token = $user->createToken('media', ['letters:write'])->plainTextToken;
        Storage::disk('public')->put('letters/existing.png', str_repeat('x', 800 * 1024));
        $letter = $user->letters()->create($this->payload(['image_path' => 'letters/existing.png']));

        $this->withToken($token)->putJson("/api/letters/{$letter->id}", $this->payload([
            'audio' => UploadedFile::fake()->create('large.mp3', 300, 'audio/mpeg'),
        ]))->assertUnprocessable()->assertJsonValidationErrors('media');
        $this->assertNull($letter->fresh()->audio_path);
        Storage::disk('public')->assertExists('letters/existing.png');

        $this->withToken($token)->putJson("/api/letters/{$letter->id}", $this->payload([
            'image' => UploadedFile::fake()->image('replacement.png')->size(700),
        ]))->assertOk();
        Storage::disk('public')->assertMissing('letters/existing.png');
        Storage::disk('public')->assertExists($letter->fresh()->image_path);
    }

    public function test_media_writes_require_token_ability_and_letter_ownership(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $letter = $other->letters()->create($this->payload(['image_path' => 'letters/private.png']));
        Storage::disk('public')->put('letters/private.png', 'private media');
        $readToken = $user->createToken('read', ['letters:read'])->plainTextToken;

        $this->withToken($readToken)->postJson('/api/letters', $this->payload([
            'image' => UploadedFile::fake()->image('blocked.png'),
        ]))->assertForbidden();

        $ownedLetter = $user->letters()->create($this->payload());
        $this->withToken($readToken)->putJson("/api/letters/{$ownedLetter->id}", $this->payload([
            'image' => UploadedFile::fake()->image('blocked.png'),
        ]))->assertForbidden();

        $writeToken = $user->createToken('write', ['letters:write'])->plainTextToken;
        $this->withToken($writeToken)->putJson("/api/letters/{$letter->id}", $this->payload([
            'image' => UploadedFile::fake()->image('blocked.png'),
            'remove_image' => 1,
        ]))->assertForbidden();

        $this->assertNull($ownedLetter->fresh()->image_path);
        $this->assertSame('letters/private.png', $letter->fresh()->image_path);
        $this->assertSame(['letters/private.png'], Storage::disk('public')->allFiles());
    }
}
