<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use App\Notifications\StorageLimitWarning;
use App\Support\AccountDeletion;
use App\Support\CreatorStorage;
use App\Support\StorageAllowanceManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

class MediaPrivacyStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    #[DataProvider('unavailableStates')]
    public function test_public_media_is_revoked_by_every_letter_access_rule(string $state): void
    {
        $letter = $this->letter();
        $url = $this->publicUrl($letter);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $this->get($url)->headers->get('Cache-Control'));
        match ($state) {
            'letter expiry' => $letter->update(['expires_at' => now()]),
            'link expiry' => $letter->link->update(['expires_at' => now()]),
            'disabled link' => $letter->link->update(['is_active' => false]),
            'regenerated link' => $letter->link->update(['token' => str_repeat('r', 64)]),
            'unpublished' => $letter->update(['status' => 'unpublished']),
            'moderated' => $letter->update(['moderation_disabled_at' => now()]),
            'disabled owner' => $letter->user->update(['disabled_at' => now()]),
            'deleted owner' => $letter->user->delete(),
            'deleted letter' => $letter->delete(),
        };
        $this->get($url)->assertNotFound();
        Storage::disk('public')->assertExists($letter->image_path);
    }

    public static function unavailableStates(): array
    {
        return array_map(fn ($state) => [$state], ['letter expiry', 'link expiry', 'disabled link', 'regenerated link', 'unpublished', 'moderated', 'disabled owner', 'deleted owner', 'deleted letter']);
    }

    public function test_media_identifier_cannot_read_another_letters_file_or_unattached_path(): void
    {
        $letter = $this->letter();
        Storage::disk('public')->put('letters/unattached.txt', 'private unrelated content');
        $this->get('/l/'.$letter->link->token.'/media/'.hash('sha256', 'letters/unattached.txt'))->assertNotFound();
        $other = $this->letter();
        $this->get('/l/'.$other->link->token.'/media/'.hash('sha256', $letter->image_path))->assertNotFound();
        $this->get('/storage/'.$letter->image_path)->assertForbidden();
    }

    public function test_owner_can_read_a_draft_but_other_accounts_cannot(): void
    {
        $letter = $this->letter();
        $letter->update(['status' => 'draft']);
        $url = route('media.owner', [$letter, hash('sha256', $letter->image_path)]);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($letter->user)->get($url)->assertOk();
        $this->get($this->publicUrl($letter))->assertNotFound();
    }

    public function test_media_supports_ranges_and_legacy_bytes_only_through_authorized_routes(): void
    {
        $letter = $this->letter();
        Storage::disk('public')->delete($letter->image_path);
        Storage::disk('legacy_media')->put($letter->image_path, '0123456789');
        $this->withHeader('Range', 'bytes=2-5')->get($this->publicUrl($letter))
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/10');
        $letter->link->update(['is_active' => false]);
        $this->get($this->publicUrl($letter))->assertNotFound();
    }

    #[DataProvider('apiMediaPermissions')]
    public function test_api_media_requires_owner_and_letter_read_scope(array $abilities, bool $own, int $status): void
    {
        $letter = $this->letter();
        $user = $own ? $letter->user : User::factory()->create();
        $token = $user->createToken('Media client', $abilities)->plainTextToken;
        $this->withToken($token)->get('/api/letters/'.$letter->id.'/media/'.hash('sha256', $letter->image_path))->assertStatus($status);
    }

    public static function apiMediaPermissions(): array
    {
        return [[['letters:read'], true, 200], [['letters:write'], true, 403], [['responses:read'], true, 403], [['letters:read'], false, 403]];
    }

    public function test_rendered_database_error_rolls_back_replacement_and_removes_new_file(): void
    {
        $letter = $this->letter();
        $oldPath = $letter->image_path;
        DB::unprepared("CREATE TRIGGER reject_letter_update BEFORE UPDATE ON letters BEGIN SELECT RAISE(ABORT, 'Synthetic write failure'); END");
        $this->actingAs($letter->user)->put('/letters/'.$letter->id, $this->payload() + ['image' => UploadedFile::fake()->image('new.jpg')])->assertStatus(500);
        $this->assertSame($oldPath, $letter->fresh()->image_path);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media_deletions', 0);
    }

    public function test_failed_deletion_is_durable_counts_toward_quota_and_can_be_retried(): void
    {
        $letter = $this->letter();
        $disk = Storage::disk('public');
        $failing = \Mockery::mock($disk)->makePartial();
        $failing->shouldReceive('delete')->andReturn(false);
        Storage::set('public', $failing);
        $this->actingAs($letter->user)->delete('/letters/'.$letter->id)->assertRedirect();
        $this->assertSoftDeleted($letter);
        $this->assertNull($letter->fresh()->image_path);
        $this->assertDatabaseHas('media_deletions', ['owner_id' => $letter->user_id, 'attempts' => 1]);
        $this->assertSame(10, app(CreatorStorage::class)->usedBytes($letter->user));
        Storage::set('public', $disk);
        $this->artisan('dearyou:retry-media-deletions')->assertSuccessful();
        Storage::disk('public')->assertMissing($letter->image_path);
        $this->assertDatabaseCount('media_deletions', 0);
        $this->assertSame(0, app(CreatorStorage::class)->usedBytes($letter->user));
    }

    public function test_partially_written_upload_is_removed_when_the_disk_reports_failure(): void
    {
        $letter = $this->letter();
        $oldPath = $letter->image_path;
        $disk = Storage::disk('public');
        $failing = \Mockery::mock($disk)->makePartial();
        $failing->shouldReceive('putFileAs')->once()->andReturnUsing(function (...$arguments) use ($disk) {
            $disk->putFileAs(...$arguments);

            return false;
        });
        Storage::set('public', $failing);
        $this->actingAs($letter->user)->put('/letters/'.$letter->id, $this->payload() + ['image' => UploadedFile::fake()->image('new.jpg')])->assertStatus(500);
        $this->assertSame($oldPath, $letter->fresh()->image_path);
        $this->assertSame([$oldPath], $disk->allFiles());
        $this->assertDatabaseCount('media_deletions', 0);
    }

    public function test_failed_warning_enqueue_does_not_start_grace_and_next_run_retries(): void
    {
        config(['dearyou.storage_limit_mb' => 1, 'mail.default' => 'smtp']);
        $letter = $this->letter();
        Storage::disk('public')->put($letter->image_path, str_repeat('x', 1024 * 1024 + 1));
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Synthetic queue failure'));
        try {
            app(StorageAllowanceManager::class)->process($letter->user);
            $this->fail('Queue failure must be retryable.');
        } catch (\RuntimeException) {
            $this->assertNull($letter->user->fresh()->storage_warning_at);
            $this->assertNull($letter->user->fresh()->storage_cleanup_due_at);
        }
        Notification::fake();
        $this->assertSame('warned', app(StorageAllowanceManager::class)->process($letter->user->fresh())['status']);
        Notification::assertSentTo($letter->user, StorageLimitWarning::class);
    }

    public function test_failed_cleanup_preserves_file_references_and_never_reports_unverified_freed_space(): void
    {
        config(['dearyou.storage_limit_mb' => 1]);
        Notification::fake();
        $letter = $this->letter();
        $letter->update(['expires_at' => now()->subDay()]);
        $letter->user->update(['storage_warning_at' => now()->subDays(8), 'storage_cleanup_due_at' => now()->subDay()]);
        Storage::disk('public')->put($letter->image_path, str_repeat('x', 1024 * 1024 + 1));
        $disk = Storage::disk('public');
        $failing = \Mockery::mock($disk)->makePartial();
        $failing->shouldReceive('delete')->andReturn(false);
        Storage::set('public', $failing);
        $result = app(StorageAllowanceManager::class)->process($letter->user->fresh());
        $this->assertSame('still-over-limit', $result['status']);
        $this->assertSame(0, $result['bytes_freed']);
        $this->assertSame($letter->image_path, $letter->fresh()->image_path);
        $this->assertDatabaseCount('storage_cleanup_logs', 0);
        $this->assertDatabaseCount('media_deletions', 0);
        Storage::set('public', $disk);
        $letter->update(['expires_at' => now()->addHour()]);
        $this->artisan('dearyou:retry-media-deletions')->assertSuccessful();
        app(StorageAllowanceManager::class)->process($letter->user->fresh());
        Storage::disk('public')->assertExists($letter->image_path);
    }

    public function test_undelivered_storage_warning_retries_without_starting_cleanup_grace(): void
    {
        config(['dearyou.storage_limit_mb' => 1, 'mail.default' => 'smtp']);
        Notification::fake();
        $letter = $this->letter();
        Storage::disk('public')->put($letter->image_path, str_repeat('x', 1024 * 1024 + 1));
        $manager = app(StorageAllowanceManager::class);
        $manager->process($letter->user);
        $first = Notification::sent($letter->user, StorageLimitWarning::class)->last();
        $this->assertSame('warning-pending', $manager->process($letter->user->fresh())['status']);
        $this->travel(2)->days();
        $this->assertSame('warned', $manager->process($letter->user->fresh())['status']);
        $this->assertNull($letter->user->fresh()->storage_warning_at);
        $this->assertNull($letter->user->fresh()->storage_cleanup_due_at);
        $this->assertFalse($first->shouldSend($letter->user, 'mail'));
        $latest = Notification::sent($letter->user, StorageLimitWarning::class)->last();
        $this->assertTrue($latest->shouldSend($letter->user, 'mail'));
        event(new NotificationSent($letter->user, $first, 'mail', $this->acceptedMailResponse()));
        $this->assertNull($letter->user->fresh()->storage_warning_at);
        event(new NotificationSent($letter->user, $latest, 'mail', $this->acceptedMailResponse()));
        $this->assertTrue($letter->user->fresh()->storage_cleanup_due_at->equalTo(now()->addDays(7)->startOfSecond()));
        Storage::disk('public')->assertExists($letter->image_path);
    }

    public function test_real_notification_starts_grace_only_after_mail_transport_accepts_it(): void
    {
        config(['dearyou.storage_limit_mb' => 1, 'queue.default' => 'sync', 'mail.default' => 'smtp']);
        app('mail.manager')->mailer('smtp')->setSymfonyTransport(new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void {}

            public function __toString(): string
            {
                return 'synthetic-mail-handoff';
            }
        });
        $letter = $this->letter();
        Storage::disk('public')->put($letter->image_path, str_repeat('x', 1024 * 1024 + 1));
        app(StorageAllowanceManager::class)->process($letter->user);
        $user = $letter->user->fresh();
        $this->assertNotNull($user->storage_warning_at);
        $this->assertNull($user->storage_warning_queued_at);
        $this->assertTrue($user->storage_cleanup_due_at->equalTo(now()->addDays(7)->startOfSecond()));
    }

    public function test_canceled_or_non_delivering_mail_cannot_start_storage_cleanup_grace(): void
    {
        config(['dearyou.storage_limit_mb' => 1, 'mail.default' => 'smtp']);
        Notification::fake();
        $letter = $this->letter();
        Storage::disk('public')->put($letter->image_path, str_repeat('x', 1024 * 1024 + 1));
        app(StorageAllowanceManager::class)->process($letter->user);
        $warning = Notification::sent($letter->user, StorageLimitWarning::class)->last();
        event(new NotificationSent($letter->user, $warning, 'mail', null));
        $this->assertNull($letter->user->fresh()->storage_cleanup_due_at);
        foreach (['array', 'log', 'failover'] as $mailer) {
            config(['mail.default' => $mailer]);
            $this->assertFalse($warning->shouldSend($letter->user, 'mail'));
            event(new NotificationSent($letter->user, $warning, 'mail', $this->acceptedMailResponse()));
            $this->assertNull($letter->user->fresh()->storage_cleanup_due_at);
        }
    }

    public function test_cleanup_notice_failure_does_not_undo_verified_cleanup_records(): void
    {
        config(['dearyou.storage_limit_mb' => 1]);
        $letter = $this->letter();
        $letter->update(['expires_at' => now()->subDay()]);
        $letter->user->update(['storage_warning_at' => now()->subDays(8), 'storage_cleanup_due_at' => now()->subDay()]);
        Storage::disk('public')->put($letter->image_path, str_repeat('x', 1024 * 1024 + 1));
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Synthetic completion enqueue failure'));
        $this->assertSame('cleaned', app(StorageAllowanceManager::class)->process($letter->user->fresh())['status']);
        $this->assertNull($letter->fresh()->image_path);
        $this->assertDatabaseHas('storage_cleanup_logs', ['letter_id' => $letter->id, 'bytes_freed' => 1024 * 1024 + 1]);
        Storage::disk('public')->assertMissing($letter->image_path);
    }

    public function test_keepsake_limits_bytes_and_files_but_text_is_still_available(): void
    {
        config(['dearyou.keepsake_max_media_bytes' => 5]);
        $letter = $this->letter();
        $base = '/l/'.$letter->link->token.'/download';
        $this->get($base.'?format=html')->assertStatus(413)->assertSee('Download letter text');
        $this->get($base)->assertOk()->assertSee('Hello');
        config(['dearyou.keepsake_max_media_bytes' => 100, 'dearyou.keepsake_max_media_files' => 1]);
        $memory = $letter->memories()->create(['title' => 'Memory']);
        $memory->images()->create(['image_path' => 'letters/memories/two.png']);
        $this->get($base.'?format=html')->assertStatus(413);
    }

    public function test_keepsake_generation_is_rate_limited(): void
    {
        $letter = $this->letter();
        for ($i = 0; $i < 6; $i++) {
            $this->get('/l/'.$letter->link->token.'/download')->assertOk();
        }
        $this->get('/l/'.$letter->link->token.'/download')->assertStatus(429);
    }

    public function test_secure_media_is_dry_run_by_default_and_only_removes_verified_copies(): void
    {
        $letter = $this->letter();
        Storage::disk('public')->delete($letter->image_path);
        Storage::disk('legacy_media')->put($letter->image_path, '0123456789');
        $this->artisan('dearyou:secure-media')->assertSuccessful();
        Storage::disk('public')->assertMissing($letter->image_path);
        Storage::disk('legacy_media')->assertExists($letter->image_path);
        $this->artisan('dearyou:secure-media --apply --remove-public')->assertSuccessful();
        $this->assertSame('0123456789', Storage::disk('public')->get($letter->image_path));
        Storage::disk('legacy_media')->assertMissing($letter->image_path);
        $this->artisan('dearyou:secure-media --apply --remove-public')->assertSuccessful();
    }

    public function test_secure_media_preserves_both_files_when_checksums_conflict(): void
    {
        $letter = $this->letter();
        Storage::disk('legacy_media')->put($letter->image_path, 'different content');
        $this->artisan('dearyou:secure-media --apply --remove-public')->assertFailed();
        $this->assertSame('different content', Storage::disk('legacy_media')->get($letter->image_path));
        $this->assertSame('0123456789', Storage::disk('public')->get($letter->image_path));
    }

    public function test_memory_move_swaps_the_adjacent_item_without_skipping(): void
    {
        $letter = $this->letter();
        $memories = collect(range(0, 3))->map(fn ($order) => $letter->memories()->create(['title' => 'Memory '.$order, 'sort_order' => $order]));
        $this->actingAs($letter->user)->patch('/memories/'.$memories[2]->id.'/move/up')->assertRedirect();
        $this->assertSame([$memories[0]->id, $memories[2]->id, $memories[1]->id, $memories[3]->id], $letter->memories()->pluck('id')->all());
    }

    public function test_permanent_delete_rejects_a_target_restored_since_it_was_loaded(): void
    {
        $letter = $this->letter();
        $user = $letter->user;
        $user->delete();
        $snapshot = $user->fresh();
        $user->restore();
        try {
            app(AccountDeletion::class)->permanentlyDelete($snapshot, mustBeTrashed: true);
            $this->fail('Restored account must survive a stale purge request.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
            Storage::disk('public')->assertExists($letter->image_path);
        }
    }

    private function letter(): Letter
    {
        $letter = User::factory()->create()->letters()->create($this->payload() + [
            'status' => 'published', 'published_at' => now(), 'expires_at' => now()->addHour(),
            'image_path' => 'letters/'.bin2hex(random_bytes(8)).'.png',
        ]);
        $letter->link()->create(['token' => bin2hex(random_bytes(32)), 'is_active' => true]);
        Storage::disk('public')->put($letter->image_path, '0123456789');

        return $letter;
    }

    private function payload(): array
    {
        return ['category' => 'anniversary', 'title' => 'A letter', 'body' => 'Hello', 'theme' => 'warm', 'primary_color' => '#d85b78', 'secondary_color' => '#fff1e8', 'decoration_type' => 'hearts', 'response_mode' => 'message'];
    }

    private function publicUrl(Letter $letter): string
    {
        return '/l/'.$letter->link->token.'/media/'.hash('sha256', $letter->image_path);
    }
}
