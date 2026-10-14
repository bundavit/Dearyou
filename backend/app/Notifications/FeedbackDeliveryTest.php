<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FeedbackDeliveryTest extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly string $marker) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('DearYou feedback delivery test - '.$this->marker)
            ->greeting('DearYou email delivery test')
            ->line('An administrator requested this test for the configured feedback inbox.')
            ->line('This message uses the application notification queue and configured mail transport.')
            ->line('Environment: '.app()->environment())
            ->line('Reference: '.$this->marker)
            ->line('No feedback record or personal letter content was included.');
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
