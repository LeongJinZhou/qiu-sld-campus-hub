<?php

return [
    // Always signed in as Super Admin (bootstraps role management).
    'superadmin_emails' => array_filter(array_map('trim', explode(',', (string) env('SUPERADMIN_EMAILS', '')))),

    // "Log in as a demo account" page: always on locally; on a hosted demo only with DEMO_LOGIN=true. Never in a pilot.
    'demo_login' => (bool) env('DEMO_LOGIN', env('APP_ENV') === 'local'),

    // Outside local development the demo login page is locked behind a passcode (bcrypt hash; set
    // DEMO_LOGIN_PASSCODE_HASH to change it). Without the passcode nobody can see or use the account list.
    'demo_passcode_hash' => env('DEMO_LOGIN_PASSCODE_HASH', '$2y$12$iF352YCX3tSORSQVJBYhLecnQiKN3418Y03f3mmXhUHSBopvtOKoS'),

    // LibreOffice headless, used to turn filled DOCX/XLSX templates into PDFs that match the originals.
    'soffice' => env('SOFFICE_PATH', PHP_OS_FAMILY === 'Darwin' ? '/Applications/LibreOffice.app/Contents/MacOS/soffice' : 'soffice'),

    // Python with python-pptx for the Annual Activities Report (tools/pptx). Local: python3 -m venv tools/pptx/.venv.
    // Shared hosting (e.g. Exabytes cPanel) has cron but no always-on queue worker: when true, the per-minute
    // scheduler also works through queued jobs (AI drafting, archives, PDFs) and then stops.
    'queue_via_scheduler' => (bool) env('QUEUE_VIA_SCHEDULER', false),

    'python' => env('PYTHON_PATH', is_file(base_path('tools/pptx/.venv/bin/python')) ? base_path('tools/pptx/.venv/bin/python') : 'python3'),
];
