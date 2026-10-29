<?php

namespace App\Services\Documents;

use Closure;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

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

        return $outputPath;
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
