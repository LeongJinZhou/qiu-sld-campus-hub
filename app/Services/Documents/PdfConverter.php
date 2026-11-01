<?php

namespace App\Services\Documents;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
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
        $this->normaliseFonts($sourcePath);
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
            $xml = (string) preg_replace('#<w:(sz|szCs) w:val="\d+"\s*/>#', '<w:$1 w:val="'.$half.'"/>', $xml);
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
