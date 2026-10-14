<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackReceived;
use App\Support\PlatformSettings;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeedbackEmailProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'queue.default' => 'database',
            'cache.default' => 'database',
            'mail.default' => 'array',
            'mail.from.address' => 'qa@dearyou.test',
            'dearyou.feedback_email_hourly_limit' => 3,
            'dearyou.feedback_email_daily_limit' => 5,
            'dearyou.feedback_email_max_age_hours' => 24,
        ]);
        app(PlatformSettings::class)->update(['feedback_notify_email' => 'feedback@example.test']);
    }

    private function feedback(array $attributes = []): Feedback
    {
        return Feedback::create([
            'category' => 'bug',
            'message' => 'Synthetic feedback report number '.(Feedback::count() + 1),
            'ip_hash' => hash('sha256', 'guest-'.(Feedback::count() + 1)),
            ...$attributes,
        ]);
    }

    private function queueFeedback(Feedback $feedback, bool $legacy = false, string $legacyModelClass = Feedback::class): void
    {
        Notification::route('mail', 'feedback@example.test')->notify(new FeedbackReceived($feedback));
        if ($legacy) {
            // Represent the real pre-update payload, which serialized a Feedback model.
            $job = DB::table('jobs')->latest('id')->first();
            $payload = json_decode($job->payload, true);
            $before = 's:10:"feedbackId";i:'.$feedback->id.';';
            $after = 's:8:"feedback";'.serialize(new ModelIdentifier($legacyModelClass, $feedback->id, [], $feedback->getConnectionName()));
            $this->assertStringContainsString($before, $payload['data']['command']);
            $payload['data']['command'] = str_replace($before, $after, $payload['data']['command']);
            DB::table('jobs')->where('id', $job->id)->update(['payload' => json_encode($payload)]);
        }
    }

    private function runOneJob(int $tries = 1): void
    {
        app('queue.worker')->runNextJob('database', 'default', new WorkerOptions(sleep: 0, maxTries: $tries));
    }

    private function drain(): void
    {
        $count = DB::table('jobs')->count();
        for ($i = 0; $i < $count; $i++) {
            $this->runOneJob();
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    private function messages()
    {
        // The array transport records messages in memory and never sends email.
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }

    public function test_legacy_queued_feedback_is_checked_against_current_record_and_recipient(): void
    {
        $feedback = $this->feedback(['message' => 'Original report before this edit.']);
        $this->queueFeedback($feedback, legacy: true);
        $feedback->update(['message' => 'The current report is loaded at delivery.']);
        $this->drain();
        $this->assertCount(1, $this->messages());
        $mail = $this->messages()->first()->getOriginalMessage();
        $this->assertSame('feedback@example.test', $mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('The current report is loaded at delivery.', $mail->getTextBody());
        $this->assertStringNotContainsString('Original report before this edit.', $mail->getTextBody());
    }

    public function test_muting_feedback_stops_an_already_queued_notification_and_keeps_the_record(): void
    {
        $this->queueFeedback($this->feedback(), legacy: true);
        app(PlatformSettings::class)->update(['feedback_notify_email' => '']);
        $this->drain();
        $this->assertCount(0, $this->messages());
        $this->assertDatabaseCount('feedback', 1);
        // Re-enabling alerts cannot resurrect a skipped queue job.
        app(PlatformSettings::class)->update(['feedback_notify_email' => 'feedback@example.test']);
        $this->drain();
        $this->assertCount(0, $this->messages());
    }

    public function test_changing_the_recipient_does_not_send_old_jobs_to_the_previous_inbox(): void
    {
        $this->queueFeedback($this->feedback());
        app(PlatformSettings::class)->update(['feedback_notify_email' => 'replacement@example.test']);
        $this->drain();
        $this->assertCount(0, $this->messages());
        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_worker_skips_reviewed_resolved_spam_and_deleted_legacy_records(): void
    {
        foreach (['reviewed', 'resolved', 'spam', 'deleted'] as $state) {
            $feedback = $this->feedback();
            $this->queueFeedback($feedback, legacy: true);
            $state === 'deleted' ? $feedback->delete() : $feedback->update(['status' => $state]);
        }
        $this->drain();
        $this->assertCount(0, $this->messages());
        $this->assertDatabaseCount('feedback', 3);
    }

    public function test_worker_skips_old_backlog_and_detects_pre_update_spam(): void
    {
        $this->queueFeedback($this->feedback(['created_at' => now()->subHours(25)]), legacy: true);
        $this->queueFeedback($this->feedback(['message' => 'Buy now! Limited time offer https://one.example/ https://two.example/']), legacy: true);
        $this->drain();
        $this->assertCount(0, $this->messages());
        $this->assertDatabaseCount('feedback', 2);
    }

    public function test_restoring_a_false_positive_is_respected_at_delivery(): void
    {
        $feedback = $this->feedback([
            'message' => 'Buy now! Limited time offer https://one.example/ https://two.example/',
            'status' => 'spam', 'spam_score' => 80, 'spam_reasons' => ['An initial spam signal.'],
        ]);
        $this->queueFeedback($feedback, legacy: true);
        // The administrator's restoration takes precedence over automated detection.
        $feedback->update(['status' => 'new', 'spam_score' => 0, 'spam_reasons' => []]);
        $this->drain();
        $this->assertCount(1, $this->messages());
        $this->assertSame([], $feedback->fresh()->spam_reasons);
    }

    public function test_an_unexpected_legacy_model_identifier_cannot_reference_a_feedback_row(): void
    {
        $this->queueFeedback($this->feedback(), legacy: true, legacyModelClass: User::class);
        $this->drain();
        $this->assertCount(0, $this->messages());
        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_shared_hourly_budget_limits_distinct_guests_and_preserves_verification_delivery(): void
    {
        foreach (range(1, 8) as $i) {
            $this->queueFeedback($this->feedback());
        }
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $this->drain();
        $subjects = $this->messages()->map(fn ($mail) => $mail->getOriginalMessage()->getSubject());
        $this->assertSame(3, $subjects->filter(fn ($subject) => str_starts_with($subject, 'New DearYou feedback'))->count());
        $this->assertContains('Your DearYou verification code', $subjects);
        $this->assertDatabaseCount('feedback', 8);
    }

    public function test_daily_budget_survives_new_hours_and_resets_on_a_new_utc_day(): void
    {
        $this->travelTo(now()->utc()->startOfDay()->addHours(10));
        foreach (range(1, 3) as $i) {
            $this->queueFeedback($this->feedback());
        }
        $this->drain();
        $this->travel(61)->minutes();
        foreach (range(1, 3) as $i) {
            $this->queueFeedback($this->feedback());
        }
        $this->drain();
        $this->assertCount(5, $this->messages());
        User::factory()->unverified()->create()->sendEmailVerificationNotification();
        $this->drain();
        $this->assertCount(6, $this->messages());
        $this->assertSame('Your DearYou verification code', $this->messages()->last()->getOriginalMessage()->getSubject());
        $this->travelTo(now()->utc()->addDay()->startOfDay());
        $this->queueFeedback($this->feedback());
        $this->drain();
        $this->assertCount(7, $this->messages());
        $this->assertDatabaseCount('feedback', 7);
    }

    public function test_transport_retry_reuses_its_budget_reservation(): void
    {
        config(['dearyou.feedback_email_hourly_limit' => 1, 'dearyou.feedback_email_daily_limit' => 1,
            'mail.default' => 'broken', 'mail.mailers.broken.transport' => 'unsupported-test-transport']);
        $this->queueFeedback($this->feedback());
        $this->runOneJob(tries: 3);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->travel(16)->seconds();
        config(['mail.default' => 'array']);
        $this->runOneJob(tries: 3);
        $this->assertCount(1, $this->messages());
        $this->queueFeedback($this->feedback());
        $this->drain();
        $this->assertCount(1, $this->messages());
        $this->assertDatabaseCount('feedback', 2);
    }

    public function test_policy_cache_failure_skips_email_without_failed_jobs_or_sensitive_logs(): void
    {
        config(['cache.stores.database.lock_table' => 'missing_policy_locks']);
        $feedback = $this->feedback(['message' => 'PRIVATE_REPORT_ABC', 'email' => 'private@example.test']);
        $this->queueFeedback($feedback);
        Log::shouldReceive('warning')->once()->with(
            'Feedback email skipped because its delivery policy could not be checked.',
            \Mockery::on(fn ($context) => array_keys($context) === ['feedback_id', 'exception_type']
                && $context['feedback_id'] === $feedback->id && is_string($context['exception_type'])),
        );
        $this->drain();
        $this->assertCount(0, $this->messages());
        $this->assertDatabaseCount('feedback', 1);
    }

    public function test_lock_contention_never_bypasses_the_budget(): void
    {
        $lock = Cache::store()->getStore()->lock('feedback-email:budget-lock', 10);
        $this->assertTrue($lock->get());
        try {
            $this->queueFeedback($this->feedback());
            $this->drain();
            $this->assertCount(0, $this->messages());
        } finally {
            $lock->release();
        }
        $this->assertDatabaseCount('feedback', 1);
    }
}
