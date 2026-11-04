<?php

namespace App\Services\Documents;

use Closure;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

/** Fills a placeholder copy (resources/templates/filled) of an official DOCX. Layout is never changed. */
class DocxRenderer
{
    /** @param Closure(TemplateProcessor): void $fill */
    public function render(string $template, Closure $fill, string $outputPath): string
    {
        // User text such as "Arrival & Registration" must be XML-escaped or the DOCX is corrupt.
        Settings::setOutputEscapingEnabled(true);
        $processor = new TemplateProcessor(resource_path("templates/filled/{$template}"));
        $fill($processor);

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }
        $processor->saveAs($outputPath);
        self::keepDatesTogether($outputPath);

        return $outputPath;
    }

    /**
     * Dates, times and amounts never break across lines: "15 November 2026", "4.00 PM" and "RM 250.00" are joined with
     * non-breaking spaces in the document body, headers and footers.
     */
    public static function keepDatesTogether(string $docxPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($docxPath) !== true) {
            return;
        }
        $months = 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|June?|July?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
        $nbsp = "\u{00A0}";
        $patterns = [
            "/\\b(\\d{1,2}) ({$months})\\.? (\\d{4})\\b/u" => "\$1{$nbsp}\$2{$nbsp}\$3",
            "/\\b({$months}) (\\d{1,2}),? (\\d{4})\\b/u" => "\$1{$nbsp}\$2{$nbsp}\$3",
            '/\\b(\\d{1,2}[.:]\\d{2}) ?([AaPp]\\.?[Mm]\\.?)/u' => "\$1{$nbsp}\$2",
            '/\\bRM (?=\\d)/u' => "RM{$nbsp}",
        ];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! preg_match('#^word/(document|header\\d*|footer\\d*)\\.xml$#', $name)) {
                continue;
            }
            $xml = (string) $zip->getFromIndex($i);
            $fixed = preg_replace_callback('#(<w:t(?:\\s[^>]*)?>)([^<]*)(</w:t>)#u', fn (array $m) => $m[1].preg_replace(array_keys($patterns), array_values($patterns), $m[2]).$m[3], $xml);
            if (is_string($fixed) && $fixed !== $xml) {
                $zip->addFromString($name, $fixed);
            }
        }
        $zip->close();
    }

    /**
     * cloneBlock() does not escape its replacement values (setValue does), so escape them here.
     *
     * @param  list<array<string, string>>  $rows
     */
    public static function block(TemplateProcessor $t, string $name, array $rows): void
    {
        $t->cloneBlock($name, 0, true, false, array_map(
            fn (array $row) => array_map(fn (string $v) => htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $row),
            $rows,
        ));
    }

    /**
     * Placeholders still present after filling (should be none).
     *
     * @return list<string>
     */
    public static function remainingPlaceholders(string $docxPath): array
    {
        return (new TemplateProcessor($docxPath))->getVariables();
    }
}
