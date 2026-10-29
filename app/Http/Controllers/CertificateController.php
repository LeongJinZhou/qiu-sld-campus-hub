<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\Profiles\Certificates;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    /** Issues (once) and downloads one of the signed-in person's own certificates. */
    public function download(Request $request, string $kind, int $source, Certificates $certificates): BinaryFileResponse
    {
        abort_unless(in_array($kind, Certificate::KINDS, true), 404);
        $certificate = $certificates->issue($request->user(), $kind, $source)->load('user.student');

        return response()->download($certificates->render($certificate), str_replace(['/', '\\'], '-', "{$certificate->title} - {$certificate->user->name}.pdf"))->deleteFileAfterSend();
    }

    /** Public check of a certificate code. */
    public function verify(string $code): Response
    {
        $certificate = Certificate::with('user')->where('code', strtoupper($code))->first();

        return Inertia::render('Verify/Certificate', [
            'code' => strtoupper($code),
            'certificate' => $certificate ? [
                'title' => $certificate->title, 'name' => $certificate->user->name,
                'statement' => $certificate->statement, 'issued_at' => $certificate->issued_at->toFormattedDateString(),
            ] : null,
        ]);
    }
}
