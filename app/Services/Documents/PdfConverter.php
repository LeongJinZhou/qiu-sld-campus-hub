<?php

namespace App\Services\Documents;

use RuntimeException;
use Symfony\Component\Process\Process;

/** LibreOffice headless conversion, so PDFs match the official templates exactly. */
class PdfConverter
{
    public function convert(string $sourcePath): string
    {
        $outDir = dirname($sourcePath);
        // A private profile dir lets several conversions run at once.
        $profile = 'file://'.sys_get_temp_dir().'/lo-profile-'.getmypid();
        $process = new Process([config('sld.soffice'), "-env:UserInstallation={$profile}", '--headless', '--convert-to', 'pdf', '--outdir', $outDir, $sourcePath]);
        $process->setTimeout(120)->run();

        $pdf = $outDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';
        if (! $process->isSuccessful() || ! is_file($pdf)) {
            throw new RuntimeException('PDF conversion failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $pdf;
    }
}
