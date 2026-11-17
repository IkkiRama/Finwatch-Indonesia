<?php

return [
    'official_ca_bundle' => env('FINWATCH_OFFICIAL_CA_BUNDLE'),
    'backup_key' => env('FINWATCH_BACKUP_KEY'),
    'admin_mfa_required' => env('FINWATCH_ADMIN_MFA_REQUIRED', true),
    'admin_mfa_ttl_seconds' => 900,
];
