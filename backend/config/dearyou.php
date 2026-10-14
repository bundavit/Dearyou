<?php

return [
    'admin_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_ALLOWED_IPS', '')),
    ))),
    'feedback_notify_email' => env('FEEDBACK_NOTIFY_EMAIL') ?: env('ADMIN_EMAIL', 'admin@dearyou.test'),
    // These budgets apply only to feedback alerts, never verification/reset mail.
    'feedback_email_hourly_limit' => (int) env('FEEDBACK_EMAIL_HOURLY_LIMIT', 3),
    'feedback_email_daily_limit' => (int) env('FEEDBACK_EMAIL_DAILY_LIMIT', 5),
    'feedback_email_max_age_hours' => (int) env('FEEDBACK_EMAIL_MAX_AGE_HOURS', 24),
    'backup_dir' => env('DEARYOU_BACKUP_DIR', '/var/backups/dearyou'),
    'storage_limit_mb' => (int) env('DEARYOU_STORAGE_LIMIT_MB', 250),
    'storage_cleanup_grace_days' => (int) env('DEARYOU_STORAGE_CLEANUP_GRACE_DAYS', 7),
];
