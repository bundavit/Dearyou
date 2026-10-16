<?php

namespace App\Notifications;

use App\Support\WarningMailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class StorageLimitWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [15, 60, 180];

    public function __construct(
        private readonly string $used,
        private readonly string $limit,
        private readonly int $graceDays,
        private readonly ?string $issuance = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $user = $notifiable->fresh();

        return WarningMailDelivery::configured() && $this->issuance !== null && $user && ! $user->trashed() && ! $user->disabled_at
            && $user->storage_warning_token === $this->issuance && ! $user->storage_warning_at;
    }

    public function recordDelivery(object $notifiable): void
    {
        if ($this->issuance !== null) {
            DB::table('users')->where('id', $notifiable->id)
                ->where('storage_warning_token', $this->issuance)->whereNull('storage_warning_at')->whereNull('deleted_at')
                ->update(['storage_warning_at' => now(), 'storage_warning_queued_at' => null, 'storage_cleanup_due_at' => now()->addDays($this->graceDays)]);
        }
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
