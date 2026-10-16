<?php

namespace App\Notifications;

use App\Support\EmailIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordResetCode extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [10, 60, 180];

    public function __construct(
        public readonly string $code,
        public readonly ?string $email = null,
        public readonly ?string $codeHash = null,
    ) {}

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

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $email = $this->email ?? $notifiable->email;
        $user = EmailIdentity::findActive($email);

        if ($user === null || (string) $user->id !== (string) $notifiable->id) {
            return false;
        }

        $record = DB::table('password_reset_codes')->where('email', $user->email)->first();

        return $record !== null
            && (int) $record->attempts < 5
            && now()->lessThan(Carbon::parse($record->expires_at))
            && ($this->codeHash === null || hash_equals($this->codeHash, $record->code))
            && Hash::check($this->code, $record->code);
    }
}
