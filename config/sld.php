<?php

return [
    // Always signed in as Super Admin (bootstraps role management).
    'superadmin_emails' => array_filter(array_map('trim', explode(',', (string) env('SUPERADMIN_EMAILS', '')))),

    // "Log in as a demo account" page: always on locally; on a hosted demo only with DEMO_LOGIN=true. Never in a pilot.
    'demo_login' => (bool) env('DEMO_LOGIN', env('APP_ENV') === 'local'),

    // LibreOffice headless, used to turn filled DOCX/XLSX templates into PDFs that match the originals.
    'soffice' => env('SOFFICE_PATH', PHP_OS_FAMILY === 'Darwin' ? '/Applications/LibreOffice.app/Contents/MacOS/soffice' : 'soffice'),

    // Python with python-pptx for the Annual Activities Report (tools/pptx). Local: python3 -m venv tools/pptx/.venv.
    'python' => env('PYTHON_PATH', is_file(base_path('tools/pptx/.venv/bin/python')) ? base_path('tools/pptx/.venv/bin/python') : 'python3'),
];
