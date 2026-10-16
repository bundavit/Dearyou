<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicMediaUrlTest extends TestCase
{
    use RefreshDatabase;

    private const MEDIA_URL = 'https://media.dearyous.app/storage';

    public function test_optional_media_url_defaults_to_each_environments_own_storage(): void
    {
        $saved = [];
        foreach (['APP_URL', 'PUBLIC_MEDIA_URL'] as $key) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        }

        try {
            foreach ([
                ['https://dearyous.app', null, 'https://dearyous.app/storage'],
                ['https://staging.dearyous.app/', '', 'https://staging.dearyous.app/storage'],
                ['https://dearyous.app', self::MEDIA_URL.'/', self::MEDIA_URL],
            ] as [$appUrl, $mediaUrl, $expected]) {
                $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $appUrl;
                if ($mediaUrl === null) {
                    unset($_ENV['PUBLIC_MEDIA_URL'], $_SERVER['PUBLIC_MEDIA_URL']);
                    putenv('PUBLIC_MEDIA_URL');
                } else {
                    $_ENV['PUBLIC_MEDIA_URL'] = $_SERVER['PUBLIC_MEDIA_URL'] = $mediaUrl;
                }

                $filesystem = require config_path('filesystems.php');

                $this->assertSame($expected, $filesystem['disks']['public']['url']);
                $this->assertSame(storage_path('app/private/media'), $filesystem['disks']['public']['root']);
            }
        } finally {
            foreach ($saved as $key => [$env, $server, $process]) {
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
                putenv($process === false ? $key : $key.'='.$process);
            }
        }
    }

    public function test_account_and_editor_use_authorized_routes_when_a_legacy_media_host_is_configured(): void
    {
        $this->configureMediaHost();
        $user = User::factory()->create(['avatar_path' => 'avatars/creator.png']);
        $letter = $this->letter($user);

        $this->actingAs($user)->get('/account')->assertOk()
            ->assertSee('src="'.route('media.avatar').'"', false);

        $editor = $this->get("/letters/{$letter->id}/edit")->assertOk();
        foreach (['image_path', 'audio_path', 'sender_profile_path', 'recipient_profile_path'] as $column) {
            $editor->assertSee('src="'.$letter->mediaUrl($letter->{$column}).'"', false);
        }
        $editor->assertSee('src="'.$letter->mediaUrl('letters/memories/video.webm').'"', false)
            ->assertDontSee(self::MEDIA_URL.'/assets/', false);

        $this->get("/letters/{$letter->id}")->assertOk()
            ->assertSee('src="'.$letter->mediaUrl('letters/letter.png').'"', false)
            ->assertSee('src="'.$letter->mediaUrl('letters/memories/video.webm').'"', false);
    }

    public function test_public_letter_uses_token_bound_media_routes(): void
    {
        $this->configureMediaHost();
        $letter = $this->letter(User::factory()->create());
        $link = $letter->link;

        $this->get("/l/{$link->token}")->assertOk()
            ->assertSee('src="'.$letter->mediaUrl('letters/letter.png').'"', false)
            ->assertSee('src="'.$letter->mediaUrl('letters/audio/song.mp3').'"', false)
            ->assertSee('data-lightbox-image="'.$letter->mediaUrl('letters/memories/video.webm').'"', false)
            ->assertSee('src="'.$letter->mediaUrl('letters/memories/video.webm').'"', false)
            ->assertDontSee(self::MEDIA_URL.'/assets/', false);
    }

    public function test_response_profiles_use_token_bound_media_routes(): void
    {
        $this->configureMediaHost();
        $letter = $this->letter(User::factory()->create(), 'confession');

        $response = $this->postJson("/l/{$letter->link->token}/response", ['response_value' => 'positive'])
            ->assertOk();
        $this->assertStringContainsString('src="'.$letter->mediaUrl('letters/profiles/sender.png').'"', $response->json('html'));
        $this->assertStringContainsString('src="'.$letter->mediaUrl('letters/profiles/recipient.png').'"', $response->json('html'));
    }

    public function test_keepsake_embeds_local_media_with_an_external_media_url(): void
    {
        $this->configureMediaHost();
        $letter = $this->letter(User::factory()->create());
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        Storage::disk('public')->put($letter->image_path, $png);
        Storage::disk('public')->put('letters/memories/video.webm', 'saved video bytes');

        $this->get("/l/{$letter->link->token}/download?format=html")->assertOk()
            ->assertSee('src="data:image/png;base64,'.base64_encode($png).'"', false)
            ->assertDontSee(self::MEDIA_URL, false);

        $letter->update(['image_path' => 'letters/memories/video.webm']);
        $this->get("/l/{$letter->link->token}/download?format=html")->assertOk()
            ->assertSee('src="data:video/webm;base64,'.base64_encode('saved video bytes').'"', false)
            ->assertDontSee(self::MEDIA_URL, false);

        Storage::disk('public')->assertExists('letters/letter.png');
        Storage::disk('public')->assertExists('letters/memories/video.webm');
    }

    private function configureMediaHost(): void
    {
        config([
            'filesystems.default' => 'local',
            'filesystems.disks.public.url' => self::MEDIA_URL,
        ]);
        Storage::fake('local');
        Storage::fake('public', ['url' => self::MEDIA_URL]);
    }

    private function letter(User $user, string $category = 'anniversary'): Letter
    {
        $letter = $user->letters()->create([
            'category' => $category, 'title' => 'A letter', 'recipient_name' => 'Alex', 'sender_name' => 'Sam', 'body' => 'Hello',
            'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts',
            'status' => 'published', 'allow_response' => true, 'response_mode' => 'buttons_with_message',
            'published_at' => now(), 'expires_at' => now()->addHour(),
            'image_path' => 'letters/letter.png', 'audio_path' => 'letters/audio/song.mp3',
            'sender_profile_path' => 'letters/profiles/sender.png', 'recipient_profile_path' => 'letters/profiles/recipient.png',
        ]);
        $letter->link()->create(['token' => str_repeat('m', 64), 'is_active' => true]);
        $memory = $letter->memories()->create(['title' => 'A memory', 'sort_order' => 0]);
        $memory->images()->create(['image_path' => 'letters/memories/video.webm', 'sort_order' => 0]);

        return $letter;
    }
}
