# QIU SLD Campus Hub — Build Prompts

How to use:
1. Create an empty folder `sld-portal/`. Put `CLAUDE.md` in its root.
2. Put your files in these folders before starting (create them):
   - `resources/templates/original/` — all official templates (AGM, Finance Record, Attendance, Indemnity PDF, Proposal, Fund Withdrawal, Post Mortem, Full Member List, Annual Activities Report, Minutes, La Place SOP).
   - `public/brand/` — QIU_Student_Activity_Black.png, QIU_Student_Activity_White.png, poster and bunting guides.
   - `database/camsys/qapi_registration_mdl.sql` — the camsys dump.
   - `docs/reference/` — Club & Society Briefing PPTX (deadlines and rules).
3. Open the CLI in `sld-portal/` and paste **one phase at a time**. Wait until the phase is done and tests pass before pasting the next.

---

## PHASE 0 — Setup and design import (paste this first)

```
Read CLAUDE.md fully. We are starting the QIU SLD Campus Hub. Do Phase 0 only.

1. Create a Laravel 11 project in the current folder (keep CLAUDE.md, resources/templates, public/brand, database/camsys, docs). Install: Inertia + Vue 3 + Tailwind (Breeze Vue/Inertia stack), laravel/socialite, spatie/laravel-permission, phpoffice/phpword, phpoffice/phpspreadsheet, simplesoftwareio/simple-qrcode, pestphp/pest. Front-end: html5-qrcode, @vueuse/core.
2. Configure two DB connections in config/database.php and .env.example:
   - mysql  -> database sld_portal (portal, read/write)
   - camsys -> database camsys (read-only user; the code must only SELECT)
   Import database/camsys/qapi_registration_mdl.sql into a local camsys database for development.
3. Set app timezone Asia/Kuala_Lumpur, locale en, currency RM.
4. Design import from Google Stitch:
   Project "QIU SLD Campus Hub", ID 15093627814513825483. Screen IDs:
   ce4851ae75614552ba4590d14006aac5, 07725871904e46b7b37734ecd5c4f398, 7d39bae2d15b436a8707e24ad4cbbfb9, 13520727798b4e4db87f1c710e151fb0, 9e2e48fa8afb4bf4b69e52dc1d7cb258.
   If a Stitch MCP server is available, use it to get each screen's image and HTML/code and download the hosted URLs with curl -L into design/stitch/<screen-id>/ (screen.png, code.html). If no Stitch tool is available, stop this step and tell me, so I can export the screens into design/stitch/ myself.
   Then write design/DESIGN_NOTES.md: for each screen, what page it is, its components, colours, spacing, fonts.
5. Build the design system from the Stitch screens + CLAUDE.md section 8: tailwind.config.js tokens (colours, Poppins, radius, shadows), and base Vue components in resources/js/Components/ui/: Button, Input, Select, Textarea, Checkbox, Card, Avatar (with story ring), Badge, Modal, Drawer, Tabs, Table (sortable), FilterBar, EmptyState, Toast, QrCode, QrScanner, CameraCapture, SignaturePad, FileUpload, StepIndicator.
6. Layouts: AppLayout (mobile bottom tab bar: Feed, Search, Create, Notifications, Profile; desktop top bar), PortalLayout (sidebar for committee/advisor/SLD pages), GuestLayout (login).
7. Create the folder structure in the "Project structure" section of BUILD_PROMPT.md (empty classes with docblocks are fine).
8. Set up Pest, a GitHub-style CI script (composer test + npm run build), and make a first commit.

Done when: `php artisan serve` + `npm run dev` show a styled login page matching the Stitch look, and `php artisan test` passes.
```

---

## PHASE 1 — Foundation: login, camsys sync, roles, clubs, profiles (4 weeks)

```
Read CLAUDE.md. Do Phase 1.

A. Migrations (sld_portal, InnoDB, utf8mb4, FKs) for: users, students, staff, programme_abbrev, intake_map, settings, sessions_academic (name like 2026/2027, starts_on, ends_on), clubs, club_categories, role_assignments (user_id, role, scope_type system|club|event, scope_id, session_id, position, is_compulsory, starts_on, ends_on), audit_log, notifications. Plus spatie permission tables with teams.

B. Services (with Pest unit tests for every case):
   - StudentIdParser: parses QIU-YYYYMM-NNNN -> year, month, serial; accepts dummy STD########## with intake_map fallback; rejects anything else.
   - YearGroupCalculator: formula in CLAUDE.md section 4, capped at course duration, Foundation and Alumni cases. Test: intake 2024-04 on 2026-10-29 = Year 3.
   - SessionResolver: current session from server date (1 July rollover); test 2027-06-30 = 2026/2027 and 2027-07-01 = 2027/2028.
   - StatusMapper: PROGRAMME_STATUS -> active|alumni using settings (defaults in CLAUDE.md).
   - CamsysSync: SELECT only from camsys; upserts students with only the allowed columns; never copies sensitive columns (write a test that fails if they appear).

C. Auth:
   - Google login (Socialite), hd=qiu.edu.my, server check of domain. Match E_MAIL in camsys (case-insensitive) -> create/update user + student, store Google photo URL. Staff accounts: allowed if a staff row with that email exists. Otherwise show a clear "Not found in QIU records — contact SLD" page.
   - On every login refresh that student from camsys.
   - Schedule CamsysSync nightly at 02:00 and year-group recompute after it.

D. Roles and scoping: role_assignments drives permissions per club/event/session. Policies for Club, Event, Document. Rule: 4 compulsory positions (President, Vice President, Secretary, Treasurer), 4 different active students, before any submission. Tests.

E. Admin panel (SLD / Super Admin):
   - Clubs CRUD (name, slug, logo, category, established year, bio, instagram handle, advisor), committee assignment per session, staff accounts (by @qiu.edu.my email), "Transfer club to committee" action, take back.
   - programme_abbrev, intake_map, status mapping, sessions, CCA settings.
   - Super Admin: role management, audit log viewer, sync now button, sync log.

F. Club QR + lifelong profile QR:
   - Each club gets a permanent qr_token; /c/{token} -> club page; if not logged in -> login -> redirect back.
   - Each user profile gets a permanent qr_token; /p/{token} -> public profile view (portfolio fields only).

G. Pages (Vue, matching Stitch): Login, Not-found-in-records, My Profile (edit photo override, hide sections, link personal email), Public Profile, Club Page skeleton with header + Committee tab (session selector, committee cards with photo, name, position, course short form, year group; tap -> pop-up card -> View full profile), Admin pages above.

Seed: 6 demo clubs (incl. GPS Club "Student Consumer Movement" with handle gps_qiu), committees from the 10 dummy students, 3 advisors, staff approvers (Assistant Registrar, Registrar, Bursar, COO).

Done when: a dummy student logs in (use a dev-only login-as route guarded by APP_ENV=local), sees their year group correct, opens a club page by QR link, sees the committee; all tests pass. Commit.
```

---

## PHASE 2 — Social feed, events, proposals, approvals, attendance, indemnity (6 weeks)

```
Read CLAUDE.md. Do Phase 2.

A. Migrations: posts, post_media, post_likes, comments, saves, stories, highlights, highlight_items, albums, club_follows, club_faqs, events, event_committee, event_registrations, attendance, indemnities, emergency_contacts, documents, document_versions, approvals (step_no, role, approver_user_id, decision, comment, decided_at), signatures (user_id, position_at_time, signed_at, image_path, verification_code), cca_records.

B. Template engine (app/Services/Documents):
   - Make placeholder copies of each official template into resources/templates/filled/ using ${field} markers WITHOUT changing layout (write a script tools/templates/make_placeholders.php and keep a mapping file per template in resources/templates/maps/*.json listing every field and its source).
   - DocxRenderer (PhpWord TemplateProcessor with cloneRow for tables, setImageValue for signatures), XlsxRenderer (PhpSpreadsheet writing into the template cells), PdfConverter (LibreOffice headless), all queued.
   - Rebuild the Indemnity Form PDF as a DOCX with the identical layout (QIU logo, black title bar, the same table rows and the full consent text) then render to PDF.
   - Golden-file tests: render each template with sample data and assert every placeholder was replaced and the table row counts are right.

C. Approval engine (app/Services/Approvals): ordered chains from CLAUDE.md section 6; Approve / Return with comment / Reject; resubmit keeps earlier approvals unless budget or date changed; approver inbox with due dates; email + in-app notifications; every action to audit_log. Digital signatures with SignaturePad; PDF shows name, position, signature, server timestamp, verification code; /verify/{code} page.

D. Event proposal form (06 template) — every field from the template: To (COO from staff table), From (club, auto), submission date (server), event date/time, name, summary, nature checkboxes, CCA hours (participants/committee), duration, venue, mode, 17 SDG checkboxes, collaboration, total budget, source of fund; then Introduction, Objectives, Target participants, Proposed activities table, Itinerary table, ICT & maintenance, Logistics, Event committee & responsibilities (from event_committee, locked), Estimated budget table with revenue/expenses/profit-loss and the "revenue must cover expenses" warning, Conclusion, signature blocks. Size rule date check. Faculty event toggle adds Dean step. Outdoor/off-campus toggle.
   Event committee creation requires the 4 compulsory positions.
   On final approval: auto-create feed post (club profile, poster, title, date/time/venue, Register button), record CCA hours, create Drive-like document folder checklist, unlock fund withdrawal and QR attendance.

E. Poster: upload with checklist validation (CLAUDE.md poster rules) and a simple poster builder using the A–F layout zones with the Student Activity logo auto-placed.

F. Registration + attendance: Register button; per-event signed, expiring clock-in/clock-out QR; scanner page for committee (html5-qrcode); manual add by committee; generates 03 Attendance List XLSX (Participant sheet, Committee sheet with CCA points, Pictures sheet with uploaded photos).

G. Indemnity: required when outdoor/off-campus; form auto-fills student and event fields (read-only), student fills emergency contact (saved to emergency_contacts and pre-filled next time), alternate contact, consent tick, signature; closes 3 days before event; QR clock-in blocked without it; committee/advisor see signed/not-signed list and download all as one PDF; SLD sees all.

H. Feed + club page tabs: feed (followed clubs first, then newest; SLD pinned posts), like/comment/save/share, report; stories (24 h, story bar at top of feed, ring on avatar), highlights, gallery albums, Events tab, FAQ tab, follow button, recap post with carousel after event. SLD moderation screen.

I. Post-event checklist with deadlines (1 week finance + receipts, 2 weeks all documents) and automatic reminders.

Done when: a full event runs end to end in the demo — proposal through all 6-7 signers, auto post appears in feed, students register, indemnity signed, QR attendance, attendance XLSX downloads in the official layout; tests pass. Commit.
```

---

## PHASE 3 — Membership, payments, finance, meetings, minutes, post mortem, venues (5 weeks)

```
Read CLAUDE.md. Do Phase 3.

A. Migrations: memberships (student, club, status pending|active|grace|terminated|alumni, starts_on, expires_on, grace_ends_on, alumni_since), club_fees (club, session, amount_sen, payment_url, approved_by), membership_drives (club_id nullable = all, starts_at, ends_at, set_by), membership_payments (method online|cash, amount_sen, reference, receipt_path, ai_amount, ai_date, ai_reference, ai_payee, ai_confidence, status auto_confirmed|pending_review|confirmed|rejected, verified_by, verified_at), cash_handovers (payment_id, committee_user_id, status requested|accepted|declined|student_ready|committee_ready|photo_taken|completed|expired, one_time_qr_token, photo_path, timestamps for each step), saved_views, club_opening_balances, ledger_entries, receipts, fund_requests, fund_request_items, payees (bank details encrypted), meetings, meeting_attendance, minutes, post_mortems, venue_bookings.

B. Join flow (all rules in CLAUDE.md "Club membership"): Join button with fee; RM0 = instant; paid = non-refundable / no-exit acknowledgement tick, then Online or Cash.
   - Online: open club payment link, upload receipt, queued AI ReceiptReader job extracts amount/date/reference/payee with confidence; auto-confirm only if all match; else Treasurer review queue showing image + AI reading side by side; reject duplicate reference; flag > 7 days old.
   - Cash: the full handover state machine with two live screens (student and committee member). Use polling or Laravel Reverb/websockets for the "both ready" and "both done" steps. Student screen shows a one-time QR; committee CameraCapture photo must decode that QR (verify server-side with a QR decoder on the photo); confirm or retake; both tap Transaction done; membership active; ledger income "Membership fee (cash) – collected by <name>"; receipts to both. Expire after 7 days.
   - Membership drives: SLD sets date-time windows; during a drive the club is featured and Join highlighted; joining is never blocked outside.
   - Lifecycle job (daily 00:10): active -> grace at expires_on, reminders day 0/3/6, grace -> terminated after 7 days, any -> alumni when camsys status maps to alumni. Unit tests for every transition and date boundary.
   - Alumni stay members; election setting "allow alumni to vote" per election (used in Phase 4).

C. Members list for committee/advisor/SLD: search, every filter in CLAUDE.md, multi-level sort, saved views, export Excel and PDF, reminder to unpaid. Defaults to current session. Dashboard cards: total, active, grace, terminated, alumni, paid, pending, unpaid. Generate 10 Full Member List XLSX from active members.

D. Finance: SLD enters opening balance first (finance locked until then); ledger mirrors 02 Finance Record (Date, Description, Income, Expenses, running Balance, Prepared by Treasurer, Reviewed by Advisor); receipt required for expenses; export XLSX in the official layout; SLD all-clubs balance screen.
   Fund withdrawal form (07 template): From club, date, event, items pulled from approved budget, payment method per item, multiple payees (treasurer and vendor accounts), the petty-cash paragraph, both signature tables; deadlines enforced.

E. Meetings: create meeting -> Google Calendar API event with Meet link on the organiser's own QIU account (incremental OAuth for calendar scope), invite committee. Minutes form (13 template): club, date, venue, time, purpose, summary bullets, action items table (task, PIC, deadline or N/A), next meeting, prepared by / President / Advisor signatures. AI MinutesDrafter from bullet notes. Weekly CCA claim = minutes + attendance both required.

F. Post mortem (09 template): auto header (event, club, date, time, venue, actual participants from attendance, committee list), A–G rows with What went well / Challenges / Improvement (N/A allowed), recommendations 1.0–3.0+, signatures. AI suggestions. XLSX + PDF.

G. Venues: La Place booking (form, email to SLD with purpose, items, activity; SLD confirm in app; show table-usage rules), semester permanent venue request, one-off big room (>= 1 week ahead).

H. Club status job: Active / At-risk / Inactive from last activity date; badge on club page; SLD list.

Done when: a student joins a free club, a paid club online (auto-confirmed), and a paid club by cash with two browser windows completing the handover; lifecycle tests pass across 365 + 7 days using time travel; finance and fund withdrawal export in official layouts. Commit.
```

---

## PHASE 4 — AGM, elections, Annual Activities Report (5 weeks)

```
Read CLAUDE.md. Do Phase 4.

A. Migrations: agm_periods, agms, agm_attendance, elections (allow_alumni_vote), election_positions, candidates, votes (unique election_position_id + voter_id), report_themes, generated_files.

B. AGM period: Admin opens/closes periods (start, AGM deadline, report deadline). Module locked outside; countdown on club dashboards.

C. AGM scheduling: date, time, venue, Meet link (organiser's account), QR attendance.

D. AGM Report form (01 template) with every rule from CLAUDE.md: locked header and signer names, server dates, AI objectives (3 drafted, + button, minimum 2 enforced server-side), computed attendance sentence, attendance table ordering (Advisor, attended, then absent below), alumni excluded, AI opening speech and agenda, locked current committee, election and appointment sections generated, adjournment. Render DOCX + PDF in the official layout. Tests for counts, ordering and the minimum-2 rule.

E. Elections: nomination window, candidate statements with photo, voting window, eligibility (active members; alumni only if allow_alumni_vote), one vote per position, compulsory positions must have candidates, uncontested flag for SLD, full-term check against expected graduation date, results page, results feed into AGM 6.0/7.0, automatic role handover on 1 July (scheduled job).

F. Annual Activities Report: python-pptx script in tools/pptx/annual_report.py that opens the official 12 template and produces: cover with club name, summary table (No, Event Name, Event Date) with as many rows as events, then per event a details slide (Event Name, Date, Time, Venue, Details) and an Event Photos slide with up to 6 photos laid out in a grid. Themes (colours, fonts, background) applied from report_themes. AI writes each Details paragraph (editable before generating). Laravel calls the script via Symfony Process, then LibreOffice for PDF. Download PPTX and PDF.

G. AGM package submission: AGM Report + Financial Report + Full Member List + Annual Activities Report submitted together to SLD; SLD checklist and verify screen.

Done when: a demo club runs an AGM inside an open period, holds an election, and downloads a correct AGM Report DOCX/PDF and Annual Activities Report PPTX/PDF; tests pass. Commit.
```

---

## PHASE 5 — Dashboards, reminders, hardening, pilot (3 weeks)

```
Read CLAUDE.md. Do Phase 5.

1. SLD dashboard: clubs by status, approvals waiting per approver, documents overdue, events this month, AGM completion per club, membership and payment totals, club balances. Approver dashboards for each staff role.
2. Reminders: all deadlines in CLAUDE.md (proposal lead time, indemnity 3 days, post-event 1 and 2 weeks, membership grace, AGM deadlines, reply to SLD within 7 working days).
3. Lifelong profile: CCA record, positions history, certificates (auto-generated PDF certificates for committee roles and event participation), alumni personal-email linking.
4. PWA: manifest, icons from public/brand, offline shell, camera permissions for QR and cash photo.
5. Security pass: policies on every route, rate limits on login/payments/votes, file upload validation (type, size, image re-encode), encrypted bank details, signed URLs for private files, CSRF, no sensitive camsys fields anywhere (test), audit log coverage test.
6. Template fidelity check: render every official template with full sample data, convert to PDF, and save to storage/qa/ for side-by-side comparison with the originals; list any differences.
7. Performance: indexes on all FK and filter columns, eager loading, cached feed queries.
8. Write docs/ADMIN_GUIDE.md and docs/COMMITTEE_GUIDE.md in plain English.

Done when: all tests pass, the QA PDFs match the templates, and the pilot checklist in docs/PILOT.md is ready for 3 clubs. Commit.
```

---

## Project structure (Phase 0 creates this)

```
sld-portal/
├── CLAUDE.md
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/GoogleController.php
│   │   │   ├── FeedController.php, StoryController.php, PostController.php
│   │   │   ├── ClubController.php, ClubQrController.php, ProfileController.php, PublicProfileController.php
│   │   │   ├── MembershipController.php, PaymentController.php, CashHandoverController.php
│   │   │   ├── EventController.php, ProposalController.php, AttendanceController.php, IndemnityController.php
│   │   │   ├── MeetingController.php, MinutesController.php, PostMortemController.php
│   │   │   ├── FinanceController.php, FundRequestController.php, VenueBookingController.php
│   │   │   ├── AgmController.php, ElectionController.php, AnnualReportController.php
│   │   │   ├── ApprovalController.php, VerifyController.php
│   │   │   └── Admin/ (Clubs, Staff, Settings, Sessions, AgmPeriods, Drives, Themes, Moderation, Sync, Audit)
│   │   ├── Requests/            (one Form Request per form)
│   │   └── Middleware/EnsureQiuDomain.php
│   ├── Models/                  (one per table)
│   ├── Policies/
│   ├── Services/
│   │   ├── Camsys/              CamsysSync, StudentIdParser, StatusMapper
│   │   ├── Time/                SessionResolver, YearGroupCalculator
│   │   ├── Roles/               RoleService, CompulsoryPositionsCheck
│   │   ├── Documents/           DocxRenderer, XlsxRenderer, PptxRenderer, PdfConverter, TemplateMap
│   │   ├── Approvals/           ApprovalEngine, Chains
│   │   ├── Membership/          JoinService, MembershipLifecycle, ReceiptVerifier, CashHandoverService
│   │   ├── Finance/             Ledger, FundRequestBuilder
│   │   ├── Events/              ProposalRules, AttendanceQr, IndemnityService
│   │   ├── Agm/                 AgmReportBuilder, ElectionService, AnnualReportBuilder
│   │   ├── Google/              CalendarMeetService
│   │   └── Ai/                  AiClient, ObjectiveDrafter, SpeechDrafter, AgendaDrafter, MinutesDrafter, ProposalDrafter, PostMortemAdvisor, ReceiptReader, PosterChecker, EventDetailsWriter
│   ├── Jobs/                    RenderDocument, ConvertToPdf, ReadReceipt, SyncCamsys, RunMembershipLifecycle, SendReminders, HandOverCommittees
│   └── Console/Kernel schedule (or routes/console.php in Laravel 11)
├── database/
│   ├── camsys/qapi_registration_mdl.sql
│   ├── migrations/
│   └── seeders/ (DemoClubsSeeder, StaffSeeder, SettingsSeeder)
├── resources/
│   ├── templates/
│   │   ├── original/            official files, never edited
│   │   ├── filled/              placeholder copies (${field})
│   │   └── maps/                one JSON per template: field -> source
│   └── js/
│       ├── Components/ui/       design system
│       ├── Components/feed/     PostCard, StoryBar, StoryViewer, Carousel
│       ├── Components/club/     ClubHeader, CommitteeBoard, ProfilePopCard, SessionSelector, Gallery, FaqList
│       ├── Components/forms/    LockedField, SignerBlock, DynamicTable, AiDraftField, SdgPicker, BudgetTable
│       ├── Layouts/             AppLayout, PortalLayout, GuestLayout
│       └── Pages/
│           ├── Auth/            Login, NotFoundInRecords
│           ├── Feed/            Index
│           ├── Clubs/           Show, Members, Finance, Documents
│           ├── Profile/         Me, Public
│           ├── Membership/      Join, PayOnline, CashHandover (Student, Committee), ReviewQueue
│           ├── Events/          Proposal, Show, Attendance, Scanner, Indemnity, PostMortem
│           ├── Meetings/        Index, Minutes
│           ├── Finance/         Ledger, FundRequest
│           ├── Agm/             Index, Report, Election, AnnualReport
│           ├── Approvals/       Inbox, Review
│           ├── Verify/          Show
│           └── Admin/           (all admin pages)
├── tools/
│   ├── pptx/annual_report.py    (python-pptx)
│   └── templates/make_placeholders.php
├── design/
│   ├── stitch/<screen-id>/      screen.png, code.html
│   └── DESIGN_NOTES.md
├── public/brand/
├── docs/ (reference, ADMIN_GUIDE.md, COMMITTEE_GUIDE.md, PILOT.md)
└── tests/ (Unit for every Service rule, Feature for every flow)
```

---

## If something goes wrong mid-phase

Paste:
```
Read CLAUDE.md. Check git log and the current state of the code. Tell me which steps of Phase <N> are done, which are not, and which tests fail. Then continue from the first unfinished step.
```
