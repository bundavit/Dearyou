<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'custom',
            'title' => 'API letter',
            'recipient_name' => 'Alex',
            'sender_name' => 'Sam',
            'body' => 'Hello from the API.',
            'theme' => 'warm',
            'primary_color' => '#d85b78',
            'secondary_color' => '#fff1e8',
            'decoration_type' => 'sparkles',
            'allow_response' => true,
            'response_mode' => 'message',
        ], $overrides);
    }

    public function test_api_requires_a_token(): void
    {
        $this->getJson('/api/letters')->assertUnauthorized();
    }

    public function test_read_only_token_can_list_but_cannot_create(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('read', ['letters:read', 'responses:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/letters')->assertOk();
        $this->withToken($token)->postJson('/api/letters', $this->payload())->assertForbidden();
    }

    public function test_write_token_can_create_update_publish_and_delete_owned_letter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('write', ['letters:read', 'letters:write', 'responses:read'])->plainTextToken;

        $created = $this->withToken($token)->postJson('/api/letters', $this->payload())
            ->assertCreated()
            ->json();

        $this->withToken($token)->putJson("/api/letters/{$created['id']}", $this->payload(['title' => 'Updated']))
            ->assertOk()
            ->assertJsonPath('title', 'Updated');
        $this->withToken($token)->postJson("/api/letters/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('status', 'published');
        $this->withToken($token)->deleteJson("/api/letters/{$created['id']}")->assertNoContent();
    }

    public function test_api_cannot_access_another_admins_letter_or_response(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('write', ['letters:read', 'letters:write', 'responses:read'])->plainTextToken;
        $letter = $other->letters()->create($this->payload());
        $link = $letter->link()->create(['token' => str_repeat('z', 64)]);
        $letter->responses()->create([
            'letter_link_id' => $link->id,
            'response_value' => 'message',
            'message' => 'Private',
            'submitted_at' => now(),
        ]);

        $this->withToken($token)->getJson("/api/letters/{$letter->id}")->assertForbidden();
        $this->withToken($token)->getJson('/api/responses')->assertOk()->assertJsonMissing(['message' => 'Private']);
    }

    public function test_admin_can_create_and_revoke_api_token_from_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/account/tokens', [
            'token_name' => 'Postman',
            'access' => 'write',
        ])->assertRedirect()->assertSessionHas('new_api_token');

        $token = $user->tokens()->first();
        $this->assertContains('letters:write', $token->abilities);
        $this->actingAs($user)->delete("/admin/account/tokens/{$token->id}")->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }
}
