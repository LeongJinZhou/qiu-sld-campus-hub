<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubQrController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Events\EventCommitteeController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\EventRegistrationController;
use App\Http\Controllers\Events\ProposalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\Social\ClubSocialController;
use App\Http\Controllers\Social\FeedController;
use App\Http\Controllers\Social\PostController;
use App\Http\Controllers\VerifyController;
use App\Models\Club;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => Inertia::render('Auth/Login', ['devLogin' => app()->isLocal()]))->name('login');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1');

    if (app()->isLocal()) {
        Route::get('/dev/login-as', [DevLoginController::class, 'index'])->name('dev.login');
        Route::post('/dev/login-as', [DevLoginController::class, 'store'])->middleware('throttle:30,1');
    }
});

Route::get('/not-in-records', fn () => Inertia::render('Auth/NotFoundInRecords'))->name('not-in-records');
Route::get('/p/{token}', PublicProfileController::class)->name('profile.public');
Route::get('/verify/{code}', VerifyController::class)->middleware('throttle:30,1')->name('verify');

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/feed', FeedController::class)->name('feed');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::post('/posts/{post}/like', [PostController::class, 'like'])->middleware('throttle:60,1')->name('posts.like');
    Route::post('/posts/{post}/save', [PostController::class, 'save'])->middleware('throttle:60,1')->name('posts.save');
    Route::post('/posts/{post}/comments', [PostController::class, 'comment'])->middleware('throttle:20,1')->name('posts.comment');
    Route::post('/posts/{post}/report', [PostController::class, 'report'])->middleware('throttle:10,1')->name('posts.report');

    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
    Route::post('/clubs/{club}/posts', [PostController::class, 'store'])->name('clubs.posts.store');
    Route::post('/clubs/{club}/follow', [ClubSocialController::class, 'follow'])->name('clubs.follow');
    Route::post('/clubs/{club}/stories', [ClubSocialController::class, 'storeStory'])->name('clubs.stories.store');
    Route::post('/clubs/{club}/stories/{story}/highlight', [ClubSocialController::class, 'highlightStory'])->name('clubs.stories.highlight');
    Route::delete('/clubs/{club}/highlights/{highlight}', [ClubSocialController::class, 'destroyHighlight'])->name('clubs.highlights.destroy');
    Route::post('/clubs/{club}/albums', [ClubSocialController::class, 'storeAlbum'])->name('clubs.albums.store');
    Route::post('/clubs/{club}/albums/{album}/photos', [ClubSocialController::class, 'addAlbumPhotos'])->name('clubs.albums.photos');
    Route::post('/clubs/{club}/faqs', [ClubSocialController::class, 'storeFaq'])->name('clubs.faqs.store');
    Route::delete('/clubs/{club}/faqs/{faq}', [ClubSocialController::class, 'destroyFaq'])->name('clubs.faqs.destroy');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');
    Route::get('/c/{token}', ClubQrController::class)->name('clubs.qr');

    Route::get('/clubs/{club}/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/clubs/{club}/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events/{event}/register', [EventRegistrationController::class, 'store'])->name('events.register');
    Route::delete('/events/{event}/register', [EventRegistrationController::class, 'destroy'])->name('events.unregister');
    Route::get('/events/{event}/proposal', [ProposalController::class, 'edit'])->name('proposals.edit');
    Route::put('/events/{event}/proposal', [ProposalController::class, 'update'])->name('proposals.update');
    Route::post('/events/{event}/proposal/submit', [ProposalController::class, 'submit'])->name('proposals.submit');
    Route::post('/events/{event}/committee', [EventCommitteeController::class, 'store'])->name('events.committee.store');
    Route::delete('/events/{event}/committee/{assignment}', [EventCommitteeController::class, 'destroy'])->name('events.committee.destroy');

    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/approvals/{approval}', [ApprovalController::class, 'decide'])->name('approvals.decide');

    Route::get('/me', [ProfileController::class, 'show'])->name('profile.me');
    Route::post('/me', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('admin')->name('admin.')->middleware('can:manage,'.Club::class)->group(function () {
        Route::resource('clubs', Admin\ClubController::class)->except(['show', 'destroy']);
        Route::post('clubs/{club}/committee', [Admin\ClubCommitteeController::class, 'assign'])->name('clubs.committee.assign');
        Route::post('clubs/{club}/advisors', [Admin\ClubCommitteeController::class, 'addAdvisor'])->name('clubs.advisors.store');
        Route::delete('clubs/{club}/assignments/{assignment}', [Admin\ClubCommitteeController::class, 'remove'])->name('clubs.assignments.destroy');
        Route::post('clubs/{club}/transfer', [Admin\ClubTransferController::class, 'store'])->name('clubs.transfer');
        Route::delete('clubs/{club}/transfer', [Admin\ClubTransferController::class, 'destroy'])->name('clubs.take-back');

        Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');

        Route::get('moderation', [Admin\ModerationController::class, 'index'])->name('moderation.index');
        Route::post('moderation/posts/{post}/pin', [Admin\ModerationController::class, 'togglePin'])->name('moderation.pin');
        Route::post('moderation/posts/{post}/hide', [Admin\ModerationController::class, 'toggleHidePost'])->name('moderation.hide-post');
        Route::post('moderation/comments/{comment}/hide', [Admin\ModerationController::class, 'toggleHideComment'])->name('moderation.hide-comment');
        Route::post('moderation/reports/{report}/resolve', [Admin\ModerationController::class, 'resolve'])->name('moderation.resolve');

        Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/{section}', [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::middleware('role:superadmin')->group(function () {
            Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{user}', [Admin\RoleController::class, 'update'])->name('roles.update');
            Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
            Route::get('sync', [Admin\SyncController::class, 'index'])->name('sync.index');
            Route::post('sync', [Admin\SyncController::class, 'store'])->name('sync.store');
        });
    });
});
