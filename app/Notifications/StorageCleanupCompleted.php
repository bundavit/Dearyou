<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StorageCleanupCompleted extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $freed,
        private readonly int $lettersAffected,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $manageUrl = $notifiable->isAdmin()
            ? route('admin.letters.index')
            : route('letters.index');

        return (new MailMessage)
            ->subject('DearYou storage cleanup completed')
            ->greeting("Hello {$notifiable->name},")
            ->line("DearYou removed {$this->freed} of media from {$this->lettersAffected} expired letter(s) after the storage grace period ended.")
            ->line('Your letter text and recipient responses were preserved.')
            ->action('Review my letters', $manageUrl);
    }
}
