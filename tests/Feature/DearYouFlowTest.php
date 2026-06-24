<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Letter;
use App\Models\ModerationAudit;
use App\Models\Response;
use App\Models\SiteMetric;
use App\Models\SiteMetricEvent;
use App\Models\User;
use App\Notifications\FeedbackReceived;
use App\Notifications\PasswordResetCode;
use App\Notifications\StorageCleanupCompleted;
use App\Notifications\StorageLimitWarning;
use App\Notifications\VerifyEmail;
use App\Support\CreatorStorage;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    private function letterPayload(array $overrides = []): array
    {
        return array_merge([
            'category' => 'custom',
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
            'response_mode' => 'message',
            'expiry_minutes' => 60,
        ], $overrides);
    }

    public function test_creator_can_login_create_and_publish_a_letter(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/letters');
        $this->actingAs($user)->post('/letters', [
            'category' => 'confession', 'title' => 'Hi', 'recipient_name' => 'Alex', 'sender_name' => 'Sam', 'body' => 'Hello',
            'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts',
            'font_style' => 'handwritten',
            'allow_response' => 1, 'response_mode' => 'buttons_with_message',
        ])->assertRedirect();
        $letter = Letter::first();
        $this->actingAs($user)->post("/letters/{$letter->id}/publish")->assertRedirect();
        $this->assertNotNull($letter->fresh()->link);
    }

    public function test_new_user_can_register_and_reach_my_dearyou_letters(): void
    {
        Notification::fake();

        $this->get('/register')
            ->assertOk()
            ->assertSee('Create account');

        $this->post('/register', [
            'name' => 'New Writer',
            'email' => 'writer@example.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ])->assertRedirect('/verify-email');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'writer@example.com',
            'role' => User::ROLE_USER,
        ]);

        $user = User::query()->where('email', 'writer@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $code = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->get('/letters')->assertRedirect('/verify-email');

        $this->post('/verify-email', ['code' => $code])->assertRedirect('/letters');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/letters')->assertOk();
    }

    public function test_user_can_resend_the_email_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/email/verification-notification')
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-code-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_email_verification_code_expires_and_limits_incorrect_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)
                ->post('/verify-email', ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->assertDatabaseHas('email_verification_codes', [
            'user_id' => $user->id,
            'attempts' => 5,
        ]);

        $this->actingAs($user)
            ->post('/verify-email', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $user->id]);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_active_user_can_request_and_complete_a_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'writer@example.com',
            'password' => 'OldPassword1',
        ]);
        $user->createToken('old-device');

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password/code')
            ->assertSessionHas('status');
        $code = null;
        Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $notification) use (&$code) {
            $code = $notification->code;

            return true;
        });

        $this->post('/forgot-password/code', ['code' => $code])
            ->assertRedirect('/reset-password');

        $this->get('/reset-password')
            ->assertOk()
            ->assertSee($user->email);

        $this->post('/reset-password', [
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertRedirect('/login');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword2', $user->password));
        $this->assertCount(0, $user->tokens);
    }

    public function test_password_reset_does_not_reveal_or_restore_disabled_accounts(): void
    {
        Notification::fake();
        $disabled = User::factory()->create([
            'email' => 'disabled@example.com',
            'disabled_at' => now(),
        ]);

        $this->post('/forgot-password', ['email' => 'missing@example.com'])
            ->assertRedirect('/forgot-password/code')
            ->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => $disabled->email])
            ->assertRedirect('/forgot-password/code')
            ->assertSessionHas('status');

        Notification::assertNothingSent();

        $this->withSession([
            'password_reset_authorized' => [
                'email' => $disabled->email,
                'expires_at' => now()->addMinutes(10)->timestamp,
            ],
        ]);
        $this->post('/reset-password', [
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');
    }

    public function test_password_reset_code_expires_and_limits_incorrect_attempts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'writer@example.com']);

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password/code');

        foreach (range(1, 5) as $attempt) {
            $this->post('/forgot-password/code', ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->assertDatabaseHas('password_reset_codes', [
            'email' => $user->email,
            'attempts' => 5,
        ]);

        $this->post('/forgot-password/code', ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('password_reset_codes', ['email' => $user->email]);
    }

    public function test_admin_and_user_accounts_receive_the_correct_area(): void
    {
        $admin = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);
        $user = User::factory()->create([
            'email' => 'writer@example.com',
            'password' => 'password',
        ]);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect('/admin/platform');
        $this->get('/admin/platform')->assertOk()->assertSee('DearYou at a glance');

        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/letters');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_login_is_admin_only_and_public_registration_cannot_create_admins(): void
    {
        Notification::fake();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Admin sign in')
            ->assertSee('Authorized DearYou administrators only.')
            ->assertDontSee('Create an account')
            ->assertDontSee(route('register'), false);

        $this->post('/register', [
            'name' => 'Public Registrant',
            'email' => 'public@example.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
            'role' => User::ROLE_ADMIN,
        ])->assertRedirect('/verify-email');

        $this->assertDatabaseHas('users', [
            'email' => 'public@example.com',
            'role' => User::ROLE_USER,
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'public@example.com',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_admin_routes_can_be_hidden_behind_an_ip_allowlist(): void
    {
        config(['dearyou.admin_allowed_ips' => ['203.0.113.10', '198.51.100.0/24']]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->get('/admin/login')
            ->assertNotFound();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->actingAs($admin)
            ->get('/admin/platform')
            ->assertNotFound();

        auth()->logout();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/admin/login')
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])
            ->actingAs($admin)
            ->get('/admin/platform')
            ->assertOk();
    }

    public function test_creator_and_admin_workspaces_are_separated(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/letters');

        $this->actingAs($user)
            ->get('/letters')
            ->assertOk()
            ->assertSee('My DearYou')
            ->assertSee('My Letters')
            ->assertDontSee('Platform');

        $this->actingAs($user)
            ->get('/admin/letters')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertRedirect('/admin/platform');

        $this->actingAs($admin)
            ->get('/admin/letters')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Platform')
            ->assertDontSee('My DearYou');

        $this->actingAs($admin)
            ->get('/letters')
            ->assertForbidden();
    }

    public function test_home_navigation_adapts_for_users_and_platform_admins(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Private letters, personal pages, thoughtful replies')
            ->assertSee('Create a free account')
            ->assertSee('How DearYou works')
            ->assertSee('No recipient signup needed')
            ->assertSee('What happens when the link expires?')
            ->assertSee(route('register'), false)
            ->assertDontSee('Open dashboard');

        $user = User::factory()->create();
        $letter = $this->letter($user);
        $link = $letter->link()->create(['token' => str_repeat('u', 64)]);
        $letter->responses()->create([
            'letter_link_id' => $link->id,
            'response_value' => 'positive',
            'submitted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('My Letters')
            ->assertSee('Inbox')
            ->assertSee('1 unread responses')
            ->assertSee('Write a letter')
            ->assertSee('View my letters')
            ->assertSeeText('Profile & settings')
            ->assertDontSee('Admin dashboard');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/')
            ->assertOk()
            ->assertSee('Admin dashboard');
    }

    public function test_homepage_visits_are_counted_and_visible_to_platform_admins(): void
    {
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->assertDatabaseHas('site_metrics', [
            'key' => SiteMetric::HOMEPAGE_VIEWS,
            'value' => 2,
        ]);
        $this->assertSame(2, SiteMetricEvent::where('key', SiteMetric::HOMEPAGE_VIEWS)->count());

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/platform')
            ->assertOk()
            ->assertSee('Homepage visits')
            ->assertSee('Homepage visits over time')
            ->assertSee('Last 7 days')
            ->assertSee('Last 4 weeks')
            ->assertSee('2');
    }

    public function test_platform_admin_can_view_deployment_health(): void
    {
        config([
            'app.url' => 'https://dearyous.app',
            'mail.default' => 'resend',
            'mail.from.address' => 'hello@dearyous.app',
            'queue.default' => 'database',
            'services.resend.key' => 'test-resend-key',
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/health')
            ->assertOk()
            ->assertSee('Email and worker health')
            ->assertSee('APP_URL')
            ->assertSee('Resend API key')
            ->assertSee('Queue configuration')
            ->assertSee('dearyou:check-production');
    }

    public function test_platform_admin_can_search_view_and_manage_user_accounts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user = User::factory()->create([
            'name' => 'Letter Writer',
            'email' => 'writer@example.com',
        ]);
        $this->letter($user, ['title' => 'Private title', 'open_count' => 4]);

        $this->actingAs($admin)
            ->get('/admin/users?search=writer@example.com')
            ->assertOk()
            ->assertSee('Letter Writer')
            ->assertSee('1')
            ->assertSee('4');

        $this->actingAs($admin)
            ->get("/admin/users/{$user->id}")
            ->assertOk()
            ->assertSee('Private title')
            ->assertDontSee('Hello');

        $this->actingAs($admin)
            ->patch("/admin/users/{$user->id}/role", ['role' => User::ROLE_ADMIN])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(User::ROLE_ADMIN, $user->fresh()->role);

        $this->actingAs($admin)
            ->patch("/admin/users/{$user->id}/status", ['status' => 'disabled'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertNotNull($user->fresh()->disabled_at);

        $this->actingAs($admin)
            ->patch("/admin/users/{$user->id}/status", ['status' => 'active'])
            ->assertRedirect();
        $this->assertNull($user->fresh()->disabled_at);
    }

    public function test_platform_admin_cannot_change_or_disable_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/role", ['role' => User::ROLE_USER])
            ->assertStatus(422);
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status", ['status' => 'disabled'])
            ->assertStatus(422);
        $this->assertNull($admin->fresh()->disabled_at);
    }

    public function test_disabled_accounts_cannot_login_or_use_existing_sessions_and_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.com',
            'password' => 'StrongPass1',
            'disabled_at' => now(),
        ]);
        $token = $user->createToken('existing')->plainTextToken;

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPass1',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)
            ->get('/letters')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->withToken($token)
            ->getJson('/api/letters')
            ->assertForbidden();
    }

    public function test_recipient_and_sender_names_are_optional(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/letters', [
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

    public function test_authenticated_creator_visiting_admin_login_returns_home(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/login')
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_admin_visiting_admin_login_returns_to_platform(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/login')
            ->assertRedirect('/admin/platform');
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

    public function test_published_letter_can_accept_reaction_responses(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, [
            'status' => 'published',
            'allow_response' => true,
            'response_mode' => 'reactions',
        ]);
        $link = $letter->link()->create(['token' => str_repeat('r', 64), 'is_active' => true]);

        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('value="happy"', false)
            ->assertSee('Surprised');

        $this->post("/l/{$link->token}/response", ['response_value' => 'thankful', 'message' => 'This made my day'])
            ->assertRedirect();

        $this->assertDatabaseHas('responses', [
            'letter_id' => $letter->id,
            'response_value' => 'thankful',
            'message' => 'This made my day',
        ]);
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
            ->get("/letters/{$letter->id}/preview")
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
            ->get('/letters')
            ->assertOk()
            ->assertSee('12');

        $this->actingAs($user)
            ->get("/letters/{$letter->id}")
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
        $this->actingAs($user)->post("/letters/{$letter->id}/regenerate-link")->assertRedirect();
        $this->assertNotEquals(str_repeat('b', 64), $link->fresh()->token);
        $this->assertTrue($letter->fresh()->expires_at->isFuture());
        $this->assertTrue($link->fresh()->expires_at->equalTo($letter->fresh()->expires_at));
    }

    public function test_creator_can_choose_link_duration_and_republishing_resets_the_link(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, ['expiry_minutes' => 15]);

        $this->actingAs($user)
            ->get("/letters/{$letter->id}/edit")
            ->assertOk()
            ->assertSee('15 minutes')
            ->assertSee('30 minutes')
            ->assertSee('1 hour')
            ->assertSee('2 hours');

        $beforePublish = now();
        $this->actingAs($user)->post("/letters/{$letter->id}/publish")->assertRedirect();

        $letter->refresh();
        $firstToken = $letter->link->token;
        $this->assertSame('published', $letter->status);
        $this->assertTrue($letter->expires_at->between($beforePublish->addMinutes(14), now()->addMinutes(16)));
        $this->assertTrue($letter->link->expires_at->equalTo($letter->expires_at));

        $letter->update(['expiry_minutes' => 120]);
        $this->actingAs($user)->post("/letters/{$letter->id}/publish")->assertRedirect();

        $letter->refresh();
        $this->assertNotSame($firstToken, $letter->link->token);
        $this->assertTrue($letter->expires_at->between(now()->addMinutes(119), now()->addMinutes(121)));
    }

    public function test_unpublishing_immediately_disables_the_private_link(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user, ['expiry_minutes' => 60]);

        $this->actingAs($user)->post("/letters/{$letter->id}/publish")->assertRedirect();
        $token = $letter->fresh()->link->token;
        $this->get("/l/{$token}")->assertOk();

        $this->actingAs($user)->post("/letters/{$letter->id}/unpublish")->assertRedirect();

        $letter->refresh();
        $this->assertSame('unpublished', $letter->status);
        $this->assertFalse($letter->link->is_active);
        $this->get("/l/{$token}")->assertNotFound();
    }

    public function test_storage_usage_counts_all_creator_media_sources(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user);

        Storage::disk('public')->put('letters/letter.jpg', str_repeat('a', 100));
        Storage::disk('public')->put('letters/audio/song.mp3', str_repeat('b', 200));
        Storage::disk('public')->put('letters/profiles/sender.jpg', str_repeat('c', 300));
        Storage::disk('public')->put('letters/profiles/recipient.jpg', str_repeat('d', 400));
        $letter->update([
            'image_path' => 'letters/letter.jpg',
            'audio_path' => 'letters/audio/song.mp3',
            'sender_profile_path' => 'letters/profiles/sender.jpg',
            'recipient_profile_path' => 'letters/profiles/recipient.jpg',
        ]);

        $memory = $letter->memories()->create([
            'title' => 'Memory',
            'sort_order' => 0,
            'image_path' => 'letters/memories/legacy.jpg',
        ]);
        Storage::disk('public')->put('letters/memories/legacy.jpg', str_repeat('e', 500));
        Storage::disk('public')->put('letters/memories/gallery.jpg', str_repeat('f', 600));
        $memory->images()->create(['image_path' => 'letters/memories/gallery.jpg', 'sort_order' => 0]);

        $usage = app(CreatorStorage::class)->usage($user);

        $this->assertSame(2100, $usage['used_bytes']);
        $this->actingAs($user)
            ->get('/letters')
            ->assertOk()
            ->assertSee('Media storage')
            ->assertSee('2.1 KB');
    }

    public function test_letter_uploads_cannot_exceed_storage_allowance_but_replacements_free_space(): void
    {
        Storage::fake('public');
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $existingPath = 'letters/existing.jpg';
        Storage::disk('public')->put($existingPath, str_repeat('x', 800 * 1024));
        $letter->update(['image_path' => $existingPath]);

        $this->actingAs($user)
            ->put("/letters/{$letter->id}", $this->letterPayload([
                'audio' => UploadedFile::fake()->create('too-large.mp3', 300, 'audio/mpeg'),
            ]))
            ->assertSessionHasErrors('media');

        $this->assertNull($letter->fresh()->audio_path);

        $this->actingAs($user)
            ->put("/letters/{$letter->id}", $this->letterPayload([
                'image' => UploadedFile::fake()->create('replacement.jpg', 700, 'image/jpeg'),
            ]))
            ->assertSessionDoesntHaveErrors();

        $letter->refresh();
        $this->assertNotSame($existingPath, $letter->image_path);
        Storage::disk('public')->assertMissing($existingPath);
        Storage::disk('public')->assertExists($letter->image_path);
    }

    public function test_memory_uploads_cannot_exceed_storage_allowance(): void
    {
        Storage::fake('public');
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create();
        $letter = $this->letter($user);
        Storage::disk('public')->put('letters/existing.jpg', str_repeat('x', 900 * 1024));
        $letter->update(['image_path' => 'letters/existing.jpg']);

        $this->actingAs($user)
            ->post("/letters/{$letter->id}/memories", [
                'title' => 'Too large',
                'memory_images' => [
                    UploadedFile::fake()->create('memory.jpg', 200, 'image/jpeg'),
                ],
            ])
            ->assertSessionHasErrors('media');

        $this->assertDatabaseMissing('letter_memories', ['letter_id' => $letter->id, 'title' => 'Too large']);
    }

    public function test_over_limit_creator_is_warned_once_and_given_a_grace_period(): void
    {
        Storage::fake('public');
        Notification::fake();
        config([
            'dearyou.storage_limit_mb' => 1,
            'dearyou.storage_cleanup_grace_days' => 7,
        ]);
        $user = User::factory()->create();
        $letter = $this->letter($user);
        Storage::disk('public')->put('letters/over-limit.jpg', str_repeat('x', 1100 * 1024));
        $letter->update(['image_path' => 'letters/over-limit.jpg']);

        $this->artisan('dearyou:process-storage')->assertSuccessful();

        $user->refresh();
        $this->assertNotNull($user->storage_warning_at);
        $this->assertTrue($user->storage_cleanup_due_at->between(now()->addDays(6), now()->addDays(8)));
        Notification::assertSentToTimes($user, StorageLimitWarning::class, 1);

        $this->artisan('dearyou:process-storage')->assertSuccessful();
        Notification::assertSentToTimes($user, StorageLimitWarning::class, 1);
        Storage::disk('public')->assertExists('letters/over-limit.jpg');

        $this->actingAs($user)
            ->get('/letters')
            ->assertOk()
            ->assertSee('Media storage needs attention')
            ->assertSee($user->storage_cleanup_due_at->format('F j, Y g:i A'));
    }

    public function test_cleanup_removes_only_oldest_expired_media_and_preserves_content(): void
    {
        Storage::fake('public');
        Notification::fake();
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create([
            'storage_warning_at' => now()->subDays(8),
            'storage_cleanup_due_at' => now()->subDay(),
        ]);

        $active = $this->letter($user, [
            'title' => 'Active letter',
            'status' => 'published',
            'expires_at' => now()->addHour(),
            'image_path' => 'letters/active.jpg',
        ]);
        Storage::disk('public')->put('letters/active.jpg', str_repeat('a', 300 * 1024));

        $oldestExpired = $this->letter($user, [
            'title' => 'Oldest expired',
            'body' => 'Keep this private message.',
            'status' => 'published',
            'expires_at' => now()->subDays(5),
            'image_path' => 'letters/oldest.jpg',
            'audio_path' => 'letters/audio/oldest.mp3',
        ]);
        Storage::disk('public')->put('letters/oldest.jpg', str_repeat('b', 300 * 1024));
        Storage::disk('public')->put('letters/audio/oldest.mp3', str_repeat('c', 100 * 1024));
        $memory = $oldestExpired->memories()->create(['title' => 'Keep this memory caption', 'sort_order' => 0]);
        Storage::disk('public')->put('letters/memories/oldest.jpg', str_repeat('d', 100 * 1024));
        $memoryImage = $memory->images()->create(['image_path' => 'letters/memories/oldest.jpg', 'sort_order' => 0]);
        $link = $oldestExpired->link()->create(['token' => str_repeat('q', 64), 'is_active' => true]);
        $response = $oldestExpired->responses()->create([
            'letter_link_id' => $link->id,
            'response_value' => 'positive',
            'message' => 'Keep this response.',
            'submitted_at' => now(),
        ]);

        $newerExpired = $this->letter($user, [
            'title' => 'Newer expired',
            'status' => 'published',
            'expires_at' => now()->subDay(),
            'image_path' => 'letters/newer.jpg',
        ]);
        Storage::disk('public')->put('letters/newer.jpg', str_repeat('e', 500 * 1024));

        $this->artisan('dearyou:process-storage')->assertSuccessful();

        Storage::disk('public')->assertExists($active->image_path);
        Storage::disk('public')->assertExists($newerExpired->image_path);
        Storage::disk('public')->assertMissing('letters/oldest.jpg');
        Storage::disk('public')->assertMissing('letters/audio/oldest.mp3');
        Storage::disk('public')->assertMissing('letters/memories/oldest.jpg');

        $oldestExpired->refresh();
        $this->assertNull($oldestExpired->image_path);
        $this->assertNull($oldestExpired->audio_path);
        $this->assertNotNull($oldestExpired->media_cleaned_at);
        $this->assertSame('Keep this private message.', $oldestExpired->body);
        $this->assertDatabaseHas('letter_memories', ['id' => $memory->id, 'title' => 'Keep this memory caption']);
        $this->assertDatabaseMissing('letter_memory_images', ['id' => $memoryImage->id]);
        $this->assertDatabaseHas('responses', ['id' => $response->id, 'message' => 'Keep this response.']);
        $this->assertDatabaseHas('storage_cleanup_logs', [
            'user_id' => $user->id,
            'letter_id' => $oldestExpired->id,
            'files_removed' => 3,
        ]);
        $this->assertDatabaseMissing('storage_cleanup_logs', ['letter_id' => $newerExpired->id]);
        $this->assertNull($user->fresh()->storage_cleanup_due_at);
        Notification::assertSentTo($user, StorageCleanupCompleted::class);
    }

    public function test_storage_warning_clears_when_usage_returns_under_limit(): void
    {
        Storage::fake('public');
        Notification::fake();
        config(['dearyou.storage_limit_mb' => 1]);
        $user = User::factory()->create([
            'storage_warning_at' => now()->subDay(),
            'storage_cleanup_due_at' => now()->addDays(6),
        ]);

        $this->artisan('dearyou:process-storage')->assertSuccessful();

        $user->refresh();
        $this->assertNull($user->storage_warning_at);
        $this->assertNull($user->storage_cleanup_due_at);
        Notification::assertNothingSent();
    }

    public function test_admin_can_view_inbox(): void
    {
        $user = User::factory()->create();
        $letter = $this->letter($user);
        $link = $letter->link()->create(['token' => str_repeat('c', 64)]);
        $letter->responses()->create(['letter_link_id' => $link->id, 'response_value' => 'positive', 'message' => 'Lovely', 'submitted_at' => now()]);
        $this->actingAs($user)->get('/inbox')->assertOk()->assertSee('Lovely');
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

        $this->actingAs($user)->get("/responses/{$response->id}")
            ->assertOk()
            ->assertSee('A private answer');
        $this->assertNotNull($response->fresh()->read_at);

        $this->actingAs($user)
            ->patch("/responses/{$response->id}/unread")
            ->assertRedirect('/inbox');
        $this->assertNull($response->fresh()->read_at);
    }

    public function test_create_letter_shows_the_accepted_confession_preview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/letters/create')
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

        $this->actingAs($user)->put("/letters/{$letter->id}", $payload)->assertRedirect();
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
        $this->actingAs($user)->put("/letters/{$letter->id}", $payload)->assertRedirect();
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
            ->from("/responses/{$response->id}")
            ->delete("/responses/{$response->id}")
            ->assertRedirect('/inbox')
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

        $this->actingAs($user)->get('/inbox?status=unread')
            ->assertOk()
            ->assertSee('Owned response')
            ->assertDontSee('Foreign response');

        $this->actingAs($user)->post('/inbox/bulk', [
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

        $this->actingAs($user)->put("/letters/{$letter->id}", $payload + [
            'image' => UploadedFile::fake()->image('memory.jpg'),
        ])->assertRedirect();

        $path = $letter->fresh()->image_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->put("/letters/{$letter->id}", $payload + [
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

        $this->actingAs($user)->put("/letters/{$letter->id}", $payload)->assertRedirect();
        Storage::disk('public')->assertExists($letter->fresh()->image_path);

        $this->actingAs($user)->post("/letters/{$letter->id}/memories", [
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
        $this->actingAs($user)->put("/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->image_path);
        $this->assertStringEndsWith('.mp4', $letter->image_path);

        $this->actingAs($user)->post("/letters/{$letter->id}/memories", [
            'title' => 'A video memory',
            'memory_images' => [
                UploadedFile::fake()->create('memory.mp4', 100, 'video/mp4'),
            ],
        ])->assertRedirect();

        $videoMemory = $letter->memories()->where('title', 'A video memory')->firstOrFail();
        Storage::disk('public')->assertExists($videoMemory->images->first()->image_path);
        $this->assertStringEndsWith('.mp4', $videoMemory->images->first()->image_path);

        $payload['image'] = UploadedFile::fake()->create('telegram-animation.webm', 100, 'video/webm');
        $this->actingAs($user)->put("/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->image_path);
        $this->assertStringEndsWith('.webm', $letter->image_path);

        $this->actingAs($user)->post("/letters/{$letter->id}/memories", [
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
            ->assertSee('<video src="'.Storage::url($letter->image_path).'" preload="metadata" muted loop playsinline data-letter-video data-autoplay-when-visible', false)
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

        $this->actingAs($user)->put("/letters/{$letter->id}", $payload)->assertRedirect();
        $letter->refresh();
        Storage::disk('public')->assertExists($letter->sender_profile_path);
        Storage::disk('public')->assertExists($letter->recipient_profile_path);

        $senderPath = $letter->sender_profile_path;
        $recipientPath = $letter->recipient_profile_path;
        $this->actingAs($user)->delete("/letters/{$letter->id}")->assertRedirect('/letters');

        Storage::disk('public')->assertMissing($senderPath);
        Storage::disk('public')->assertMissing($recipientPath);
    }

    public function test_admin_can_manage_anniversary_memories_and_recipient_sees_timeline(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $letter = $this->letter($user, ['category' => 'anniversary', 'status' => 'published']);
        $link = $letter->link()->create(['token' => str_repeat('i', 64), 'is_active' => true]);

        $this->actingAs($user)->post("/letters/{$letter->id}/memories", [
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
        $this->actingAs($user)->put("/memories/{$memory->id}", [
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
        $this->actingAs($user)->patchJson("/letters/{$letter->id}/memories/reorder", [
            'order' => [$secondMemory->id, $memory->id],
        ])->assertNoContent();
        $this->assertSame(0, $secondMemory->fresh()->sort_order);
        $this->assertSame(1, $memory->fresh()->sort_order);

        $images = $memory->images()->get();
        $this->actingAs($user)->patchJson("/memories/{$memory->id}/images/reorder", [
            'order' => $images->pluck('id')->reverse()->values()->all(),
        ])->assertNoContent();
        $this->assertSame($images->last()->id, $memory->images()->first()->id);

        $remainingPaths = $memory->images->pluck('image_path');
        $this->actingAs($user)->delete("/memories/{$memory->id}")->assertRedirect();
        $remainingPaths->each(fn ($path) => Storage::disk('public')->assertMissing($path));
    }

    public function test_admin_can_search_and_filter_letters(): void
    {
        $user = User::factory()->create();
        $this->letter($user, ['title' => 'Birthday for Taylor', 'category' => 'birthday', 'status' => 'published']);
        $this->letter($user, ['title' => 'Private apology', 'category' => 'apology', 'status' => 'draft']);

        $this->actingAs($user)->get('/letters?search=Taylor&status=published&category=birthday')
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

        $this->actingAs($user)->get("/letters/{$letter->id}/edit")
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
            ->put("/letters/{$letter->id}", $payload)
            ->assertRedirect();

        $this->assertSame('gift', $letter->fresh()->envelope_style);
        $this->assertSame('diamond', $letter->fresh()->seal_style);
        $this->get("/l/{$link->token}")
            ->assertOk()
            ->assertSee('envelope-style-gift', false)
            ->assertSee('seal-style-diamond', false)
            ->assertSee('bi-gem', false);

        $this->actingAs($user)
            ->put("/letters/{$letter->id}", array_merge($payload, ['envelope_style' => 'unknown']))
            ->assertSessionHasErrors('envelope_style');

        $this->actingAs($user)
            ->put("/letters/{$letter->id}", array_merge($payload, ['seal_style' => 'unknown']))
            ->assertSessionHasErrors('seal_style');
    }

    public function test_admin_can_view_an_owned_letter_but_not_another_users_letter(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $letter = $this->letter($user, ['title' => 'A private detail page']);
        $foreignLetter = $this->letter($other, ['title' => 'Not yours']);

        $this->actingAs($user)->get("/letters/{$letter->id}")
            ->assertOk()
            ->assertSee('A private detail page')
            ->assertSee('Edit letter');

        $this->actingAs($user)->get("/letters/{$foreignLetter->id}")
            ->assertForbidden();
    }

    public function test_admin_can_delete_an_owned_letter_from_letter_pages(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $letter = $this->letter($user, ['image_path' => 'letters/delete-me.jpg']);
        Storage::disk('public')->put('letters/delete-me.jpg', 'image');

        $this->actingAs($user)
            ->get('/letters')
            ->assertOk()
            ->assertSee(route('letters.destroy', $letter), false)
            ->assertSee('Delete');

        $this->actingAs($user)
            ->get("/letters/{$letter->id}")
            ->assertOk()
            ->assertSee(route('letters.destroy', $letter), false)
            ->assertSee('Delete letter');

        $this->actingAs($user)
            ->delete("/letters/{$letter->id}")
            ->assertRedirect('/letters')
            ->assertSessionHas('success', 'Letter deleted.');

        $this->assertSoftDeleted($letter);
        Storage::disk('public')->assertMissing('letters/delete-me.jpg');
    }

    public function test_admin_can_update_profile_with_current_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OldPassword1']);

        $this->actingAs($user)->put('/account/profile', [
            'name' => 'New Admin',
            'email' => 'new@example.com',
            'current_password' => 'OldPassword1',
        ])->assertRedirect('/verify-email');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New Admin', 'email' => 'new@example.com']);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_creator_can_upload_replace_and_remove_a_profile_picture(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['password' => 'OldPassword1']);

        $this->actingAs($user)->put('/account/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword1',
            'avatar' => UploadedFile::fake()->image('profile.jpg', 400, 400),
        ])->assertRedirect();

        $firstAvatar = $user->fresh()->avatar_path;
        $this->assertNotNull($firstAvatar);
        Storage::disk('public')->assertExists($firstAvatar);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee(Storage::url($firstAvatar), false);

        $this->actingAs($user)->put('/account/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword1',
            'avatar' => UploadedFile::fake()->image('replacement.png', 300, 300),
        ])->assertRedirect();

        $secondAvatar = $user->fresh()->avatar_path;
        $this->assertNotSame($firstAvatar, $secondAvatar);
        Storage::disk('public')->assertMissing($firstAvatar);
        Storage::disk('public')->assertExists($secondAvatar);

        $this->actingAs($user)->put('/account/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword1',
            'remove_avatar' => 1,
        ])->assertRedirect();

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($secondAvatar);
    }

    public function test_creator_navigation_is_shared_and_advanced_api_controls_are_hidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('data-user-navbar', false)
            ->assertSee('My DearYou');

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertSee('data-user-navbar', false)
            ->assertSee('Profile picture')
            ->assertDontSee('Advanced: API access');
    }

    public function test_logout_returns_the_user_to_the_public_homepage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/')
            ->assertSessionHas('success', 'You have been logged out.');

        $this->assertGuest();
    }

    public function test_admin_can_change_password_and_api_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);
        $user->createToken('test');

        $this->actingAs($user)->put('/account/password', [
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

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword2',
            'password_confirmation' => 'NewPassword2',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword1', $user->fresh()->password));
    }

    public function test_only_platform_admins_can_manage_platform_settings(): void
    {
        $creator = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($creator)->get('/admin/settings')->assertForbidden();

        $this->actingAs($admin)->put('/admin/settings', [
            'allowed_expiry_minutes' => [15, 30],
            'default_expiry_minutes' => 30,
            'storage_limit_mb' => 400,
            'cleanup_grace_days' => 10,
            'cleanup_enabled' => 1,
            'cleanup_policy' => 'oldest_expired',
            'enabled_categories' => ['custom', 'birthday'],
            'letter_media_limit_mb' => 8,
            'audio_limit_mb' => 20,
            'profile_image_limit_mb' => 4,
            'memory_files_per_upload' => 6,
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($creator)->get('/letters/create')
            ->assertOk()
            ->assertSee('15 minutes')
            ->assertSee('30 minutes')
            ->assertDontSee('2 hours')
            ->assertSee('Custom')
            ->assertSee('Birthday')
            ->assertDontSee('Confession')
            ->assertSee('up to 8 MB')
            ->assertSee('up to 20 MB');

        $this->actingAs($admin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Creation upload limits')
            ->assertSee('+15 minutes')
            ->assertSee('+1 hour')
            ->assertSee('+1 day')
            ->assertSee('value="6"', false);

        $this->assertSame(400 * 1024 * 1024, app(CreatorStorage::class)->limitBytes());
        $this->assertSame(['custom', 'birthday'], app(PlatformSettings::class)->enabledCategories());
        $this->assertSame(6, app(PlatformSettings::class)->memoryFilesPerUpload());
        $this->assertDatabaseHas('moderation_audits', [
            'admin_user_id' => $admin->id,
            'action' => 'platform_settings_updated',
        ]);
    }

    public function test_platform_settings_accept_browser_string_values_and_show_validation_feedback(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put('/admin/settings', [
            'allowed_expiry_minutes' => ['15', '60'],
            'default_expiry_minutes' => '60',
            'storage_limit_mb' => '250',
            'cleanup_grace_days' => '7',
            'cleanup_enabled' => '1',
            'cleanup_policy' => 'oldest_expired',
            'enabled_categories' => ['confession', 'birthday'],
            'letter_media_limit_mb' => '10',
            'audio_limit_mb' => '20',
            'profile_image_limit_mb' => '5',
            'memory_files_per_upload' => '5',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($admin)->from('/admin/settings')->put('/admin/settings', [
            'allowed_expiry_minutes' => ['15'],
            'default_expiry_minutes' => '15',
            'storage_limit_mb' => '0',
            'cleanup_grace_days' => '7',
            'cleanup_policy' => 'oldest_expired',
            'enabled_categories' => ['confession'],
            'letter_media_limit_mb' => '10',
            'audio_limit_mb' => '20',
            'profile_image_limit_mb' => '5',
            'memory_files_per_upload' => '5',
        ])->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('storage_limit_mb');
    }

    public function test_platform_admin_can_add_custom_publishing_windows(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $creator = User::factory()->create();

        $this->actingAs($admin)->put('/admin/settings', [
            'allowed_expiry_minutes' => ['15'],
            'custom_expiry_minutes' => '45, 180, 1440',
            'default_expiry_minutes' => '180',
            'storage_limit_mb' => '250',
            'cleanup_grace_days' => '7',
            'cleanup_enabled' => '1',
            'cleanup_policy' => 'oldest_expired',
            'enabled_categories' => ['confession'],
            'letter_media_limit_mb' => '10',
            'audio_limit_mb' => '20',
            'profile_image_limit_mb' => '5',
            'memory_files_per_upload' => '5',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(
            [15 => '15 minutes', 45 => '45 minutes', 180 => '3 hours', 1440 => '1 day'],
            app(PlatformSettings::class)->expiryOptions(),
        );
        $this->assertSame(180, app(PlatformSettings::class)->defaultExpiryMinutes());

        $this->actingAs($creator)->get('/letters/create')
            ->assertOk()
            ->assertSee('45 minutes')
            ->assertSee('3 hours')
            ->assertSee('1 day')
            ->assertDontSee('2 hours');
    }

    public function test_custom_publishing_windows_must_be_within_thirty_days(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->from('/admin/settings')->put('/admin/settings', [
            'custom_expiry_minutes' => '43201',
            'default_expiry_minutes' => '43201',
            'storage_limit_mb' => '250',
            'cleanup_grace_days' => '7',
            'cleanup_policy' => 'oldest_expired',
            'enabled_categories' => ['confession'],
            'letter_media_limit_mb' => '10',
            'audio_limit_mb' => '20',
            'profile_image_limit_mb' => '5',
            'memory_files_per_upload' => '5',
        ])->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('custom_expiry_minutes');
    }

    public function test_creation_settings_control_categories_and_upload_limits(): void
    {
        Storage::fake('public');
        app(PlatformSettings::class)->update([
            'enabled_categories' => ['custom'],
            'letter_media_limit_mb' => 2,
            'audio_limit_mb' => 3,
            'profile_image_limit_mb' => 1,
            'memory_files_per_upload' => 2,
        ]);

        $creator = User::factory()->create();

        $this->actingAs($creator)->get('/account')
            ->assertOk()
            ->assertSee('up to 1 MB');

        $this->actingAs($creator)->put('/account/profile', [
            'name' => $creator->name,
            'email' => $creator->email,
            'current_password' => 'password',
            'avatar' => UploadedFile::fake()->image('large-profile.jpg')->size(2 * 1024),
        ])->assertSessionHasErrors('avatar');

        $this->actingAs($creator)->post('/letters', $this->letterPayload([
            'category' => 'birthday',
        ]))->assertSessionHasErrors('category');

        $this->actingAs($creator)->post('/letters', $this->letterPayload([
            'image' => UploadedFile::fake()->create('large.mp4', 3 * 1024, 'video/mp4'),
        ]))->assertSessionHasErrors('image');

        $this->actingAs($creator)->post('/letters', $this->letterPayload([
            'audio' => UploadedFile::fake()->create('large.mp3', 4 * 1024, 'audio/mpeg'),
        ]))->assertSessionHasErrors('audio');

        $existing = $this->letter($creator, ['category' => 'confession']);
        $this->actingAs($creator)->put("/letters/{$existing->id}", $this->letterPayload([
            'category' => 'confession',
            'title' => 'Still editable',
        ]))->assertSessionDoesntHaveErrors();

        $memoryLetter = $this->letter($creator, ['category' => 'custom']);
        $this->actingAs($creator)->post("/letters/{$memoryLetter->id}/memories", [
            'title' => 'Too many at once',
            'memory_images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
        ])->assertSessionHasErrors('memory_images');
    }

    public function test_admin_can_disable_public_letter_access_and_action_is_audited(): void
    {
        $creator = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $letter = $this->letter($creator, [
            'status' => 'published',
            'expires_at' => now()->addHour(),
        ]);
        $link = $letter->link()->create([
            'token' => str_repeat('m', 64),
            'is_active' => true,
            'expires_at' => now()->addHour(),
        ]);

        $this->get("/l/{$link->token}")->assertOk();

        $this->actingAs($admin)->patch("/admin/moderation/letters/{$letter->id}/disable", [
            'reason' => 'Reported public safety concern.',
        ])->assertRedirect()->assertSessionHas('success');

        $this->get("/l/{$link->token}")->assertNotFound();
        $this->assertNotNull($letter->fresh()->moderation_disabled_at);
        $this->assertDatabaseHas('moderation_audits', [
            'letter_id' => $letter->id,
            'action' => 'letter_disabled',
        ]);
    }

    public function test_listing_filters_auto_submit_and_filter_moderation_users_and_audits(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Platform Admin']);
        $creator = User::factory()->create(['name' => 'Visible Creator', 'role' => User::ROLE_USER]);
        $otherCreator = User::factory()->create(['name' => 'Hidden Creator', 'role' => User::ROLE_USER]);
        $apology = $this->letter($creator, [
            'category' => 'apology',
            'title' => 'Visible apology',
            'status' => 'published',
        ]);
        $this->letter($otherCreator, [
            'category' => 'confession',
            'title' => 'Hidden confession',
            'status' => 'published',
        ]);

        ModerationAudit::create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $creator->id,
            'letter_id' => $apology->id,
            'action' => 'letter_disabled',
            'reason' => 'Visible moderation reason.',
        ]);
        ModerationAudit::create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $otherCreator->id,
            'action' => 'user_restored',
            'reason' => 'Hidden audit reason.',
        ]);

        $this->actingAs($admin)->get('/admin/moderation/letters?category=apology')
            ->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertSee('Visible apology')
            ->assertDontSee('Hidden confession');

        $this->actingAs($admin)->get('/admin/users?search=Visible+Creator&role=user')
            ->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertSee('Visible Creator')
            ->assertDontSee('Hidden Creator');

        $this->actingAs($admin)->get('/admin/audit?action=letter_disabled')
            ->assertOk()
            ->assertSee('data-auto-filter', false)
            ->assertSee('Visible moderation reason.')
            ->assertDontSee('Hidden audit reason.');

        $this->actingAs($creator)->get('/letters')
            ->assertOk()
            ->assertSee('data-auto-filter', false);

        $this->actingAs($creator)->get('/inbox')
            ->assertOk()
            ->assertSee('data-auto-filter', false);
    }

    public function test_admin_must_log_a_reason_to_reveal_letter_content_and_never_sees_response_text(): void
    {
        $creator = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $letter = $this->letter($creator, [
            'title' => 'Moderation metadata title',
            'body' => 'Private letter body for review.',
        ]);
        $link = $letter->link()->create(['token' => str_repeat('n', 64), 'is_active' => true]);
        Response::create([
            'letter_id' => $letter->id,
            'letter_link_id' => $link->id,
            'response_value' => 'positive',
            'message' => 'A recipient reply that admins must never see.',
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)->get("/admin/moderation/letters/{$letter->id}")
            ->assertOk()
            ->assertSee('Moderation metadata title')
            ->assertDontSee('Private letter body for review.')
            ->assertDontSee('A recipient reply that admins must never see.');

        $this->actingAs($admin)
            ->followingRedirects()
            ->post("/admin/moderation/letters/{$letter->id}/reveal", [
                'reason' => 'Investigating a reported safety concern.',
            ])
            ->assertOk()
            ->assertSee('Private letter body for review.')
            ->assertDontSee('A recipient reply that admins must never see.');

        $this->assertDatabaseHas('moderation_audits', [
            'letter_id' => $letter->id,
            'action' => 'letter_content_revealed',
        ]);
    }

    public function test_admin_can_soft_delete_and_restore_a_letter_without_removing_media(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('letters/kept.jpg', 'image');

        $creator = User::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $letter = $this->letter($creator, ['image_path' => 'letters/kept.jpg']);

        $this->actingAs($admin)->delete("/admin/moderation/letters/{$letter->id}", [
            'reason' => 'Temporarily removed for moderation review.',
        ])->assertRedirect('/admin/moderation/letters');

        $this->assertSoftDeleted($letter);
        Storage::disk('public')->assertExists('letters/kept.jpg');

        $this->actingAs($admin)->patch("/admin/moderation/letters/{$letter->id}/restore", [
            'reason' => 'Review completed and restriction cleared.',
        ])->assertRedirect();

        $this->assertNotSoftDeleted($letter);
        $this->assertSame(2, ModerationAudit::where('letter_id', $letter->id)->count());
    }

    public function test_platform_admin_can_soft_delete_and_restore_a_user_without_losing_data(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $creator = User::factory()->create();
        $letter = $this->letter($creator, ['image_path' => 'letters/account-kept.jpg']);
        Storage::disk('public')->put('letters/account-kept.jpg', 'image');

        $this->actingAs($admin)->delete("/admin/users/{$creator->id}", [
            'reason' => 'Requested account moderation review.',
        ])->assertRedirect('/admin/users');

        $this->assertSoftDeleted($creator);
        $this->assertDatabaseHas('letters', ['id' => $letter->id, 'user_id' => $creator->id]);
        Storage::disk('public')->assertExists('letters/account-kept.jpg');

        $this->actingAs($admin)->patch("/admin/users/{$creator->id}/restore", [
            'reason' => 'Account review completed successfully.',
        ])->assertRedirect();

        $this->assertNotSoftDeleted($creator);
        $this->assertDatabaseHas('moderation_audits', [
            'target_user_id' => $creator->id,
            'action' => 'user_soft_deleted',
        ]);
        $this->assertDatabaseHas('moderation_audits', [
            'target_user_id' => $creator->id,
            'action' => 'user_restored',
        ]);
    }

    public function test_creator_can_delete_their_account_with_password_and_delete_confirmation(): void
    {
        $creator = User::factory()->create(['password' => 'StrongPass1']);
        $letter = $this->letter($creator, [
            'status' => 'published',
            'expires_at' => now()->addHour(),
        ]);
        $link = $letter->link()->create([
            'token' => str_repeat('d', 64),
            'is_active' => true,
            'expires_at' => now()->addHour(),
        ]);
        $creator->createToken('phone');

        $this->actingAs($creator)->delete('/account', [
            'current_password' => 'StrongPass1',
            'confirmation' => 'DELETE',
        ])->assertRedirect('/')
            ->assertSessionHas('success', 'Your account was deleted.');

        $this->assertGuest();
        $this->assertSoftDeleted($creator);
        $this->assertCount(0, $creator->fresh()->tokens);
        $this->get("/l/{$link->token}")->assertNotFound();
    }

    public function test_creator_account_deletion_requires_password_and_exact_confirmation(): void
    {
        $creator = User::factory()->create(['password' => 'StrongPass1']);

        $this->actingAs($creator)->delete('/account', [
            'current_password' => 'wrong-password',
            'confirmation' => 'delete',
        ])->assertSessionHasErrors(['current_password', 'confirmation']);

        $this->assertNotSoftDeleted($creator);
        $this->assertAuthenticatedAs($creator);
    }

    public function test_admin_can_permanently_delete_a_soft_deleted_account_and_its_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $creator = User::factory()->create([
            'email' => 'remove@example.com',
            'avatar_path' => 'avatars/remove.jpg',
        ]);
        $letter = $this->letter($creator, [
            'image_path' => 'letters/remove.jpg',
            'audio_path' => 'letters/remove.mp3',
            'sender_profile_path' => 'letters/sender.jpg',
            'recipient_profile_path' => 'letters/recipient.jpg',
        ]);
        $memory = $letter->memories()->create(['title' => 'Memory']);
        $memory->images()->create(['image_path' => 'memories/remove.jpg']);
        foreach ([
            'avatars/remove.jpg',
            'letters/remove.jpg',
            'letters/remove.mp3',
            'letters/sender.jpg',
            'letters/recipient.jpg',
            'memories/remove.jpg',
        ] as $path) {
            Storage::disk('public')->put($path, 'data');
        }
        $creatorId = $creator->id;
        $letterId = $letter->id;
        $creator->delete();

        $this->actingAs($admin)->delete("/admin/users/{$creatorId}/permanent", [
            'reason' => 'User requested permanent erasure.',
            'confirmation' => 'remove@example.com',
        ])->assertRedirect('/admin/users')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $creatorId]);
        $this->assertDatabaseMissing('letters', ['id' => $letterId]);
        foreach ([
            'avatars/remove.jpg',
            'letters/remove.jpg',
            'letters/remove.mp3',
            'letters/sender.jpg',
            'letters/recipient.jpg',
            'memories/remove.jpg',
        ] as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertDatabaseHas('moderation_audits', [
            'target_user_id' => null,
            'action' => 'user_permanently_deleted',
        ]);
    }

    public function test_suspended_and_deleted_creators_have_no_public_letters(): void
    {
        $creator = User::factory()->create();
        $letter = $this->letter($creator, [
            'status' => 'published',
            'expires_at' => now()->addHour(),
        ]);
        $link = $letter->link()->create([
            'token' => str_repeat('q', 64),
            'is_active' => true,
            'expires_at' => now()->addHour(),
        ]);

        $this->get("/l/{$link->token}")->assertOk();

        $creator->update(['disabled_at' => now()]);
        $this->get("/l/{$link->token}")->assertNotFound();

        $creator->update(['disabled_at' => null]);
        $creator->delete();
        $this->get("/l/{$link->token}")->assertNotFound();

        $creator->restore();
        $this->get("/l/{$link->token}")->assertOk();
    }

    public function test_admin_cannot_delete_self_or_the_last_administrator(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}", [
            'reason' => 'This should never be allowed.',
        ])->assertStatus(422);

        $this->assertNotSoftDeleted($admin);
    }

    public function test_publishing_is_rate_limited_per_creator(): void
    {
        $creator = User::factory()->create();
        $letter = $this->letter($creator);

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($creator)->post("/letters/{$letter->id}/publish")->assertRedirect();
        }

        $this->actingAs($creator)->post("/letters/{$letter->id}/publish")->assertTooManyRequests();
    }

    public function test_guest_can_send_private_feedback(): void
    {
        Notification::fake();
        config(['dearyou.feedback_notify_email' => 'admin@example.com']);

        $this->from('/#feedback')->post('/feedback', [
            'category' => 'suggestion',
            'rating' => 5,
            'email' => 'visitor@example.com',
            'message' => 'It would be helpful to have another gentle envelope style.',
            'source_page' => 'https://dearyou.test/',
            'website' => '',
        ])->assertRedirect('/#feedback')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('feedback', [
            'category' => 'suggestion',
            'rating' => 5,
            'email' => 'visitor@example.com',
            'status' => 'new',
        ]);

        Notification::assertSentOnDemand(FeedbackReceived::class);
    }

    public function test_feedback_is_private_to_platform_admins(): void
    {
        $creator = User::factory()->create();
        $feedback = Feedback::create([
            'category' => 'bug',
            'message' => 'The mobile menu needs a little attention.',
            'status' => 'new',
        ]);

        $this->get('/admin/feedback')->assertRedirect('/login');
        $this->actingAs($creator)->get('/admin/feedback')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get('/admin/feedback')
            ->assertOk()
            ->assertSee($feedback->message);
    }

    public function test_platform_dashboard_summarizes_recent_feedback(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Feedback::create([
            'category' => 'suggestion',
            'message' => 'Please add another envelope style.',
            'rating' => 5,
            'status' => 'new',
        ]);
        Feedback::create([
            'category' => 'design',
            'message' => 'The mobile layout is looking better.',
            'rating' => 3,
            'status' => 'reviewed',
        ]);

        $this->actingAs($admin)->get('/admin/platform')
            ->assertOk()
            ->assertSee('Feedback')
            ->assertSee('New feedback')
            ->assertSee('Average rating')
            ->assertSee('<span class="nav-count">1</span>', false)
            ->assertSee('4.0')
            ->assertSee('Please add another envelope style.');
    }

    public function test_every_auto_filter_has_a_permanent_clear_action(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $creator = User::factory()->create();

        foreach ([
            '/admin/feedback' => route('admin.feedback.index'),
            '/admin/audit' => route('admin.audit'),
            '/admin/moderation/letters' => route('admin.moderation.index'),
            '/admin/users' => route('admin.users.index'),
            '/admin/letters' => route('admin.letters.index'),
            '/admin/inbox' => route('admin.inbox'),
        ] as $url => $clearUrl) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('auto-filter-clear', false)
                ->assertSee($clearUrl, false);
        }

        foreach ([
            '/letters' => route('letters.index'),
            '/inbox' => route('inbox'),
        ] as $url => $clearUrl) {
            $this->actingAs($creator)->get($url)
                ->assertOk()
                ->assertSee('auto-filter-clear', false)
                ->assertSee($clearUrl, false);
        }
    }

    public function test_admin_can_review_resolve_and_delete_feedback(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $feedback = Feedback::create([
            'category' => 'design',
            'message' => 'Please improve the spacing on a small screen.',
            'status' => 'new',
        ]);

        $this->actingAs($admin)->get("/admin/feedback/{$feedback->id}")->assertOk();
        $this->assertSame('reviewed', $feedback->fresh()->status);

        $this->actingAs($admin)->patch("/admin/feedback/{$feedback->id}", [
            'status' => 'resolved',
        ])->assertRedirect();
        $this->assertSame('resolved', $feedback->fresh()->status);

        $this->actingAs($admin)->delete("/admin/feedback/{$feedback->id}")
            ->assertRedirect('/admin/feedback');
        $this->assertDatabaseMissing('feedback', ['id' => $feedback->id]);
    }

    public function test_public_navigation_feedback_and_password_controls_are_rendered(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('#feedback')
            ->assertSee('data-navbar-panel', false)
            ->assertSee('home-feedback', false)
            ->assertSee('feedback-stars', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('data-password-input', false)
            ->assertSee('data-password-toggle', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('data-password-toggle', false);
    }

    public function test_production_readiness_command_accepts_a_complete_configuration(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.debug' => false,
            'app.url' => 'https://dearyou.test',
            'mail.default' => 'resend',
            'mail.from.address' => 'hello@dearyou.test',
            'queue.default' => 'database',
            'services.resend.key' => 're_test_only',
        ]);

        $this->artisan('dearyou:check-production', ['--strict' => true])
            ->expectsOutputToContain('DearYou is ready')
            ->assertSuccessful();
    }

    public function test_expired_security_codes_are_pruned_without_removing_active_codes(): void
    {
        $expiredUser = User::factory()->create();
        $activeUser = User::factory()->create();

        DB::table('email_verification_codes')->insert([
            [
                'user_id' => $expiredUser->id,
                'code' => Hash::make('111111'),
                'attempts' => 0,
                'expires_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $activeUser->id,
                'code' => Hash::make('222222'),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('password_reset_codes')->insert([
            [
                'email' => 'expired@example.com',
                'code' => Hash::make('333333'),
                'attempts' => 0,
                'expires_at' => now()->subMinute(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'email' => 'active@example.com',
                'code' => Hash::make('444444'),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->artisan('dearyou:prune-security-codes')
            ->expectsOutput('Security codes pruned: 1 verification, 1 password reset.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('email_verification_codes', ['user_id' => $expiredUser->id]);
        $this->assertDatabaseHas('email_verification_codes', ['user_id' => $activeUser->id]);
        $this->assertDatabaseMissing('password_reset_codes', ['email' => 'expired@example.com']);
        $this->assertDatabaseHas('password_reset_codes', ['email' => 'active@example.com']);
    }
}
