<?php

return [
    'admin_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_ALLOWED_IPS', '')),
    ))),
    'feedback_notify_email' => env('FEEDBACK_NOTIFY_EMAIL') ?: env('ADMIN_EMAIL', 'admin@dearyou.test'),
    'backup_dir' => env('DEARYOU_BACKUP_DIR', '/var/backups/dearyou'),
    'storage_limit_mb' => (int) env('DEARYOU_STORAGE_LIMIT_MB', 250),
    'storage_cleanup_grace_days' => (int) env('DEARYOU_STORAGE_CLEANUP_GRACE_DAYS', 7),
];
