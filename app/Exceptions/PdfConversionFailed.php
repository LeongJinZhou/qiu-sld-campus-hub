<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * The PDF copy could not be made (LibreOffice failed; details are in the log). People get a clear message and can
 * still download the Excel, Word or PowerPoint version, instead of an error page.
 */
class PdfConversionFailed extends RuntimeException
{
    public function render(): RedirectResponse
    {
        return back()->with('error', 'The PDF could not be made right now. Download the Excel, Word or PowerPoint version instead, or try again in a minute.');
    }
}
