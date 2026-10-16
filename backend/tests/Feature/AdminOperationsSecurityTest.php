<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use App\Support\LetterPublisher;
use App\Support\PlatformSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AdminOperationsSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();
        config(['dearyou.admin_allowed_ips' => [], 'dearyou.backup_dir' => '']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function letter(User $owner): Letter
    {
        return $owner->letters()->create([
            'category' => 'custom', 'title' => 'Synthetic operations letter',
            'body' => 'Private synthetic message', 'theme' => 'warm',
            'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8',
            'decoration_type' => 'none', 'status' => 'draft',
            'expiry_minutes' => 60, 'allow_response' => true,
            'response_mode' => 'buttons_with_message',
        ]);
    }

    public function test_deleted_creator_remains_visible_to_moderation_and_search_but_public_access_stays_closed(): void
    {
        $owner = User::factory()->create(['name' => 'Deleted Synthetic Creator']);
        $letter = app(LetterPublisher::class)->publish($this->letter($owner));
        $owner->delete();

        // Public ownership must continue to exclude deleted users.
        $this->assertNull($letter->fresh()->user);
        $this->actingAs($this->admin())
            ->get('/admin/moderation/letters')
            ->assertOk()->assertSee($owner->name)->assertSee($owner->email)
            ->assertDontSee('Private synthetic message');
        $this->get('/admin/moderation/letters?search='.urlencode($owner->email))
            ->assertOk()->assertSee($letter->title);
        $this->get('/admin/moderation/letters/'.$letter->id)
            ->assertOk()->assertSee($owner->name)->assertDontSee('Private synthetic message');
        $this->get('/l/'.$letter->link->token)->assertNotFound();
    }

    public function test_purging_an_already_deleted_admin_preserves_the_remaining_active_administrator(): void
    {
        $active = $this->admin();
        $deleted = $this->admin();
        $deleted->delete();

        $this->actingAs($active)->delete('/admin/users/'.$deleted->id.'/permanent', [
            'confirmation' => $deleted->email, 'reason' => 'Synthetic permanent account removal',
        ])->assertRedirect('/admin/users')->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $deleted->id]);
        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->count());
        $this->assertNotSoftDeleted($active);
    }

    public function test_active_and_own_admin_accounts_still_cannot_be_permanently_deleted(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        foreach ([$admin, $other] as $target) {
            $this->actingAs($admin)->delete('/admin/users/'.$target->id.'/permanent', [
                'confirmation' => $target->email, 'reason' => 'Synthetic invalid permanent removal',
            ])->assertStatus(422);
            $this->assertDatabaseHas('users', ['id' => $target->id, 'deleted_at' => null]);
        }
    }

    public function test_removing_moderation_block_does_not_expose_a_link_republished_during_the_block(): void
    {
        $owner = User::factory()->create();
        $admin = $this->admin();
        $letter = app(LetterPublisher::class)->publish($this->letter($owner));
        $oldToken = $letter->link->token;
        $this->actingAs($admin)->patch('/admin/moderation/letters/'.$letter->id.'/disable', [
            'reason' => 'Synthetic review restriction',
        ])->assertRedirect();
        $this->actingAs($owner)->post('/letters/'.$letter->id.'/publish')->assertRedirect();
        $blockedToken = $letter->fresh()->link->token;
        $this->get('/l/'.$blockedToken)->assertNotFound();
        $this->actingAs($admin)->patch('/admin/moderation/letters/'.$letter->id.'/enable', [
            'reason' => 'Synthetic review completed',
        ])->assertRedirect();
        $this->assertNull($letter->fresh()->moderation_disabled_at);
        $this->assertFalse($letter->fresh()->link->is_active);
        foreach ([$oldToken, $blockedToken] as $token) {
            $this->get('/l/'.$token)->assertNotFound();
            $this->get('/l/'.$token.'/download?format=html')->assertNotFound();
            $this->postJson('/l/'.$token.'/response', ['response_value' => 'positive'])->assertNotFound();
        }
        $this->actingAs($owner)->post('/letters/'.$letter->id.'/publish')->assertRedirect();
        $finalToken = $letter->fresh()->link->token;
        $this->assertNotSame($blockedToken, $finalToken);
        $this->get('/l/'.$finalToken)->assertOk();
        $this->get('/l/'.$blockedToken)->assertNotFound();
    }

    #[DataProvider('moderationMutations')]
    public function test_moderation_mutation_and_its_audit_roll_back_together_on_database_failure(string $action): void
    {
        $owner = User::factory()->create();
        $letter = app(LetterPublisher::class)->publish($this->letter($owner));
        if ($action === 'enable') {
            $letter->update(['moderation_disabled_at' => now()]);
        } elseif ($action === 'restore') {
            $letter->delete();
        }
        $beforeLetter = DB::table('letters')->where('id', $letter->id)->first();
        $beforeLink = DB::table('letter_links')->where('letter_id', $letter->id)->first();
        DB::unprepared("CREATE TRIGGER reject_moderation_audit BEFORE INSERT ON moderation_audits BEGIN SELECT RAISE(ABORT, 'synthetic audit persistence failure'); END");
        $method = $action === 'destroy' ? 'delete' : 'patch';
        $path = '/admin/moderation/letters/'.$letter->id.($action === 'destroy' ? '' : '/'.$action);

        $this->actingAs($this->admin())->{$method}($path, [
            'reason' => 'Synthetic atomic moderation regression',
            'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertStatus(500);

        $this->assertEquals($beforeLetter, DB::table('letters')->where('id', $letter->id)->first());
        $this->assertEquals($beforeLink, DB::table('letter_links')->where('letter_id', $letter->id)->first());
        $this->assertDatabaseCount('moderation_audits', 0);
    }

    public static function moderationMutations(): array
    {
        return [
            'disable' => ['disable'], 'enable' => ['enable'], 'delete' => ['destroy'],
            'restore' => ['restore'], 'expiration override' => ['expiry'],
        ];
    }

    public function test_deleted_letters_require_restoration_before_public_access_or_expiration_changes(): void
    {
        $letter = app(LetterPublisher::class)->publish($this->letter(User::factory()->create()));
        $letter->delete();
        $this->actingAs($this->admin());
        foreach (['disable', 'enable', 'expiry'] as $action) {
            $this->patch('/admin/moderation/letters/'.$letter->id.'/'.$action, [
                'reason' => 'Synthetic invalid deleted letter change',
                'expires_at' => now()->addDay()->toDateTimeString(),
            ])->assertStatus(422);
        }
        $this->assertSoftDeleted($letter);
        $this->assertDatabaseCount('moderation_audits', 0);
    }

    public function test_health_reads_configured_queue_connection_and_tables(): void
    {
        config([
            'database.connections.health_diagnostic' => config('database.connections.sqlite'),
            'queue.default' => 'health_queue',
            'queue.connections.health_queue' => [
                'driver' => 'database', 'connection' => 'health_diagnostic',
                'table' => 'pending_tasks', 'queue' => 'mail',
            ],
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => 'health_diagnostic',
            'queue.failed.table' => 'delivery_failures',
        ]);
        $db = DB::connection('health_diagnostic');
        $db->getSchemaBuilder()->create('pending_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        $db->getSchemaBuilder()->create('delivery_failures', function (Blueprint $table) {
            $table->id();
        });
        $db->table('delivery_failures')->insert(['id' => 1]);

        $this->actingAs($this->admin())->get('/admin/health')->assertOk()
            ->assertViewHas('checks', function ($checks) {
                $byLabel = collect($checks)->keyBy('label');

                return $byLabel['Queue configuration']['state'] === 'ok'
                    && $byLabel['Failed queue jobs']['state'] === 'warning'
                    && $byLabel['Failed queue jobs']['detail'] === '1 failed job(s) currently recorded.';
            });
        DB::purge('health_diagnostic');
    }

    public function test_health_rejects_missing_configured_queue_tables_even_if_default_tables_exist(): void
    {
        config([
            'queue.default' => 'database', 'queue.connections.database.table' => 'missing_queue_tasks',
            'queue.failed.driver' => 'database-uuids', 'queue.failed.database' => 'sqlite',
            'queue.failed.table' => 'missing_delivery_failures',
        ]);
        $this->actingAs($this->admin())->get('/admin/health')->assertOk()
            ->assertViewHas('checks', function ($checks) {
                $byLabel = collect($checks)->keyBy('label');

                return $byLabel['Queue configuration']['state'] === 'bad'
                    && $byLabel['Failed queue jobs']['state'] === 'warning'
                    && str_contains($byLabel['Failed queue jobs']['detail'], 'unavailable');
            });
    }

    public function test_private_storage_health_accepts_a_writable_private_root_without_a_public_storage_link(): void
    {
        config(['filesystems.disks.public.root' => Storage::disk('public')->path('future/private-media')]);
        $this->actingAs($this->admin())->get('/admin/health')->assertOk()
            ->assertViewHas('checks', fn ($checks) => collect($checks)->firstWhere('label', 'Private media storage')['state'] === 'ok');
    }

    #[DataProvider('exposedMediaRoots')]
    public function test_storage_health_rejects_roots_exposed_by_web_or_legacy_storage(string $root): void
    {
        config(['filesystems.disks.public.root' => $root === 'web' ? public_path() : storage_path('app/public')]);
        $this->actingAs($this->admin())->get('/admin/health')->assertOk()
            ->assertViewHas('checks', fn ($checks) => collect($checks)->firstWhere('label', 'Private media storage')['state'] === 'bad');
    }

    public static function exposedMediaRoots(): array
    {
        return ['web root' => ['web'], 'legacy public storage' => ['legacy']];
    }

    #[DataProvider('nonLocalEnvironments')]
    public function test_sample_seed_is_rejected_outside_local_and_testing_before_any_writes(string $environment): void
    {
        $previous = app()->environment();
        app()->instance('env', $environment);
        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('Sample seeding must be rejected outside local/testing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restricted', $exception->getMessage());
        } finally {
            app()->instance('env', $previous);
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('letters', 0);
        $this->assertDatabaseCount('platform_settings', 0);
    }

    public static function nonLocalEnvironments(): array
    {
        return ['production' => ['production'], 'staging' => ['staging']];
    }

    #[DataProvider('reservedAdministratorIdentities')]
    public function test_sample_seed_never_promotes_or_changes_reserved_accounts_or_settings(string $state): void
    {
        app(PlatformSettings::class)->update(['storage_limit_mb' => 17, 'cleanup_enabled' => false]);
        $user = User::factory()->unverified()->create([
            'email' => (string) env('ADMIN_EMAIL', 'admin@dearyou.test'),
            'role' => $state === 'creator' ? User::ROLE_USER : User::ROLE_ADMIN,
            'disabled_at' => $state === 'disabled' ? now() : null,
        ]);
        // Simulate a historical row predating canonical email writes.
        DB::table('users')->where('id', $user->id)->update(['email' => strtoupper($user->email)]);
        if ($state === 'deleted') {
            $user->delete();
        }
        $before = DB::table('users')->where('id', $user->id)->first();
        $settings = DB::table('platform_settings')->orderBy('key')->get()->toJson();

        try {
            app(DatabaseSeeder::class)->run();
            $this->fail('Reserved identities must not be reused by the sample seed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reserved', $exception->getMessage());
        }

        $this->assertEquals($before, DB::table('users')->where('id', $user->id)->first());
        $this->assertSame($settings, DB::table('platform_settings')->orderBy('key')->get()->toJson());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('letters', 0);
    }

    public static function reservedAdministratorIdentities(): array
    {
        return ['creator' => ['creator'], 'deleted administrator' => ['deleted'], 'disabled administrator' => ['disabled']];
    }
}
