<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Agm\AgmController;
use App\Http\Controllers\Agm\AgmReportController;
use App\Http\Controllers\Agm\AnnualReportController;
use App\Http\Controllers\Agm\ElectionController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubQrController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Events\AttendanceController;
use App\Http\Controllers\Events\EventCommitteeController;
use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\EventDocumentController;
use App\Http\Controllers\Events\EventRegistrationController;
use App\Http\Controllers\Events\IndemnityController;
use App\Http\Controllers\Events\PosterController;
use App\Http\Controllers\Events\PostMortemController;
use App\Http\Controllers\Events\ProposalController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Meetings\GoogleCalendarController;
use App\Http\Controllers\Meetings\MeetingController;
use App\Http\Controllers\Membership\FeeController;
use App\Http\Controllers\Membership\HandoverController;
use App\Http\Controllers\Membership\MembersController;
use App\Http\Controllers\Membership\MembershipController;
use App\Http\Controllers\MyClubsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SldRequestController;
use App\Http\Controllers\Social\ClubSocialController;
use App\Http\Controllers\Social\FeedController;
use App\Http\Controllers\Social\PostController;
use App\Http\Controllers\Venues\VenueController;
use App\Http\Controllers\VerifyController;
use App\Models\Club;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => Inertia::render('Auth/Login', ['devLogin' => config('sld.demo_login')]))->name('login');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/alumni', [GoogleController::class, 'alumni'])->name('auth.google.alumni');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1');

    if (config('sld.demo_login')) {
        Route::get('/dev/login-as', [DevLoginController::class, 'index'])->name('dev.login');
        Route::post('/dev/login-as', [DevLoginController::class, 'store'])->middleware('throttle:30,1');
    }
});

Route::get('/not-in-records', fn () => Inertia::render('Auth/NotFoundInRecords'))->name('not-in-records');
Route::get('/p/{token}', PublicProfileController::class)->name('profile.public');
Route::get('/verify/{code}', VerifyController::class)->middleware('throttle:30,1')->name('verify');
Route::get('/certificates/{code}', [CertificateController::class, 'verify'])->middleware('throttle:30,1')->name('certificates.verify');

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
    Route::get('/events/{event}/attend', [AttendanceController::class, 'attend'])->middleware('throttle:20,1')->name('events.attend');
    Route::get('/events/{event}/attendance', [AttendanceController::class, 'index'])->name('events.attendance');
    Route::post('/events/{event}/attendance', [AttendanceController::class, 'record'])->middleware('throttle:120,1')->name('events.attendance.record');
    Route::get('/events/{event}/attendance/export', [AttendanceController::class, 'export'])->name('events.attendance.export');
    Route::post('/events/{event}/attendance/submit', [AttendanceController::class, 'submit'])->name('events.attendance.submit');
    Route::get('/events/{event}/poster', [PosterController::class, 'edit'])->name('events.poster');
    Route::post('/events/{event}/poster', [PosterController::class, 'update'])->name('events.poster.update');
    Route::get('/events/{event}/post-mortem', [PostMortemController::class, 'show'])->name('events.post-mortem');
    Route::put('/events/{event}/post-mortem', [PostMortemController::class, 'save'])->name('events.post-mortem.save');
    Route::post('/events/{event}/post-mortem/suggest', [PostMortemController::class, 'suggest'])->middleware('throttle:6,1')->name('events.post-mortem.suggest');
    Route::post('/events/{event}/post-mortem/submit', [PostMortemController::class, 'submit'])->name('events.post-mortem.submit');
    Route::get('/events/{event}/documents', [EventDocumentController::class, 'index'])->name('events.documents');
    Route::post('/events/{event}/documents/{item}', [EventDocumentController::class, 'upload'])->name('events.documents.upload');
    Route::get('/events/{event}/documents/{item}', [EventDocumentController::class, 'download'])->middleware('signed')->name('events.documents.download');
    Route::get('/events/{event}/indemnity', [IndemnityController::class, 'create'])->name('events.indemnity');
    Route::post('/events/{event}/indemnity', [IndemnityController::class, 'store'])->name('events.indemnity.store');
    Route::get('/events/{event}/indemnities', [IndemnityController::class, 'index'])->name('events.indemnities');
    Route::get('/events/{event}/indemnities/pdf', [IndemnityController::class, 'download'])->name('events.indemnities.pdf');
    Route::get('/events/{event}/proposal', [ProposalController::class, 'edit'])->name('proposals.edit');
    Route::put('/events/{event}/proposal', [ProposalController::class, 'update'])->name('proposals.update');
    Route::post('/events/{event}/proposal/submit', [ProposalController::class, 'submit'])->name('proposals.submit');
    Route::post('/events/{event}/proposal/ask', [ProposalController::class, 'askToSubmit'])->middleware('throttle:10,1')->name('proposals.ask');
    Route::delete('/events/{event}/proposal', [ProposalController::class, 'discard'])->name('proposals.discard');
    Route::post('/events/{event}/committee', [EventCommitteeController::class, 'store'])->name('events.committee.store');
    Route::delete('/events/{event}/committee/{assignment}', [EventCommitteeController::class, 'destroy'])->name('events.committee.destroy');

    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/download/{format}', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::get('/approvals/{approval}', [ApprovalController::class, 'show'])->name('approvals.show');
    Route::post('/approvals/{approval}', [ApprovalController::class, 'decide'])->name('approvals.decide');

    Route::get('/notifications', NotificationController::class)->name('notifications');

    Route::get('/clubs/{club}/join', [MembershipController::class, 'show'])->name('membership.join');
    Route::post('/clubs/{club}/join', [MembershipController::class, 'join'])->middleware('throttle:10,1')->name('membership.store');
    Route::post('/clubs/{club}/join/receipt', [MembershipController::class, 'receipt'])->middleware('throttle:10,1')->name('membership.receipt');
    Route::post('/clubs/{club}/join/cash', [MembershipController::class, 'cash'])->middleware('throttle:10,1')->name('membership.cash');
    Route::get('/clubs/{club}/members', [MembersController::class, 'club'])->name('members.club');
    Route::get('/clubs/{club}/members/export/{format}', [MembersController::class, 'export'])->name('members.export');
    Route::post('/clubs/{club}/members/remind', [MembersController::class, 'remind'])->middleware('throttle:5,1')->name('members.remind');
    Route::post('/saved-views', [MembersController::class, 'saveView'])->name('views.store');
    Route::delete('/saved-views/{view}', [MembersController::class, 'deleteView'])->name('views.destroy');
    Route::get('/clubs/{club}/finance', [FinanceController::class, 'show'])->name('finance.show');
    Route::post('/clubs/{club}/finance/opening', [FinanceController::class, 'opening'])->name('finance.opening');
    Route::post('/clubs/{club}/finance/entries', [FinanceController::class, 'entry'])->name('finance.entry');
    Route::get('/clubs/{club}/finance/receipts/{receipt}', [FinanceController::class, 'receipt'])->middleware('signed')->name('finance.receipt');
    Route::get('/clubs/{club}/finance/export/{format}', [FinanceController::class, 'export'])->name('finance.export');
    Route::post('/clubs/{club}/finance/entries/{entry}/receipt', [FinanceController::class, 'attachReceipt'])->name('finance.entry.receipt');
    Route::post('/clubs/{club}/finance/payees', [FinanceController::class, 'payee'])->name('finance.payees');
    Route::get('/clubs/{club}/fund-requests/create', [FinanceController::class, 'requestForm'])->name('fund.create');
    Route::post('/clubs/{club}/fund-requests', [FinanceController::class, 'submitRequest'])->name('fund.store');
    Route::get('/clubs/{club}/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::post('/clubs/{club}/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::post('/meetings/{meeting}/attendance', [MeetingController::class, 'attendance'])->name('meetings.attendance');
    Route::put('/meetings/{meeting}/minutes', [MeetingController::class, 'save'])->name('meetings.minutes.save');
    Route::post('/meetings/{meeting}/minutes/draft', [MeetingController::class, 'draft'])->middleware('throttle:6,1')->name('meetings.minutes.draft');
    Route::post('/meetings/{meeting}/minutes/submit', [MeetingController::class, 'submit'])->name('meetings.minutes.submit');
    Route::get('/google/calendar/connect', [GoogleCalendarController::class, 'connect'])->name('google.calendar.connect');
    Route::get('/google/calendar/callback', [GoogleCalendarController::class, 'callback'])->name('google.calendar.callback');
    Route::get('/clubs/{club}/agm', [AgmController::class, 'show'])->name('agm.show');
    Route::post('/clubs/{club}/agm', [AgmController::class, 'store'])->name('agm.store');
    Route::get('/agms/{agm}/attend', [AgmController::class, 'attend'])->middleware('throttle:30,1')->name('agm.attend');
    Route::post('/agms/{agm}/attendance', [AgmController::class, 'record'])->middleware('throttle:120,1')->name('agm.attendance');
    Route::post('/agms/{agm}/package', [AgmController::class, 'submitPackage'])->name('agm.package');
    Route::get('/agms/{agm}/report', [AgmReportController::class, 'edit'])->name('agm.report');
    Route::put('/agms/{agm}/report', [AgmReportController::class, 'save'])->name('agm.report.save');
    Route::post('/agms/{agm}/report/draft', [AgmReportController::class, 'draft'])->middleware('throttle:6,1')->name('agm.report.draft');
    Route::post('/agms/{agm}/report/submit', [AgmReportController::class, 'submit'])->name('agm.report.submit');
    Route::get('/agms/{agm}/election', [ElectionController::class, 'show'])->name('election.show');
    Route::post('/agms/{agm}/election', [ElectionController::class, 'store'])->name('election.store');
    Route::post('/election-positions/{position}/nominate', [ElectionController::class, 'nominate'])->middleware('throttle:10,1')->name('election.nominate');
    Route::post('/election-positions/{position}/vote', [ElectionController::class, 'vote'])->middleware('throttle:30,1')->name('election.vote');
    Route::post('/elections/{election}/publish', [ElectionController::class, 'publish'])->name('election.publish');
    Route::put('/clubs/{club}/annual-report', [AnnualReportController::class, 'save'])->name('annual.save');
    Route::post('/clubs/{club}/annual-report/draft', [AnnualReportController::class, 'draft'])->middleware('throttle:6,1')->name('annual.draft');
    Route::post('/clubs/{club}/annual-report/generate', [AnnualReportController::class, 'generate'])->middleware('throttle:6,1')->name('annual.generate');
    Route::get('/clubs/{club}/annual-report/{format}', [AnnualReportController::class, 'download'])->name('annual.download');
    Route::post('/sld-requests/{sldRequest}/reply', [SldRequestController::class, 'reply'])->name('sld-requests.reply');
    Route::get('/clubs/{club}/venues', [VenueController::class, 'index'])->name('venues.index');
    Route::post('/clubs/{club}/venues', [VenueController::class, 'store'])->middleware('throttle:10,1')->name('venues.store');
    Route::get('/clubs/{club}/payments/review', [MembershipController::class, 'reviewQueue'])->name('payments.review');
    Route::post('/payments/{payment}/decide', [MembershipController::class, 'decide'])->name('payments.decide');
    Route::get('/payments/{payment}/receipt', [MembershipController::class, 'receiptFile'])->middleware('signed')->name('payments.receipt');
    Route::post('/clubs/{club}/fee', [FeeController::class, 'store'])->name('fees.store');
    Route::get('/handovers/{handover}', [HandoverController::class, 'show'])->name('handovers.show');
    Route::post('/handovers/{handover}', [HandoverController::class, 'act'])->middleware('throttle:60,1')->name('handovers.act');
    Route::get('/handovers/{handover}/photo', [HandoverController::class, 'photo'])->middleware('signed')->name('handovers.photo');
    Route::get('/my-clubs', MyClubsController::class)->name('my-clubs');
    Route::put('/notifications/mutes', [NotificationController::class, 'mutes'])->name('notifications.mutes');
    Route::post('/notifications/left-halfway', [NotificationController::class, 'leftHalfway'])->middleware('throttle:30,1')->name('notifications.left');
    Route::get('/me', [ProfileController::class, 'show'])->name('profile.me');
    Route::get('/me/certificates/{kind}/{source}', [CertificateController::class, 'download'])->middleware('throttle:20,1')->whereNumber('source')->name('certificates.download');
    Route::post('/me', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('admin')->name('admin.')->middleware('can:manage,'.Club::class)->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::resource('clubs', Admin\ClubController::class)->except(['show', 'destroy']);
        Route::post('clubs/{club}/committee', [Admin\ClubCommitteeController::class, 'assign'])->name('clubs.committee.assign');
        Route::post('clubs/{club}/advisors', [Admin\ClubCommitteeController::class, 'addAdvisor'])->name('clubs.advisors.store');
        Route::delete('clubs/{club}/assignments/{assignment}', [Admin\ClubCommitteeController::class, 'remove'])->name('clubs.assignments.destroy');
        Route::post('clubs/{club}/transfer', [Admin\ClubTransferController::class, 'store'])->name('clubs.transfer');
        Route::delete('clubs/{club}/transfer', [Admin\ClubTransferController::class, 'destroy'])->name('clubs.take-back');

        Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');

        Route::get('members', [MembersController::class, 'all'])->name('members.all');
        Route::get('finance', Admin\FinanceOverviewController::class)->name('finance.index');
        Route::get('requests', [SldRequestController::class, 'index'])->name('requests.index');
        Route::post('requests', [SldRequestController::class, 'store'])->name('requests.store');
        Route::get('agm', [Admin\AgmController::class, 'index'])->name('agm.index');
        Route::post('agm/periods', [Admin\AgmController::class, 'open'])->name('agm.open');
        Route::post('agm/periods/{period}/close', [Admin\AgmController::class, 'close'])->name('agm.close');
        Route::post('agm/themes', [Admin\AgmController::class, 'theme'])->name('agm.themes');
        Route::get('venues', [VenueController::class, 'pending'])->name('venues.index');
        Route::post('venues/{booking}', [VenueController::class, 'decide'])->name('venues.decide');
        Route::get('fees', [FeeController::class, 'index'])->name('fees.index');
        Route::post('fees/{fee}/approve', [FeeController::class, 'approve'])->name('fees.approve');
        Route::post('drives', [FeeController::class, 'drive'])->name('drives.store');

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
