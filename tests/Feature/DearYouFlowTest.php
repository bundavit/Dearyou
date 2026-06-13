<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DearYouFlowTest extends TestCase
{
    use RefreshDatabase;

    private function letter(User $user, array $overrides = []): Letter
    {
        return $user->letters()->create(array_merge([
            'category' => 'confession', 'title' => 'A letter', 'recipient_name' => 'Alex', 'sender_name' => 'Sam', 'body' => 'Hello',
            'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts',
            'font_style' => 'classic',
            'status' => 'draft', 'allow_response' => true, 'response_mode' => 'buttons_with_message',
        ], $overrides));
    }

    public function test_admin_can_login_create_and_publish_a_letter(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin/dashboard');
        $this->actingAs($user)->post('/admin/letters', [
            'category' => 'confession', 'title' => 'Hi', 'recipient_name' => 'Alex', 'sender_name' => 'Sam', 'body' => 'Hello',
            'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts',
            'font_style' => 'handwritten',
            'allow_response' => 1, 'response_mode' => 'buttons_with_message',
        ])->assertRedirect();
        $letter = Letter::first();
        $this->actingAs($user)->post("/admin/letters/{$letter->id}/publish")->assertRedirect();
        $this->assertNotNull($letter->fresh()->link);
    }

    public function test_recipient_and_sender_names_are_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/letters', [
            'category' => 'custom',
            'title' => 'A nameless letter',
            'recipient_name' => '',
            'sender_name' => '',
            'body' => 'Some words do not need names.',
            'theme' => 'warm',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'hearts',
            'font_style' => 'classic',
            'allow_response' => 1,
            'response_mode' => 'message',
            'positive_button_text' => '',
            'negative_button_text' => '',
            'chapter_heading' => '',
        ])->assertRedirect();

        $letter = Letter::query()->sole();
        $this->assertNull($letter->recipient_name);
        $this->assertNull($letter->sender_name);
        $this->assertSame('Yes', $letter->positive_button_text);
        $this->assertSame('No', $letter->negative_button_text);
        $this->assertSame('A beautiful new chapter begins.', $letter->chapter_heading);

        $letter->update(['status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('n', 64), 'is_active' => true]);

        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('Dear Someone special,')
            ->assertSee('Anonymous');
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_authenticated_admin_visiting_login_is_sent_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_only_valid_published_links_open_and_accept_responses(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, ['status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('a', 64), 'is_active' => true]);
        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('Open Letter')
            ->assertSee('id="close-letter"', false)
            ->assertDontSee('Made especially for you')
            ->assertDontSee('Close and reread')
            ->assertSee('name="response_value"', false)
            ->assertSee('value="positive"', false)
            ->assertSee('value="negative"', false);
        $this->post("/l/{$link->token}/response", ['response_value' => 'positive', 'message' => 'Yes!'])
            ->assertRedirect();
        $this->get("/l/{$link->token}")
            ->assertOk();
        $responsePage = $this->withSession(['response_sent' => true, 'response_value' => 'positive'])
            ->get("/l/{$link->token}");
        $responsePage
            ->assertOk()
            ->assertSee('A beautiful new chapter begins.')
            ->assertSee('id="envelope-stage" class="envelope-stage"  hidden', false)
            ->assertSee('opened-letter-scene')
            ->assertSee('revealed');
        $this->assertDatabaseHas('responses', ['letter_id' => $letter->id, 'message' => 'Yes!']);
        $letter->update(['status' => 'unpublished']);
        $this->get("/l/{$link->token}")->assertNotFound();
    }

    public function test_every_valid_public_link_open_is_counted(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, ['status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('o', 64), 'is_active' => true]);

        $this->get("/l/{$link->token}")->assertOk();
        $firstOpenedAt = $letter->fresh()->opened_at;

        $this->get("/l/{$link->token}")->assertOk();

        $letter->refresh();
        $this->assertSame(2, $letter->open_count);
        $this->assertNotNull($firstOpenedAt);
        $this->assertTrue($letter->opened_at->equalTo($firstOpenedAt));

        $this->actingAs($user)
            ->get("/admin/letters/{$letter->id}/preview")
            ->assertOk();

        $this->assertSame(2, $letter->fresh()->open_count);

        $letter->update(['status' => 'unpublished']);
        $this->get("/l/{$link->token}")->assertNotFound();
        $this->assertSame(2, $letter->fresh()->open_count);
    }

    public function test_admin_pages_show_letter_open_totals(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, [
            'open_count' => 12,
            'opened_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Link opens')
            ->assertSee('12');

        $this->actingAs($user)
            ->get('/admin/letters')
            ->assertOk()
            ->assertSee('12');

        $this->actingAs($user)
            ->get("/admin/letters/{$letter->id}")
            ->assertOk()
            ->assertSee('Link opens')
            ->assertSee('First opened')
            ->assertSee('12');
    }

    public function test_expired_and_regenerated_links_are_protected(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, ['status' => 'published', 'expires_at' => now()->subMinute()]);
        $link = $letter->link()->create(['token' => str_repeat('b', 64), 'is_active' => true]);
        $this->get("/l/{$link->token}")->assertNotFound();
        $letter->update(['expires_at' => null]);
        $this->actingAs($user)->post("/admin/letters/{$letter->id}/regenerate-link")->assertRedirect();
        $this->assertNotEquals(str_repeat('b', 64), $link->fresh()->token);
    }

    public function test_admin_can_view_inbox(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $link = $letter->link()->create(['token' => str_repeat('c', 64)]);
        $letter->responses()->create(['letter_link_id' => $link->id, 'response_value' => 'positive', 'message' => 'Lovely', 'submitted_at' => now()]);
        $this->actingAs($user)->get('/admin/inbox')->assertOk()->assertSee('Lovely');
    }

    public function test_opening_response_marks_it_read_and_it_can_be_marked_unread(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $link = $letter->link()->create(['token' => str_repeat('e', 64)]);
        $response = $letter->responses()->create([
            'letter_link_id' => $link->id,
            'response_value' => 'positive',
            'message' => 'A private answer',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)->get("/admin/responses/{$response->id}")
            ->assertOk()
            ->assertSee('A private answer');
        $this->assertNotNull($response->fresh()->read_at);

        $this->actingAs($user)
            ->patch("/admin/responses/{$response->id}/unread")
            ->assertRedirect('/admin/inbox');
        $this->assertNull($response->fresh()->read_at);
    }

    public function test_create_letter_shows_the_accepted_confession_preview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/letters/create')
            ->assertOk()
            ->assertSee('Positive response preview')
            ->assertSee('A beautiful new chapter begins.')
            ->assertSee('data-chapter-sender-image', false)
            ->assertSee('data-chapter-recipient-image', false)
            ->assertSee('class="editor-section"', false);
    }

    public function test_chapter_heading_music_and_async_response_are_supported(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user, ['status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('k', 64), 'is_active' => true]);
        $payload = [
            'category' => 'confession',
            'title' => 'A letter',
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => 'Hello',
            'theme' => 'warm',
            'font_style' => 'classic',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'hearts',
            'allow_response' => 1,
            'response_mode' => 'buttons',
            'chapter_heading' => 'Our story starts here.',
            'audio' => UploadedFile::fake()->create('song.mp3', 13 * 1024, 'application/octet-stream'),
        ];

        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->audio_path);

        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('Play music')
            ->assertSee('data-letter-audio loop preload="metadata"', false)
            ->assertDontSee('data-letter-audio autoplay', false)
            ->assertSee('data-async-response', false);

        $asyncResponse = $this->postJson("/l/{$link->token}/response", ['response_value' => 'positive'])
            ->assertOk();
        $this->assertStringContainsString('Our story starts here.', $asyncResponse->json('html'));

        $audioPath = $letter->audio_path;
        unset($payload['audio']);
        $payload['remove_audio'] = 1;
        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload)->assertRedirect();
        Storage::disk('public')->assertMissing($audioPath);
    }

    public function test_deleting_response_returns_to_full_inbox(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $link = $letter->link()->create(['token' => str_repeat('h', 64)]);
        $response = $letter->responses()->create([
            'letter_link_id' => $link->id,
            'response_value' => 'positive',
            'message' => 'Delete this response',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->from("/admin/responses/{$response->id}")
            ->delete("/admin/responses/{$response->id}")
            ->assertRedirect('/admin/inbox')
            ->assertSessionHas('success', 'Response deleted.');

        $this->assertDatabaseMissing('responses', ['id' => $response->id]);
    }

    public function test_inbox_filters_and_bulk_actions_only_affect_owned_responses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $letter = $this->letter($user, ['title' => 'My letter']);
        $otherLetter = $this->letter($other, ['title' => 'Other letter']);
        $link = $letter->link()->create(['token' => str_repeat('f', 64)]);
        $otherLink = $otherLetter->link()->create(['token' => str_repeat('g', 64)]);
        $owned = $letter->responses()->create([
            'letter_link_id' => $link->id,
            'response_value' => 'positive',
            'message' => 'Owned response',
            'submitted_at' => now(),
        ]);
        $foreign = $otherLetter->responses()->create([
            'letter_link_id' => $otherLink->id,
            'response_value' => 'negative',
            'message' => 'Foreign response',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)->get('/admin/inbox?status=unread')
            ->assertOk()
            ->assertSee('Owned response')
            ->assertDontSee('Foreign response');

        $this->actingAs($user)->post('/admin/inbox/bulk', [
            'response_ids' => [$owned->id, $foreign->id],
            'action' => 'read',
        ])->assertRedirect();

        $this->assertNotNull($owned->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_admin_can_upload_replace_and_remove_a_letter_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user);

        $payload = [
            'category' => 'confession',
            'title' => 'A letter',
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => 'Hello',
            'theme' => 'warm',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'hearts',
            'allow_response' => 1,
            'response_mode' => 'buttons_with_message',
            'image_alt' => 'Our favorite day',
        ];

        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload + [
            'image' => UploadedFile::fake()->image('memory.jpg'),
        ])->assertRedirect();

        $path = $letter->fresh()->image_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload + [
            'remove_image' => 1,
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($letter->fresh()->image_path);
    }

    public function test_admin_can_use_gifs_mp4s_and_telegram_webms_in_letters_and_memories(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==').str_repeat("\0", 6 * 1024 * 1024);
        $payload = [
            'category' => 'confession',
            'title' => 'A playful letter',
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => 'Hello',
            'theme' => 'warm',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'hearts',
            'allow_response' => 1,
            'response_mode' => 'buttons_with_message',
            'image' => UploadedFile::fake()->createWithContent('playful.gif', $gif),
        ];

        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload)->assertRedirect();
        Storage::disk('public')->assertExists($letter->fresh()->image_path);

        $this->actingAs($user)->post("/admin/letters/{$letter->id}/memories", [
            'title' => 'A funny moment',
            'memory_images' => [
                UploadedFile::fake()->createWithContent('memory.gif', $gif),
            ],
        ])->assertRedirect();

        $memory = $letter->memories()->first();
        $this->assertCount(1, $memory->images);
        Storage::disk('public')->assertExists($memory->images->first()->image_path);

        $payload['category'] = 'anniversary';
        $payload['image'] = UploadedFile::fake()->create('playful.mp4', 100, 'video/mp4');
        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->image_path);
        $this->assertStringEndsWith('.mp4', $letter->image_path);

        $this->actingAs($user)->post("/admin/letters/{$letter->id}/memories", [
            'title' => 'A video memory',
            'memory_images' => [
                UploadedFile::fake()->create('memory.mp4', 100, 'video/mp4'),
            ],
        ])->assertRedirect();

        $videoMemory = $letter->memories()->where('title', 'A video memory')->firstOrFail();
        Storage::disk('public')->assertExists($videoMemory->images->first()->image_path);
        $this->assertStringEndsWith('.mp4', $videoMemory->images->first()->image_path);

        $payload['image'] = UploadedFile::fake()->create('telegram-animation.webm', 100, 'video/webm');
        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->image_path);
        $this->assertStringEndsWith('.webm', $letter->image_path);

        $this->actingAs($user)->post("/admin/letters/{$letter->id}/memories", [
            'title' => 'A Telegram animation',
            'memory_images' => [
                UploadedFile::fake()->create('telegram-memory.webm', 100, 'video/webm'),
            ],
        ])->assertRedirect();

        $telegramMemory = $letter->memories()->where('title', 'A Telegram animation')->firstOrFail();
        Storage::disk('public')->assertExists($telegramMemory->images->first()->image_path);
        $this->assertStringEndsWith('.webm', $telegramMemory->images->first()->image_path);

        $letter->update(['status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('v', 64), 'is_active' => true]);
        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('<video src="'.Storage::url($letter->image_path).'" autoplay muted loop playsinline', false)
            ->assertSee('data-lightbox-type="video"', false);
    }

    public function test_category_specific_recipient_copy_and_decorations_are_rendered(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, [
            'category' => 'birthday',
            'theme' => 'celebration',
            'decoration_type' => 'balloons',
            'status' => 'published',
        ]);
        $link = $letter->link()->create(['token' => str_repeat('d', 64), 'is_active' => true]);

        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('A BIRTHDAY SURPRISE FOR')
            ->assertSee('category-birthday', false)
            ->assertSee('decoration-balloons', false);
    }

    public function test_positive_confession_response_shows_profiles_and_started_date(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user, ['status' => 'published']);
        $senderPath = UploadedFile::fake()->image('sender.jpg')->store('letters/profiles', 'public');
        $recipientPath = UploadedFile::fake()->image('recipient.jpg')->store('letters/profiles', 'public');
        $letter->update([
            'relationship_started_at' => '2026-06-10',
            'sender_profile_path' => $senderPath,
            'recipient_profile_path' => $recipientPath,
        ]);
        $link = $letter->link()->create(['token' => str_repeat('h', 64), 'is_active' => true]);

        $this->from("/l/{$link->token}")
            ->followingRedirects()
            ->post("/l/{$link->token}/response", ['response_value' => 'positive'])
            ->assertOk()
            ->assertSee('A beautiful new chapter begins.')
            ->assertSee('Started from June 10, 2026')
            ->assertSee(Storage::url($senderPath), false)
            ->assertSee(Storage::url($recipientPath), false);
    }

    public function test_confession_profiles_can_be_uploaded_and_are_deleted_with_letter(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $payload = [
            'category' => 'confession',
            'title' => 'A letter',
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => 'Hello',
            'theme' => 'romantic',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'hearts',
            'allow_response' => 1,
            'response_mode' => 'buttons_with_message',
            'relationship_started_at' => '2026-06-10',
            'sender_profile' => UploadedFile::fake()->image('sender.jpg'),
            'recipient_profile' => UploadedFile::fake()->image('recipient.jpg'),
        ];

        $this->actingAs($user)->put("/admin/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->sender_profile_path);
        Storage::disk('public')->assertExists($letter->recipient_profile_path);

        $senderPath = $letter->sender_profile_path;
        $recipientPath = $letter->recipient_profile_path;
        $this->actingAs($user)->delete("/admin/letters/{$letter->id}")->assertRedirect('/admin/letters');

        Storage::disk('public')->assertMissing($senderPath);
        Storage::disk('public')->assertMissing($recipientPath);
    }

    public function test_admin_can_manage_anniversary_memories_and_recipient_sees_timeline(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user, ['category' => 'anniversary', 'status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('i', 64), 'is_active' => true]);

        $this->actingAs($user)->post("/admin/letters/{$letter->id}/memories", [
            'title' => 'Our first trip',
            'memory_date' => '2025-12-20',
            'caption' => 'A day we still talk about.',
            'memory_images' => [
                UploadedFile::fake()->image('trip-one.jpg'),
                UploadedFile::fake()->image('trip-two.jpg'),
            ],
        ])->assertRedirect();

        $memory = $letter->memories()->first();
        $this->assertCount(2, $memory->images);
        $memory->images->each(fn ($image) => Storage::disk('public')->assertExists($image->image_path));
        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('Moments worth remembering')
            ->assertSee('Our first trip')
            ->assertSee('December 20, 2025')
            ->assertSee('picture 1')
            ->assertSee('picture 2')
            ->assertSee('data-memory-lightbox', false)
            ->assertSee('data-lightbox-image', false);

        $removedImage = $memory->images->first();
        $this->actingAs($user)->put("/admin/memories/{$memory->id}", [
            'title' => 'Our first trip',
            'memory_date' => '2025-12-20',
            'caption' => 'A day we still talk about.',
            'remove_memory_images' => [$removedImage->id],
            'memory_images' => [UploadedFile::fake()->image('trip-three.jpg')],
        ])->assertRedirect();

        Storage::disk('public')->assertMissing($removedImage->image_path);
        $memory->refresh();
        $this->assertCount(2, $memory->images);

        $secondMemory = $letter->memories()->create(['title' => 'Another day', 'sort_order' => 1]);
        $this->actingAs($user)->patchJson("/admin/letters/{$letter->id}/memories/reorder", [
            'order' => [$secondMemory->id, $memory->id],
        ])->assertNoContent();
        $this->assertSame(0, $secondMemory->fresh()->sort_order);
        $this->assertSame(1, $memory->fresh()->sort_order);

        $images = $memory->images()->get();
        $this->actingAs($user)->patchJson("/admin/memories/{$memory->id}/images/reorder", [
            'order' => $images->pluck('id')->reverse()->values()->all(),
        ])->assertNoContent();
        $this->assertSame($images->last()->id, $memory->images()->first()->id);

        $remainingPaths = $memory->images->pluck('image_path');
        $this->actingAs($user)->delete("/admin/memories/{$memory->id}")->assertRedirect();
        $remainingPaths->each(fn ($path) => Storage::disk('public')->assertMissing($path));
    }

    public function test_admin_can_search_and_filter_letters(): void
    {
        $user = User::factory()->create();
        $this->letter($user, ['title' => 'Birthday for Taylor', 'category' => 'birthday', 'status' => 'published']);
        $this->letter($user, ['title' => 'Private apology', 'category' => 'apology', 'status' => 'draft']);

        $this->actingAs($user)->get('/admin/letters?search=Taylor&status=published&category=birthday')
            ->assertOk()
            ->assertSee('Birthday for Taylor')
            ->assertDontSee('Private apology');
    }

    public function test_admin_can_choose_a_font_style_for_the_recipient_letter(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, [
            'font_style' => 'handwritten',
            'status' => 'published',
        ]);
        $link = $letter->link()->create(['token' => str_repeat('j', 64), 'is_active' => true]);

        $this->actingAs($user)->get("/admin/letters/{$letter->id}/edit")
            ->assertOk()
            ->assertSee('Handwritten')
            ->assertSee('data-font-select', false)
            ->assertDontSee('data-font-preview', false);

        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('font-handwritten', false)
            ->assertSee('--letter-font:', false);
    }

    public function test_admin_can_choose_an_envelope_style_for_the_recipient_letter(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, ['status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('s', 64), 'is_active' => true]);
        $payload = [
            'category' => 'birthday',
            'title' => 'A wrapped surprise',
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => 'Open me',
            'theme' => 'celebration',
            'font_style' => 'friendly',
            'envelope_style' => 'gift',
            'seal_style' => 'diamond',
            'primary_color' => '#7b68c7',
            'secondary_color' => '#fff6cf',
            'decoration_type' => 'balloons',
            'allow_response' => 1,
            'response_mode' => 'message',
        ];

        $this->actingAs($user)
            ->put("/admin/letters/{$letter->id}", $payload)
            ->assertRedirect();

        $this->assertSame('gift', $letter->fresh()->envelope_style);
        $this->assertSame('diamond', $letter->fresh()->seal_style);
        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('envelope-style-gift', false)
            ->assertSee('seal-style-diamond', false)
            ->assertSee('bi-gem', false);

        $this->actingAs($user)
            ->put("/admin/letters/{$letter->id}", array_merge($payload, ['envelope_style' => 'unknown']))
            ->assertSessionHasErrors('envelope_style');

        $this->actingAs($user)
            ->put("/admin/letters/{$letter->id}", array_merge($payload, ['seal_style' => 'unknown']))
            ->assertSessionHasErrors('seal_style');
    }

    public function test_admin_can_view_an_owned_letter_but_not_another_users_letter(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $letter = $this->letter($user, ['title' => 'A private detail page']);
        $foreignLetter = $this->letter($other, ['title' => 'Not yours']);

        $this->actingAs($user)->get("/admin/letters/{$letter->id}")
            ->assertOk()
            ->assertSee('A private detail page')
            ->assertSee('Edit letter');

        $this->actingAs($user)->get("/admin/letters/{$foreignLetter->id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_an_owned_letter_from_letter_pages(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $letter = $this->letter($user, ['image_path' => 'letters/delete-me.jpg']);
        Storage::disk('public')->put('letters/delete-me.jpg', 'image');

        $this->actingAs($user)
            ->get('/admin/letters')
            ->assertOk()
            ->assertSee(route('admin.letters.destroy', $letter), false)
            ->assertSee('Delete');

        $this->actingAs($user)
            ->get("/admin/letters/{$letter->id}")
            ->assertOk()
            ->assertSee(route('admin.letters.destroy', $letter), false)
            ->assertSee('Delete letter');

        $this->actingAs($user)
            ->delete("/admin/letters/{$letter->id}")
            ->assertRedirect('/admin/letters')
            ->assertSessionHas('success', 'Letter deleted.');

        $this->assertSoftDeleted($letter);
        Storage::disk('public')->assertMissing('letters/delete-me.jpg');
    }

    public function test_admin_can_update_profile_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);

        $this->actingAs($user)->put('/admin/account/profile', [
            'name' => 'New Admin',
            'email' => 'new@example.com',
            'current_password' => 'OldPassword1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Admin', 'email' => 'new@example.com']);
    }

    public function test_admin_can_change_password_and_api_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);
        $user->createToken('test');

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'OldPassword1',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('NewPassword2', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_admin_cannot_change_password_with_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);

        $this->actingAs($user)->put('/admin/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword1', $user->fresh()->password));
    }
}
