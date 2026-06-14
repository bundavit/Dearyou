<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StorageLimitWarning extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $used,
        private readonly string $limit,
        private readonly int $graceDays,
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
            ->subject('Your DearYou media storage is over its allowance')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your DearYou account is using {$this->used} of its {$this->limit} media allowance.")
            ->line("Please remove some media within {$this->graceDays} days.")
            ->line('If the account remains over the limit, DearYou will remove media from the oldest expired letters first. Letter text and recipient responses are never removed.')
            ->action('Manage my letters', $manageUrl);
    }
}
