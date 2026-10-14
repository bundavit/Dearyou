<?php

namespace App\Notifications;

use App\Models\Feedback;
use App\Support\FeedbackEmailPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public ?Feedback $feedback = null;

    public ?int $feedbackId = null;

    public function __construct(Feedback $feedback)
    {
        $this->feedbackId = (int) $feedback->getKey();
    }

    public function __unserialize(array $values): void
    {
        // Old queue payloads stored a model. Normalize them before Laravel tries
        // restoring it, so deleted feedback can be skipped without a failed job.
        if (($values['feedback'] ?? null) instanceof ModelIdentifier) {
            $legacy = $values['feedback'];
            $id = is_int($legacy->id) || is_string($legacy->id)
                ? filter_var($legacy->id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            $values['feedbackId'] = $legacy->getClass() === Feedback::class && $id !== false ? $id : null;
            unset($values['feedback']);
        }
        parent::__unserialize($values);
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $category = Feedback::CATEGORIES[$this->feedback->category] ?? ucfirst((string) $this->feedback->category);
        $rating = $this->feedback->rating ? "{$this->feedback->rating} / 5" : 'No rating';
        $sender = $this->feedback->email ?: ($this->feedback->user?->email ?? 'Not provided');

        return (new MailMessage)
            ->subject("New DearYou feedback: {$category}")
            ->greeting('New DearYou feedback')
            ->line("Category: {$category}")
            ->line("Rating: {$rating}")
            ->line("From: {$sender}")
            ->line('Message:')
            ->line($this->feedback->message)
            ->action('Review feedback', route('admin.feedback.show', $this->feedback))
            ->line('This message was sent automatically from DearYou.');
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $recipient = $notifiable->routeNotificationFor('mail');
        if ($channel !== 'mail' || ! is_string($recipient) || ! $this->feedbackId) {
            return false;
        }
        $this->feedback = app(FeedbackEmailPolicy::class)->eligibleFeedback($this->feedbackId, $recipient);

        return $this->feedback !== null;
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
