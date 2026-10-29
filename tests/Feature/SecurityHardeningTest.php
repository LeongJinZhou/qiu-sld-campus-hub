<?php

use App\Models\AuditLog;
use App\Models\Payee;
use App\Services\Uploads\ImageUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Phase 5 security pass: every route guarded, rate limits, uploads re-encoded, audit coverage. */
const PUBLIC_ROUTES = ['up', '/', 'auth/google/redirect', 'auth/google/alumni', 'auth/google/callback', 'not-in-records', 'p/{token}', 'verify/{code}', 'certificates/{code}', 'dev/login-as'];

it('requires sign-in on every route except the public allow-list, and SLD rights on every admin route', function () {
    $open = [];
    $admin = [];
    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();
        $middleware = $route->gatherMiddleware();
        if (str_starts_with($uri, '_inertia/')) {
            continue; // Inertia devtools: local only, behind its own gate
        }
        if (! in_array('auth', $middleware, true) && ! in_array($uri, PUBLIC_ROUTES, true)) {
            $open[] = $uri;
        }
        if (str_starts_with($uri, 'admin') && ! collect($middleware)->contains(fn ($m) => str_starts_with($m, 'can:manage'))) {
            $admin[] = $uri;
        }
    }
    expect($open)->toBe([])->and($admin)->toBe([]);
});

it('protects every state-changing route with the web stack (sessions and CSRF)', function () {
    $missing = collect(Route::getRoutes())->filter(fn ($r) => array_diff($r->methods(), ['GET', 'HEAD']) !== [] && ! str_starts_with($r->uri(), '_inertia/'))
        ->reject(fn ($r) => in_array('web', $r->gatherMiddleware(), true))->map->uri()->values()->all();
    expect($missing)->toBe([]);
});

it('rate-limits sign-in, payments, handovers and votes', function () {
    foreach (['auth/google/callback', 'clubs/{club}/join/receipt', 'handovers/{handover}', 'election-positions/{position}/vote'] as $uri) {
        $route = collect(Route::getRoutes())->first(fn ($r) => $r->uri() === $uri && array_diff($r->methods(), ['HEAD']) !== []
            && ($uri !== 'handovers/{handover}' || in_array('POST', $r->methods(), true)));
        expect(collect($route?->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'throttle')))->toBeTrue("{$uri} has no rate limit");
    }
});

it('re-encodes uploaded photos so hidden payloads and metadata are dropped', function () {
    Storage::fake('public');
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagepng($image);
    $png = ob_get_clean().'<?php system($_GET["c"]); ?>';
    $file = UploadedFile::fake()->createWithContent('cat.png', $png);

    $path = app(ImageUpload::class)->store($file, 'avatars');
    expect(Storage::disk('public')->get($path))->not->toContain('<?php')
        ->and(getimagesizefromstring(Storage::disk('public')->get($path))[0])->toBe(40);

    app(ImageUpload::class)->store(UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'), 'avatars');
})->throws(ValidationException::class);

it('scales oversized photos down to 2048px on the long edge', function () {
    Storage::fake('public');
    $path = app(ImageUpload::class)->store(UploadedFile::fake()->image('big.jpg', 4000, 1000), 'posts');
    expect(getimagesizefromstring(Storage::disk('public')->get($path))[0])->toBe(2048);
});

it('keeps bank details encrypted at rest', function () {
    $f = proposalFixture();
    $payee = Payee::create(['club_id' => $f['club']->id, 'kind' => 'vendor', 'name' => 'Vendor Sdn Bhd', 'bank_name' => 'Maybank', 'account_holder' => 'Vendor Sdn Bhd', 'account_number' => '5123456789012']);
    $raw = DB::table('payees')->where('id', $payee->id)->first();
    expect($raw->account_number)->not->toContain('5123456789012')->and($payee->fresh()->account_number)->toBe('5123456789012');
});

it('writes the audit log for submissions, approvals, role changes and payments', function () {
    $f = proposalFixture();
    $this->actingAs($f['president'])->post("/events/{$f['event']->slug}/proposal/submit", [...completeProposal($f), 'signature' => signaturePng(), 'signature_strokes' => signatureStrokes()]);
    approveRemainingSteps($f['document']);
    $actions = AuditLog::pluck('action')->unique()->all();
    expect($actions)->toContain('committee.assigned')->toContain('advisor.added')->toContain('document.submitted')
        ->toContain('approval.approved')->toContain('document.approved');
});
