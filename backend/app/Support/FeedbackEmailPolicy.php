<?php

namespace App\Support;

use App\Models\Feedback;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FeedbackEmailPolicy
{
    public function eligibleFeedback(int $feedbackId, string $recipient): ?Feedback
    {
        try {
            $feedback = Feedback::query()->find($feedbackId);
            $configuredRecipient = app(PlatformSettings::class)->feedbackNotifyEmail();
            $maxAgeHours = max(0, (int) config('dearyou.feedback_email_max_age_hours', 24));

            if (! $feedback || $feedback->status !== 'new' || blank($configuredRecipient)
                || strcasecmp(trim($recipient), trim($configuredRecipient)) !== 0
                || ! $feedback->created_at || $maxAgeHours === 0
                || $feedback->created_at->lessThan(now()->subHours($maxAgeHours))
                || ($feedback->spam_reasons === null
                    && app(FeedbackSpamDetector::class)->inspect($feedback->message, $feedbackId)['flagged'])) {
                return null;
            }

            return $this->reserve($feedback, $recipient, $maxAgeHours) ? $feedback : null;
        } catch (Throwable $exception) {
            // Never include the submission, recipient, credentials or exception message.
            Log::warning('Feedback email skipped because its delivery policy could not be checked.', [
                'feedback_id' => $feedbackId,
                'exception_type' => $exception::class,
            ]);

            return null;
        }
    }

    private function reserve(Feedback $feedback, string $recipient, int $maxAgeHours): bool
    {
        $hourlyLimit = max(0, (int) config('dearyou.feedback_email_hourly_limit', 3));
        $dailyLimit = max(0, (int) config('dearyou.feedback_email_daily_limit', 5));
        if ($hourlyLimit === 0 || $dailyLimit === 0) {
            return false;
        }

        $cache = Cache::store();
        $store = $cache->getStore();
        if (! $store instanceof LockProvider
            || ($store instanceof ArrayStore && ! app()->environment(['local', 'testing']))) {
            throw new RuntimeException('A shared cache with atomic locks is required.');
        }

        return $store->lock('feedback-email:budget-lock', 10)->block(1, function () use ($cache, $feedback, $recipient, $maxAgeHours, $hourlyLimit, $dailyLimit): bool {
            $now = now()->utc();
            $state = $cache->get('feedback-email:budget', []);
            if (! is_array($state)) {
                throw new RuntimeException('The feedback budget could not be read.');
            }
            $hour = $now->format('Y-m-d-H');
            $day = $now->format('Y-m-d');
            $hourCount = ($state['hour'] ?? null) === $hour ? (int) ($state['hour_count'] ?? 0) : 0;
            $dayCount = ($state['day'] ?? null) === $day ? (int) ($state['day_count'] ?? 0) : 0;
            $reservations = array_filter($state['reservations'] ?? [], fn ($expiresAt) => $expiresAt >= $now->timestamp);
            $reservation = hash('sha256', $feedback->id.'|'.strtolower(trim($recipient)));

            // A transport retry reuses the same reservation instead of consuming another slot.
            if (isset($reservations[$reservation])) {
                return true;
            }
            if ($hourCount >= $hourlyLimit || $dayCount >= $dailyLimit) {
                return false;
            }
            $reservations[$reservation] = $feedback->created_at->copy()->addHours($maxAgeHours)->timestamp;
            $saved = $cache->put('feedback-email:budget', [
                'hour' => $hour, 'hour_count' => $hourCount + 1,
                'day' => $day, 'day_count' => $dayCount + 1,
                'reservations' => $reservations,
            ], max(86400, $maxAgeHours * 3600));
            if (! $saved) {
                throw new RuntimeException('The feedback budget could not be saved.');
            }

            return true;
        });
    }
}
