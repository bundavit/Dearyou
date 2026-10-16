<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetCode;
use App\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordRecoverySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'queue.default' => 'sync', 'logging.default' => 'null']);
        Http::preventStrayRequests();
    }

    public function test_registration_canonicalizes_email_and_supports_verification_login_and_recovery(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Synthetic Writer', 'email' => 'Case.Writer@EXAMPLE.TEST',
            'password' => 'OriginalPassword1', 'password_confirmation' => 'OriginalPassword1',
        ])->assertRedirect('/verify-email');

        $user = User::where('email', 'case.writer@example.test')->sole();
        $verification = Notification::sent($user, VerifyEmail::class)->sole();
        $this->post('/verify-email', ['code' => $verification->code])->assertRedirect('/letters');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->post('/logout')->assertRedirect('/');

        $this->post('/login', ['email' => 'CASE.WRITER@example.test', 'password' => 'OriginalPassword1'])
            ->assertRedirect('/letters');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');

        $this->post('/forgot-password', ['email' => 'CASE.WRITER@Example.Test'])->assertRedirect('/forgot-password/code');
        $notification = Notification::sent($user, PasswordResetCode::class)->sole();
        $this->post('/forgot-password/code', ['code' => $notification->code])->assertRedirect('/reset-password');
        $this->post('/reset-password', $this->newPassword())->assertRedirect('/login');
        $this->assertTrue(Hash::check('ReplacementPassword2', $user->fresh()->password));
    }

    public function test_historical_case_sensitive_email_is_found_without_rewriting_it(): void
    {
        Notification::fake();
        $user = $this->historicalUser('Legacy.Writer@EXAMPLE.TEST');

        $this->post('/login', ['email' => 'legacy.writer@example.test', 'password' => 'OriginalPassword1'])
            ->assertRedirect('/letters');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');

        $this->post('/forgot-password', ['email' => 'LEGACY.WRITER@example.test'])->assertRedirect('/forgot-password/code');
        $notification = Notification::sent($user, PasswordResetCode::class)->sole();
        $this->assertDatabaseHas('password_reset_codes', ['email' => 'Legacy.Writer@EXAMPLE.TEST']);
        $this->post('/forgot-password/code', ['code' => $notification->code])->assertRedirect('/reset-password');
        $this->post('/reset-password', $this->newPassword())->assertRedirect('/login');
        $this->assertSame('Legacy.Writer@EXAMPLE.TEST', $user->fresh()->email);
        $this->assertTrue(Hash::check('ReplacementPassword2', $user->fresh()->password));
    }

    #[DataProvider('reservedAccountStates')]
    public function test_registration_reserves_historical_identity_including_deleted_accounts(bool $deleted): void
    {
        Notification::fake();
        $user = $this->historicalUser('Reserved.Writer@EXAMPLE.TEST');
        if ($deleted) {
            $user->delete();
        }

        $this->post('/register', [
            'name' => 'Duplicate Writer', 'email' => 'reserved.writer@example.test',
            'password' => 'OriginalPassword1', 'password_confirmation' => 'OriginalPassword1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public static function reservedAccountStates(): array
    {
        return ['active' => [false], 'soft deleted' => [true]];
    }

    #[DataProvider('reservedAccountStates')]
    public function test_ambiguous_historical_identity_never_selects_an_account(bool $deleted): void
    {
        Notification::fake();
        $this->historicalUser('Ambiguous.Writer@EXAMPLE.TEST');
        $duplicate = $this->historicalUser('ambiguous.writer@example.test');
        if ($deleted) {
            $duplicate->delete();
        }

        $this->post('/login', ['email' => 'AMBIGUOUS.WRITER@example.test', 'password' => 'OriginalPassword1'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/forgot-password', ['email' => 'ambiguous.writer@example.test'])
            ->assertRedirect('/forgot-password/code')->assertSessionHas('status');
        $this->assertDatabaseCount('password_reset_codes', 0);
        Notification::assertNothingSent();
    }

    public function test_profile_case_only_edit_keeps_verification_and_canonicalizes_the_assigned_email(): void
    {
        Notification::fake();
        $user = $this->historicalUser('Profile.Writer@EXAMPLE.TEST');
        $verifiedAt = $user->email_verified_at->toDateTimeString();

        $this->actingAs($user)->from('/account')->put('/account/profile', [
            'name' => 'Profile Writer', 'email' => 'PROFILE.WRITER@example.test', 'current_password' => 'OriginalPassword1',
        ])->assertRedirect('/account')->assertSessionHasNoErrors();

        $this->assertSame('profile.writer@example.test', $user->fresh()->email);
        $this->assertSame($verifiedAt, $user->fresh()->email_verified_at->toDateTimeString());
        Notification::assertNothingSent();
    }

    public function test_profile_rejects_another_deleted_historical_identity(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => 'OriginalPassword1']);
        $reserved = $this->historicalUser('Reserved.Profile@EXAMPLE.TEST');
        $reserved->delete();

        $this->actingAs($user)->put('/account/profile', [
            'name' => $user->name, 'email' => 'reserved.profile@example.test', 'current_password' => 'OriginalPassword1',
        ])->assertSessionHasErrorsIn('profile', 'email');

        $this->assertSame($user->email, $user->fresh()->email);
        Notification::assertNothingSent();
    }

    #[DataProvider('queueFailureConnections')]
    public function test_enqueue_failure_preserves_previous_valid_code_and_returns_neutral_recoverable_feedback(bool $external): void
    {
        $this->configureFailingQueue($external);
        $user = User::factory()->create(['email' => 'queue-failure@example.test']);
        $this->resetRecord($user, '123456', 2);
        $previous = DB::table('password_reset_codes')->where('email', $user->email)->first();

        $this->post('/forgot-password', ['email' => 'QUEUE-FAILURE@EXAMPLE.TEST'])
            ->assertRedirect('/forgot-password/code')->assertSessionHas('password_reset_email', $user->email)
            ->assertSessionHas('status');
        $failedStatus = session('status');

        $this->assertEquals($previous, DB::table('password_reset_codes')->where('email', $user->email)->first());
        $this->post('/forgot-password', ['email' => 'missing@example.test'])
            ->assertRedirect('/forgot-password/code')->assertSessionHas('status', $failedStatus);
        $disabled = User::factory()->create(['email' => 'disabled@example.test', 'disabled_at' => now()]);
        $this->post('/forgot-password', ['email' => $disabled->email])
            ->assertRedirect('/forgot-password/code')->assertSessionHas('status', $failedStatus);
        $this->assertStringContainsString('try requesting a new code', $failedStatus);
        $this->withSession(['password_reset_email' => $user->email])
            ->post('/forgot-password/code', ['code' => '123456'])->assertRedirect('/reset-password');
    }

    public static function queueFailureConnections(): array
    {
        return ['same database transaction' => [false], 'external queue after commit' => [true]];
    }

    public function test_first_enqueue_failure_leaves_no_unusable_code_or_old_authorization(): void
    {
        $this->configureFailingQueue(false);
        $user = User::factory()->create(['email' => 'first-failure@example.test']);

        $this->withSession(['password_reset_authorized' => ['email' => $user->email]])
            ->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect('/forgot-password/code')->assertSessionHas('password_reset_email', $user->email)
            ->assertSessionMissing('password_reset_authorized');
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    #[DataProvider('staleNotificationStates')]
    public function test_worker_drops_stale_or_ineligible_password_reset_notifications(string $state): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create(['email' => 'delayed-code@example.test']);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password/code');
        $this->assertDatabaseCount('jobs', 1);

        match ($state) {
            'expired' => $this->travelTo(DB::table('password_reset_codes')->value('expires_at')),
            'superseded' => DB::table('password_reset_codes')->where('email', $user->email)->update(['code' => Hash::make('654321')]),
            'consumed' => DB::table('password_reset_codes')->where('email', $user->email)->delete(),
            'attempts exhausted' => DB::table('password_reset_codes')->where('email', $user->email)->update(['attempts' => 5]),
            'disabled' => $user->update(['disabled_at' => now()]),
            'deleted' => $user->delete(),
            'email changed' => $user->update(['email' => 'replacement@example.test']),
        };

        $this->runNotificationJob();
        $this->assertCount(0, $this->mailMessages());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public static function staleNotificationStates(): array
    {
        return array_combine(
            ['expired', 'superseded', 'consumed', 'attempts exhausted', 'disabled', 'deleted', 'email changed'],
            array_map(fn ($state) => [$state], ['expired', 'superseded', 'consumed', 'attempts exhausted', 'disabled', 'deleted', 'email changed']),
        );
    }

    public function test_current_notification_delivers_and_resending_only_delivers_the_newest_issuance(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create(['email' => 'current-code@example.test']);
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password/code');
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password/code');
        $this->assertDatabaseCount('jobs', 2);

        $this->runNotificationJob();
        $this->assertCount(0, $this->mailMessages());
        $this->runNotificationJob();
        $this->assertCount(1, $this->mailMessages());
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    public function test_reissued_identical_digits_still_invalidate_the_old_notification(): void
    {
        $user = User::factory()->create();
        $this->resetRecord($user, '123456');
        $notification = new PasswordResetCode('123456', $user->email, DB::table('password_reset_codes')->value('code'));
        DB::table('password_reset_codes')->where('email', $user->email)->update(['code' => Hash::make('123456')]);

        $this->assertFalse($notification->shouldSend($user, 'mail'));
    }

    public function test_correct_code_is_denied_at_the_exact_expiry_instant(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $this->resetRecord($user, '123456');
        $this->travelTo(now()->addMinutes(10));

        $this->withSession(['password_reset_email' => $user->email])
            ->post('/forgot-password/code', ['code' => '123456'])->assertSessionHasErrors('code')
            ->assertSessionMissing('password_reset_authorized');
        $this->assertDatabaseMissing('password_reset_codes', ['email' => $user->email]);
    }

    public function test_consumed_code_cannot_grant_a_second_authorization(): void
    {
        $user = User::factory()->create();
        $this->resetRecord($user, '123456');
        $this->authorize($user);
        $this->withSession(['password_reset_email' => $user->email])
            ->post('/forgot-password/code', ['code' => '123456'])->assertSessionHasErrors('code')
            ->assertSessionMissing('password_reset_authorized');
    }

    public function test_reset_authorization_expires_at_the_exact_boundary(): void
    {
        $user = User::factory()->create(['password' => 'OriginalPassword1']);
        $this->resetRecord($user, '123456');
        $this->authorize($user);
        $this->travelTo(now()->setTimestamp(session('password_reset_authorized.expires_at')));

        $this->get('/reset-password')->assertRedirect('/forgot-password');
        $this->post('/reset-password', $this->newPassword())->assertRedirect('/forgot-password');
        $this->assertTrue(Hash::check('OriginalPassword1', $user->fresh()->password));
    }

    public function test_completed_reset_rejects_a_replayed_copy_of_the_authorization(): void
    {
        $user = User::factory()->create(['password' => 'OriginalPassword1']);
        $this->resetRecord($user, '123456');
        $this->authorize($user);
        $authorization = session('password_reset_authorized');
        $this->post('/reset-password', $this->newPassword())->assertRedirect('/login');

        $this->withSession(['password_reset_authorized' => $authorization])
            ->post('/reset-password', ['password' => 'ReplayedPassword3', 'password_confirmation' => 'ReplayedPassword3'])
            ->assertRedirect('/forgot-password')->assertSessionMissing('password_reset_authorized');
        $this->assertTrue(Hash::check('ReplacementPassword2', $user->fresh()->password));
    }

    private function historicalUser(string $email): User
    {
        $user = User::factory()->create(['password' => 'OriginalPassword1']);
        DB::table('users')->where('id', $user->id)->update(['email' => $email]);

        return $user->refresh();
    }

    private function resetRecord(User $user, string $code, int $attempts = 0): void
    {
        DB::table('password_reset_codes')->insert([
            'email' => $user->email, 'code' => Hash::make($code), 'attempts' => $attempts,
            'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function authorize(User $user): void
    {
        $this->withSession(['password_reset_email' => $user->email])
            ->post('/forgot-password/code', ['code' => '123456'])->assertRedirect('/reset-password');
    }

    private function newPassword(): array
    {
        return ['password' => 'ReplacementPassword2', 'password_confirmation' => 'ReplacementPassword2'];
    }

    private function configureFailingQueue(bool $external): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'synthetic_missing_jobs']);
        if ($external) {
            // A separate in-memory database models a queue outside the code transaction.
            config([
                'database.connections.synthetic_queue' => config('database.connections.sqlite'),
                'queue.connections.database.connection' => 'synthetic_queue',
            ]);
        }
    }

    private function runNotificationJob(): void
    {
        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions(sleep: 0, maxTries: 1));
    }

    private function mailMessages(): mixed
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }
}
