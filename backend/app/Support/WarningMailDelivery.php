<?php

namespace App\Support;

class WarningMailDelivery
{
    public static function configured(?string $mailer = null, array $seen = []): bool
    {
        $mailer ??= config('mail.default');
        if (! is_string($mailer) || in_array($mailer, $seen, true)) {
            return false;
        }
        $settings = config('mail.mailers.'.$mailer, []);
        $transport = $settings['transport'] ?? null;
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $settings['mailers'] ?? [];

            return $children !== [] && collect($children)->every(fn ($child) => self::configured($child, [...$seen, $mailer]));
        }

        return in_array($transport, ['smtp', 'sendmail', 'resend', 'ses', 'ses-v2', 'mailgun', 'postmark'], true);
    }
}
