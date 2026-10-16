<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginDestinationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('destinations')]
    public function test_login_only_preserves_an_allowed_same_origin_destination(string $role, string $intended, string $expected): void
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'StrongPass1']);

        $this->withSession(['url.intended' => str_replace('{origin}', url('/'), $intended)])
            ->post('/login', ['email' => $user->email, 'password' => 'StrongPass1'])
            ->assertRedirect($expected)->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($user);
    }

    public static function destinations(): array
    {
        return [
            'creator wrong role' => ['user', '{origin}/admin/platform', '/letters'],
            'admin wrong role' => ['admin', '{origin}/letters', '/admin/platform'],
            'creator editor' => ['user', '{origin}/letters/create', '/letters/create'],
            'admin settings' => ['admin', '{origin}/admin/settings', '/admin/settings'],
            'external origin' => ['user', 'https://outside.invalid/letters', '/letters'],
            'protocol relative' => ['admin', '//outside.invalid/admin/platform', '/admin/platform'],
            'guest route' => ['user', '{origin}/register', '/letters'],
            'unknown route' => ['user', '{origin}/not-a-route', '/letters'],
        ];
    }
}
