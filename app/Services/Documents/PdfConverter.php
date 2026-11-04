<?php

namespace App\Services\Documents;

use App\Exceptions\PdfConversionFailed;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * LibreOffice headless conversion. Every PDF is set in one sans-serif face at 12 pt (Arial, or its metric twin
 * Liberation Sans on servers without it) so exported documents read the same everywhere.
 */
class PdfConverter
{
    public const FONT = 'Arial';

    public const SIZE_PT = 12;

    public function convert(string $sourcePath): string
    {
        if (! is_file($sourcePath)) {
            throw new PdfConversionFailed("PDF conversion failed: {$sourcePath} does not exist.");
        }
        if (! filled(config('sld.soffice'))) {
            throw new PdfConversionFailed('PDF conversion is not set up on this server (LibreOffice path is empty).');
        }
        $this->normaliseFonts($sourcePath);
        $outDir = dirname($sourcePath);
        $pdf = $outDir.'/'.pathinfo($sourcePath, PATHINFO_FILENAME).'.pdf';

        // A private, writable LibreOffice profile per process (a shared /tmp profile is the usual cause of silent
        // failures); on failure, one retry with a brand-new profile.
        $last = null;
        foreach ([false, true] as $fresh) {
            $profileDir = storage_path('framework/lo-profile/'.getmypid().($fresh ? '-'.bin2hex(random_bytes(3)) : ''));
            @mkdir($profileDir, 0775, true);
            $process = new Process(
                [config('sld.soffice'), '-env:UserInstallation=file://'.$profileDir, '--headless', '--norestore', '--nologo', '--convert-to', 'pdf', '--outdir', $outDir, $sourcePath],
                null,
                ['HOME' => $profileDir, 'TMPDIR' => sys_get_temp_dir()],
            );
            $process->setTimeout(120)->run();
            if ($process->isSuccessful() && is_file($pdf)) {
                if ($fresh) {
                    $this->removeDir($profileDir);
                }

                return $pdf;
            }
            $last = $process;
            if ($fresh) {
                $this->removeDir($profileDir);
            }
        }
        Log::error('PDF conversion failed', [
            'file' => basename($sourcePath), 'binary' => config('sld.soffice'), 'exit_code' => $last?->getExitCode(),
            'stderr' => mb_substr((string) $last?->getErrorOutput(), 0, 2000), 'stdout' => mb_substr((string) $last?->getOutput(), 0, 2000),
        ]);

        throw new PdfConversionFailed('PDF conversion failed (exit '.$last?->getExitCode().'): '.trim((string) ($last?->getErrorOutput() ?: $last?->getOutput())));
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    /** Rewrites the file in place: every run in a Word file, every cell style in a workbook. */
    public function normaliseFonts(string $path): void
    {
        match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'docx' => $this->docx($path),
            'xlsx' => $this->xlsx($path),
            default => null,
        };
    }

    private function docx(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return;
        }
        $half = self::SIZE_PT * 2; // Word sizes are in half-points
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^word/(document|styles|header\d*|footer\d*|footnotes|endnotes|numbering)\.xml$#', $name)) {
                continue;
            }
            $xml = (string) $zip->getFromIndex($i);
            $xml = (string) preg_replace('#<w:rFonts\b[^>]*/>#', '<w:rFonts w:ascii="'.self::FONT.'" w:hAnsi="'.self::FONT.'" w:cs="'.self::FONT.'" w:eastAsia="'.self::FONT.'"/>', $xml);
            // Every size becomes 12 pt, except 9.5 pt (19 half-points): the AGM attendance tables, whose seven columns
            // (with student IDs and emails) only fit on one line each at that size.
            $xml = (string) preg_replace('#<w:(sz|szCs) w:val="(?!19")\d+"\s*/>#', '<w:$1 w:val="'.$half.'"/>', $xml);
            if ($name === 'word/styles.xml' && ! str_contains($xml, '<w:rPrDefault>')) {
                $xml = (string) preg_replace('#<w:docDefaults>#', '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="'.self::FONT.'" w:hAnsi="'.self::FONT.'" w:cs="'.self::FONT.'"/><w:sz w:val="'.$half.'"/><w:szCs w:val="'.$half.'"/></w:rPr></w:rPrDefault>', $xml, 1);
            }
            $zip->addFromString($name, $xml);
        }
        $zip->close();
    }

    private function xlsx(string $path): void
    {
        $book = IOFactory::load($path);
        $book->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(self::SIZE_PT);
        foreach ($book->getCellXfCollection() as $style) {
            $style->getFont()->setName(self::FONT)->setSize(self::SIZE_PT);
        }
        IOFactory::createWriter($book, 'Xlsx')->save($path);
    }
}
