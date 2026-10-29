# QIU SLD Campus Hub — Project Memory

Read this file fully before doing any work. Coding standards and QA: @AGENTS.md. Phase prompts: `docs/BUILD_PROMPT.md`. This file holds every decision already agreed with the Student Life Division (SLD). Do not re-decide anything written here; if something is missing, ask.

---

## 1. What we are building

A web portal for **Quest International University (QIU) Student Life Division** with two faces on one database:

1. **Social face (Instagram-style):** news feed, club profiles, stories, highlights, gallery, lifelong student profiles with personal QR codes.
2. **Portal face (back office):** every official SLD template turned into a web form that prints back into the **exact official layout** (DOCX / XLSX / PPTX / PDF), with digital approval chains.

Core principle: **nobody types a fact the system already knows.** Identity, programme, year group, positions, club and event details are auto-filled and locked.

---

## 2. Tech stack (fixed)

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ (Laravel 11 is end-of-life with unpatched advisories; switched 2026-10-29) |
| Database | MariaDB 10.4+ / MySQL 8, InnoDB, utf8mb4. Portal schema: `sld_portal`. camsys is a separate, **read-only** connection. Local dev: SQLite files for both |
| Frontend | Vue 3 (TypeScript) + Inertia.js + Tailwind CSS 4 (tokens in `resources/css/app.css` `@theme`), Vite. Mobile-first, installable PWA |
| Auth | Google OAuth via Laravel Socialite, restricted to `@qiu.edu.my` (`hd` param + server check) |
| Roles | spatie/laravel-permission with teams (scope = club or event) |
| DOCX | phpoffice/phpword `TemplateProcessor` (`setValue`, `cloneRow`, `cloneBlock`, `setImageValue`) |
| XLSX | phpoffice/phpspreadsheet, opening the official template and writing cells |
| PPTX | Python 3 + python-pptx script in `tools/pptx/`, called from Laravel via `Symfony\Process` |
| PDF | LibreOffice headless (`soffice --headless --convert-to pdf`) so PDFs match templates exactly |
| QR | simplesoftwareio/simple-qrcode (generate), html5-qrcode (scan in browser) |
| Google Meet | Google Calendar API with `conferenceData`, using **each organiser's own** QIU Workspace OAuth token (calendar scope requested on first use) |
| AI | Server-side LLM API calls inside queued jobs only. Never from the browser. Send minimum data (never IC, address, phone, payment details) |
| Queue / schedule | Laravel queue (database driver to start) + scheduler |
| Storage | Laravel filesystem (local in dev, S3-compatible in prod). Thumbnails generated on upload |
| Tests | Pest. Every business rule in section 6 has a test |

---

## 3. The camsys source database (read-only)

Table `camsys.qapi_registration_mdl` (MyISAM, no primary key, dates stored as varchar). Columns used by the portal:

`STUDENT_NUMBER, STUDENT_NAME, GENDER, STUDENT_MOBILE_NO, E_MAIL, PROGRAMME_STATUS, PROFILE_STATUS, PROGRAMME, PROGRAMME_CODE, TYPE_OF_PROGRAMME, YEAR_ENTRY, INTAKE_NUMBER, COURSE_DURATION, GRADUATED_DATE`

**Never copy** IC_PASSPORT, ADDRESS, RELIGION, RACE, CGPA, SCORE, DATE_OF_BIRTH or any other sensitive column into the portal.

Confirmed facts and fixes:

1. **Student ID** real format: `QIU-YYYYMM-NNNN` (e.g. `QIU-202404-0001` = April 2024 intake). The dummy dump uses `STD20240001`; the parser must accept both in dev. Regex for real IDs: `^QIU-(\d{4})(0[1-9]|1[0-2])-(\d{4})$`.
2. **Intake month comes from the student ID**, not `INTAKE_NUMBER` (that column is a sequence 1, 2, 3). Fallback for dummy IDs: `intake_map` table (intake number → month) editable by Admin.
   QIU has **3 intakes a year: March, July, November** (3 semesters per year). Default `intake_map`: 1 → March, 2 → July, 3 → November.
3. **`E_MAIL` is the student's @qiu.edu.my Google email** in the real data (dummy shows example.com). Google login matches `E_MAIL` exactly (case-insensitive). Reject logins that are not @qiu.edu.my or not found in camsys (staff accounts are the exception, see section 5).
4. **`is_active` generated column is wrong** (checks for 'Active' but data says 'Enrolled'). Ignore it. Status mapping, stored in `settings` and editable by Admin:
   - active: `Enrolled, Active, Deferred, Suspended`
   - alumni / inactive: `Graduated, Completed, Withdrawn, Terminated`
5. **`GRADUATED_DATE` is the expected end date**, not proof of graduation. Alumni = status first.
6. **No course abbreviation exists.** `programme_abbrev` table maps `PROGRAMME_CODE` → short form (e.g. BCS, MBBS), editable by Admin.

**Sync:** nightly scheduled job + refresh of the logged-in student on every login. Upsert into `students` by `student_number`.

---

## 4. Time rules (the year moves by itself)

- **Session** = 1 July to 30 June, labelled `2026/2027`. Current session is computed from the server date (`Asia/Kuala_Lumpur`). Rolls over automatically every 1 July. Never hard-code a year.
- **Year group** = `floor(((today.year − entry.year) × 12 + (today.month − intake.month)) / 12) + 1`, capped at course duration. Graduated → `Alumni`. `TYPE_OF_PROGRAMME` = Foundation → `Foundation`. Recalculated nightly. Example: intake 2024-04, today 2026-10-29 → Year 3.
- Every list defaults to the current session; past sessions are read-only history.
- All signature and submission dates = **server date**, never client-provided.

---

## 5. Roles

Roles are **scoped**. One person can hold many scoped roles at once.

| Role | Scope | Notes |
| --- | --- | --- |
| Student | global | Every Google login matched to camsys |
| Club Committee | club + session | **Compulsory:** President, Vice President, Secretary, Treasurer (4 different active students). Optional extra positions allowed |
| Event Committee | one event | **Compulsory:** President, Vice President, Secretary, Treasurer. Optional extras. **Separate from club committee** — a club committee member is on an event committee only if chosen |
| Advisor | club | Staff |
| Staff | global | Approvers: Assistant Registrar, Registrar, Bursar, COO, Dean |
| Admin (SLD Officer) | global | |
| Super Admin | system | |

- **Staff/advisor accounts:** SLD keys in the club, committee and advisor once (creating staff accounts by @qiu.edu.my email), then **transfers** the club to its committee and advisor. SLD keeps oversight and can take it back.
- A club or event cannot submit any document until all four compulsory positions are filled.

---

## 6. Business rules (each needs a test)

### Forms (all)
- **Auto-fill on every form:** the logged-in user's name, student ID, programme, year group, QIU email, phone and their position in that club/event are filled and **read-only**. Club name, advisor, event details carry over from records.
- **Position-locked signers:** "Prepared by — Secretary" shows the current Secretary's name, read-only; only that account can sign. Same for President, Advisor, etc.
- **Signatures are digital only** (Registry, Bursar, COO included). The generated PDF shows signer name, position, signature image, server timestamp and a verification code/URL that opens the signed record.
- Form data stored as JSON per `document_versions` row; the official template is filled at render time. **Never change template layout**, only fill blanks.
- Approval engine: ordered steps, each tied to a role; actions Approve / Return (with comments) / Reject. Returned → club edits and resubmits.

### Approval chains
| Document | Chain |
| --- | --- |
| Event Proposal | Preparer → Advisor → (Dean, faculty events only) → SLD Officer → Assistant Registrar → Registrar → Bursar → COO |
| Fund Withdrawal/Transfer | Secretary → President → Advisor → Assistant Registrar → Bursar (endorse) → COO |
| AGM Report | Secretary → President → Advisor → SLD verify |
| Minutes, Finance Record, Attendance List, Post Mortem | Preparer → Advisor → SLD verify |

On final proposal approval: auto-create feed post, record CCA hours, unlock fund withdrawal and QR attendance.

### Events
- **CCA = event hours, 1 hour = 1 CCA.** The proposal pre-fills CCA from the event duration (participants and committee); the club can still edit it before submitting.
- Size rule: < 60 participants → submit ≥ 1.5 months before; > 60 → ≥ 3 months before. Date picker enforces it.
- Outdoor / off-campus flag → **indemnity required** from every participant and committee member; signing closes 3 days before the event. Without a signed indemnity, QR clock-in is blocked.
- Indemnity visibility: student sees own; event committee, club committee and advisor see signed / not-signed list and can download all as one PDF; SLD sees all.
- Post-event: finance record + receipts within 1 week; all documents within 2 weeks (Proposal, Poster, Participant list, Post Mortem, Survey, Reel). Reminders automatic.
- Poster checklist: Student Activity logo + club logo, organiser, title, tagline, date & day, time from–to, venue/platform, registration method, CCA points, contact name & phone. Real QIU photos only (no human or AI-generated people). Layout zones: A logos (top corners), B title, C date/time/venue, D description, E contact, F social handles.

### Club membership
- Every club has a permanent **club QR code**. Scanning → club page; not signed in → Google sign-in → back to club. Only signed-in camsys students can join.
- Students can **join any time**. Membership drives (start/end date-time set by SLD, all clubs or one club) only feature the club and highlight Join.
- **No refund, no exit.** Student ticks acknowledgement before paying. No Leave button.
- Fee per club per session set by Treasurer, approved by SLD. RM0 → one tap → active.
- **Online payment:** student pays via the club's pasted payment link, uploads receipt → AI reads amount, date, reference, payee → all match fee and club account name → auto-confirm. Anything unclear → Treasurer review queue (AI reading shown beside the image). Reject reused reference numbers; flag receipts older than 7 days; Treasurer can spot-check.
- **Cash payment:** student picks a committee member → member Accepts/Declines → student taps Ready to pay, member taps Ready to receive → student screen shows a one-time payment QR → member's camera takes one photo containing the QR and the cash → system verifies the QR belongs to this payment → member confirms or retakes → both tap Transaction done → membership active, ledger income recorded against that committee member, digital receipt to both. Request expires after 7 days.
- Membership lasts **365 days** from confirmation. Then **7-day grace** with reminders; renewing (paying) starts a new 365 days; no renewal by day 7 → **terminated**.
- Graduated in camsys → **Alumni** automatically (stays in club, no fee). Alumni vote only if the committee switches on alumni voting for that election.
- Members list (committee, advisor, SLD): search (name, ID, email, programme), combinable filters (session, status, position, joined range, expiring within N days, paid/pending/unpaid, method, collected-by, year group, programme, intake, events attended, CCA hours; SLD also club, category, club status), multi-level sort, saved views, export Excel/PDF.

### Finance
- SLD enters each club's **opening balance** (amount, as-of date, note) before the club can use finance. It becomes the ledger's first line "Opening Acc". Only SLD can change it; logged.
- Ledger = Finance Record template columns: Date, Description, Income, Expenses, Balance (running). Every expense needs a receipt upload (original receipts only; bank screenshots not accepted).
- Fund withdrawal line items come from the approved proposal budget; ≥ 2 weeks ahead for weekly activities, immediately after approval for events. Multiple vendors supported. Bank details stored encrypted.
- Proposal budget warns if revenue does not cover expenses.

### Club status
- Active / At-risk (no activity 2+ months) / Inactive (3+ months). At least one event per year. Reply to SLD within 7 working days (tracked on SLD requests).

### AGM (module locked outside the AGM period)
- Admin opens an **AGM period** (e.g. 1 Jul – 30 Sep, report due 9 Oct).
- AGM Report rules:
  - Session, organisation locked. Prepared by = Secretary, Checked by = President, Reviewed by = Advisor; names locked, dates = server date at signing.
  - 1.0 Objectives: AI drafts **3** from the whole session (minutes, proposals, post mortems, events). Editable. **+** adds more. Submission refused with fewer than **2**.
  - 2.0: "Our club has a total of N members, A members attended the AGM, N−A members absent" — all computed. Table: Advisor first, attended members (by position then name), then **absent members listed below**. Columns: No, Name, Position, Student ID, Email, Year Group, Program. Alumni excluded from counts.
  - 3.0 Opening speech and 4.0 agenda: AI drafts, editable.
  - 5.0 Current committee: pulled from roles, **not editable**.
  - 6.0 Election and 7.0 Appointment: generated from the election module.
- AGM package submitted together: AGM Report, Financial Report, Full Member List, Annual Activities Report.

### Elections
- Inside the AGM: positions, nomination window, voting window. Compulsory positions must have candidates; single-candidate positions flagged for SLD. One vote per position per voter (DB unique constraint). Committee must be able to serve the full July–June term (check expected grad date). Winners hand over roles on 1 July.

### Annual Activities Report
- Generated when the AGM period opens. Structure = official PPTX: cover ("Annual Activities Report / <Club>"), summary table (No, Event Name, Event Date), then per event a details slide (Event Name, Date, Time, Venue, Details) + an Event Photos slide. AI writes each "Details". Themes uploaded by SLD. Download PPTX and PDF.

### Social
- Feed: event auto-post on approval; recap posts with photo carousel; like, comment, save, share; followed clubs first; SLD pins and moderates.
- Club page: header (logo, name, established year, category, bio, Instagram handle, club QR, Join with fee), counts (active members, alumni, events, followers), **stories** (24 h) and **highlights**, tabs: **Committee (default)** with a session selector showing that year's committee + advisor + events & activities; Feed; Gallery (albums by event/session); Events; FAQ; Members and Documents (committee/advisor/SLD only). Tapping a committee card → pop-up profile card → **View full profile**.
- **Lifelong student profile:** photo, programme short form, year group/Alumni, clubs, positions per session, events, CCA hours, elections, certificates. **Personal permanent QR code**; anyone who scans can view the public profile. Never show IC, phone, address, payment data. Student can hide sections. Students link a personal email before graduating so alumni can still sign in.

### Meetings & minutes
- Committee or advisor creates a meeting → Google Meet from their own QIU calendar → invites committee. Secretary fills Minutes (template structure); AI drafts from notes. Weekly CCA claim needs Minutes + Attendance.

### Venue
- La Place booking: form + email to SLD (purpose, items sold/promoted, activity) → SLD confirms in app. Semester permanent venue requests; one-off big room ≥ 1 week ahead.

---

## 7. Official templates (in `resources/templates/original/`, never edited)

| File | Becomes |
| --- | --- |
| `01. AGM Template.docx` | AGM Report |
| `02. Finance Record.xlsx` | Club ledger export |
| `03. Event Attendance List Template.xlsx` | Attendance (sheets: Participant, Committee, Pictures) |
| `05. SLD Indemnity Form.pdf` | Indemnity (rebuild as DOCX with identical layout, then PDF) |
| `06. Event Proposal Template 052026.docx` | Event proposal / concept paper |
| `07. Student Organization Fund Withdrawal and Transfer Form template.docx` | Fund withdrawal |
| `09. Event Post Mortem Template.xlsx` | Post mortem |
| `10. Full Member List.xlsx` | Full member list |
| `12. Annual Activities Report.pptx` | Annual report |
| `13. Meeting Minutes Template.docx` | Minutes |
| `18. SOP to book place La Place.docx` | Process only (no file output) |

Placeholder copies live in `resources/templates/filled/` using `${field}` syntax. Fixed approver names currently printed on templates (Mr Nicholas Goh COO, Mr Muhammad MG Omar Registrar, Mr Khor Desmond Assistant Registrar, Ms Sumathi Ayadurai Bursar) must come from the `staff` table, not be hard-coded.

---

## 8. Brand and UI

- Logos in `public/brand/`: QIU Student Activity (black and white versions), QIU wordmark.
- Palette from the Student Activity logo: yellow `#FDDA1A`, cyan `#1CBDFD`, magenta `#E01CEB`, orange `#FD8F1A`; gradient text orange→coral and cyan→blue. Neutrals for UI. Font: Poppins.
- UI design reference: Google Stitch project **"QIU SLD Campus Hub"** (ID `15093627814513825483`), screens in `design/stitch/`. Match these screens; where a screen is missing, follow the same style.
- Mobile first; bottom tab bar on phones (Feed, Search, Create, Notifications, Profile); sidebar on desktop for portal pages.

---

## 9. Conventions

- Code: PSR-12, strict types, service classes for business rules (`app/Services/...`), thin controllers, Form Requests for validation, Policies for every model.
- Every sign, approve, return, edit of a submitted document, payment confirmation and role change writes to `audit_log`.
- Money in sen (integer), displayed as RM with 2 decimals.
- Timezone `Asia/Kuala_Lumpur` everywhere.
- Do not touch the camsys database except with `SELECT`.
- Run `php artisan test` after each phase; all tests must pass before moving on.
- Commit after each completed step with a clear message.
