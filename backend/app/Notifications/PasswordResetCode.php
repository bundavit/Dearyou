<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCode extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [10, 60, 180];

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your DearYou password reset code')
            ->greeting('Reset your DearYou password')
            ->line('Enter this six-digit code to continue:')
            ->line("**{$this->code}**")
            ->line('This code expires in 10 minutes and can only be used once.')
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
