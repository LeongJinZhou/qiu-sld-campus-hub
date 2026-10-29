<?php

return [
    // Always signed in as Super Admin (bootstraps role management).
    'superadmin_emails' => array_filter(array_map('trim', explode(',', (string) env('SUPERADMIN_EMAILS', '')))),

    // LibreOffice headless, used to turn filled DOCX/XLSX templates into PDFs that match the originals.
    'soffice' => env('SOFFICE_PATH', PHP_OS_FAMILY === 'Darwin' ? '/Applications/LibreOffice.app/Contents/MacOS/soffice' : 'soffice'),
];
