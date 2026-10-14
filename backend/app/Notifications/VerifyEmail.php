<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your DearYou verification code')
            ->greeting('Welcome to DearYou')
            ->line('Enter this six-digit code to verify your email address:')
            ->line("**{$this->code}**")
            ->line('This code expires in 10 minutes.')
            ->line('If you did not create this account, you can ignore this email.');
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($notifiable->hasVerifiedEmail() || $notifiable->disabled_at || $notifiable->trashed()) {
            return false;
        }

        $record = DB::table('email_verification_codes')->where('user_id', $notifiable->id)->first();

        return $record !== null
            && (int) $record->attempts < 5
            && now()->lessThanOrEqualTo(Carbon::parse($record->expires_at))
            && Hash::check($this->code, $record->code);
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }
}
