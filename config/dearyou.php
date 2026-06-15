<?php

return [
    'admin_allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ADMIN_ALLOWED_IPS', '')),
    ))),
    'storage_limit_mb' => (int) env('DEARYOU_STORAGE_LIMIT_MB', 250),
    'storage_cleanup_grace_days' => (int) env('DEARYOU_STORAGE_CLEANUP_GRACE_DAYS', 7),
];
