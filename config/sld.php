<?php

return [
    // Always signed in as Super Admin (bootstraps role management).
    'superadmin_emails' => array_filter(array_map('trim', explode(',', (string) env('SUPERADMIN_EMAILS', '')))),
];
