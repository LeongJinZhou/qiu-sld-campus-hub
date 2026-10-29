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
     * Placeholders still present after filling (should be none).
     *
     * @return list<string>
     */
    public static function remainingPlaceholders(string $docxPath): array
    {
        return (new TemplateProcessor($docxPath))->getVariables();
    }
}
