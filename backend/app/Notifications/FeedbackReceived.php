<?php

namespace App\Notifications;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly Feedback $feedback) {}

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

    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
