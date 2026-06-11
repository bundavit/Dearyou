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
            'allow_response' => 1, 'response_mode' => 'buttons_with_message',
        ])->assertRedirect();
        $letter = Letter::first();
        $this->actingAs($user)->post("/admin/letters/{$letter->id}/publish")->assertRedirect();
        $this->assertNotNull($letter->fresh()->link);
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
            ->assertSee('name="response_value"', false)
            ->assertSee('value="positive"', false)
            ->assertSee('value="negative"', false);
        $this->post("/l/{$link->token}/response", ['response_value' => 'positive', 'message' => 'Yes!'])->assertRedirect();
        $this->assertDatabaseHas('responses', ['letter_id' => $letter->id, 'message' => 'Yes!']);
        $letter->update(['status' => 'unpublished']);
        $this->get("/l/{$link->token}")->assertNotFound();
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

        $this->actingAs($user)->patch("/admin/responses/{$response->id}/unread")->assertRedirect();
        $this->assertNull($response->fresh()->read_at);
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
            'memory_image' => UploadedFile::fake()->image('trip.jpg'),
        ])->assertRedirect();

        $memory = $letter->memories()->first();
        Storage::disk('public')->assertExists($memory->image_path);
        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('Moments worth remembering')
            ->assertSee('Our first trip')
            ->assertSee('December 20, 2025');

        $path = $memory->image_path;
        $this->actingAs($user)->delete("/admin/memories/{$memory->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($path);
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
