<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Spreadsheet export safety for every exporter: text that starts with "=" is stored as text, so a description like
 * "=1+1" or "=HYPERLINK(...)" can never run as a formula. The only formulas allowed are the app's own arithmetic on
 * cell references (e.g. =SUM(C6:C9), =E5+C6-D6); they cannot call other functions.
 */
class SafeValueBinder extends DefaultValueBinder
{
    private const APP_FORMULA = '/^=(SUM\([A-Z]{1,3}\d+:[A-Z]{1,3}\d+\)|[A-Z]{1,3}\d+)([+\-](SUM\([A-Z]{1,3}\d+:[A-Z]{1,3}\d+\)|[A-Z]{1,3}\d+))*$/';

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && str_starts_with($value, '=') && ! preg_match(self::APP_FORMULA, $value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
