<div align="center">

<img src="public/brand/student-activity-black.png" alt="QIU Student Activity" height="72">

# QIU SLD Campus Hub

**The Student Life Division portal for Quest International University (QIU), Ipoh.**

An Instagram-style home for clubs and student life, plus a back office that turns every official SLD template into a
web form, routes it through digital approval chains, and prints it back in the exact official layout.

[**Live demo →**](https://qiu-sld-campus-hub.onrender.com)

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Tests](https://img.shields.io/badge/tests-2%2C800%2B_Pest-F28D1A)
![Render](https://img.shields.io/badge/deployed_on-Render-46E3B7?logo=render&logoColor=white)

</div>

> Core principle: **nobody types a fact the system already knows.** Identity, programme, year group, positions, club and event
> details come from university records and are locked.

## How it works

### An event, from idea to archive

```mermaid
flowchart LR
    A[Committee drafts proposal<br/>live, autosaved] --> B[Preparer signs]
    B --> C[Advisor signs]
    C --> D{SLD}
    D -- needs Dean / COO / Registrar --> P[Export · sign on paper<br/>upload scan · tick signers]
    P --> E
    D -- approves --> E[Approved 🎉]
    D -- marks parts --> R[Returned to fix] --> B
    E --> F[Posted to the feed<br/>followers notified]
    E --> G[Registration · tickets · QR attendance]
    G --> H[Completion package<br/>photos · post mortem · finance]
    H --> I[SLD tracker]
```

A late proposal (under the 14-day lead time) or a budget deficit needs a written reason, and SLD must approve it explicitly. That decision is recorded with the signature.

### A club's year

```mermaid
flowchart LR
    N[New club application<br/>President → Advisor → SLD] --> O[Club opens with<br/>committee and advisor]
    O --> W[Weekly activities<br/>minutes + 3 photos]
    O --> EV[Events]
    W & EV --> Y[AGM: year in review<br/>report · election · package]
    Y --> K[SLD acknowledges<br/>archive ZIP built]
    K --> HO[SLD starts handover<br/>new committee: view-only training]
    HO --> T[Overlap ends: new committee in charge<br/>old committee thanked + certificates]
```

### Money

```mermaid
flowchart LR
    M[Membership payment<br/>receipt checked once across all clubs] --> L[(Club ledger)]
    WD[Fund withdrawal<br/>Treasurer → President → Advisor → SLD] --> FI[Finance transfers<br/>within 14 days, else flagged]
    FI --> BI[Treasurer uploads bank-in] --> SC[SLD confirms] --> L
    PU[Event purchases<br/>receipt read by AI] --> L
```

### Architecture

```mermaid
flowchart TB
    subgraph Browser
        V[React 19 + Inertia · TypeScript<br/>installable PWA]
    end
    subgraph Laravel
        C[Thin controllers + form requests] --> S[Services: business rules]
        C --> PO[Policies: every permission server-side]
        S --> Q[Queued jobs: AI drafting, archives, PDFs]
    end
    V <--> C
    S --> DB[(MySQL / SQLite)]
    S --> CS[(camsys: read-only<br/>student records)]
    S --> ST[Private storage<br/>signed, expiring links]
    Q --> AI[Claude: drafting, receipts, assistant]
    Q --> LO[LibreOffice · PhpWord · python-pptx<br/>official template output]
    C --> G[Google: sign-in, Calendar, Meet]
```

## Features

**Social (students)**
- Club profiles with logo, category, bio, Instagram, permanent club QR
- Committee tab with session selector: photo, position, course short form, year group; tap for profile card
- Lifelong student profile with a personal permanent QR (portfolio fields only)
- News feed: last 7 days in full, older posts folded into 8–14 days, months and years; followers notified of new posts and events; likes and comments (who liked, who commented) notify the committee
- Feed hygiene: bad-word and insult filter (English, Malay, Manglish, leet/spaced spellings), per-club and per-event posting limits, no repeat captions or comment floods
- Committee year timeline on the club page

**Portal (committees, advisors, SLD)**
- Google sign-in restricted to `@qiu.edu.my`, matched against the university student system (camsys)
- Nightly camsys sync (read-only) with automatic year group and academic session rollover (1 July)
- Scoped roles: club committee and advisor per session; President, Vice President, Secretary and Treasurer compulsory before any submission
- SLD admin: dashboard, clubs, committees, staff accounts, transfer club to committee, requests to clubs (7-working-day replies), settings, audit log
- Daily reminders for every deadline (approvals, proposal lead time, indemnity, post-event documents, membership grace, AGM, replies to SLD)
- Lifelong profile with CCA, positions, verifiable PDF certificates; alumni sign in with a linked personal email
- Installable PWA (manifest, offline shell); photos re-encoded on upload; private files behind signed, expiring links
- Event proposals with approval chains and e-signatures, QR attendance, indemnity, posters, post mortem (09)
- Membership and payments (online receipt check, cash handover), finance ledger (02) and fund withdrawal (07)
- One receipt pays once: screenshot fingerprint and bank transaction ID checked across every club; sender name checked
- Transaction IDs `CLUB_EVENT|MEMBERSHIPDRIVE-<drive>|NORMALREGISTER_MATRIC_NAME_YYYYMMDD_HHMM_TRANSACTION`; SLD finds any transaction by any parts as they type
- Finance workbook (Excel/PDF): Overview, then membership drive, normal registration, each event, other; this session or all years; pick the sheets
- Event purchases and claims: snap the receipt (AI reads items and prices), budget vs spending, helpers claim back, event committee verifies, pay back by cash (QR photo) or transfer (name checked)
- Outdoor events: indemnity pre-filled, sign once registered or paid, ticket appears after signing
- Meetings with Google Meet, minutes (13) with AI drafting, weekly CCA. Committee meetings invite the committee and advisors only; weekly activities invite the committee or all members, with expected pax and location, and everyone invited is notified
- Meeting check-in / check-out by rotating QR (like events): only the meeting's audience can check in (committee + advisors, or all members), with times kept per person
- Meetings report for each club and for SLD (every club), with attendance rate and minutes status, exported to Excel/PDF
- Membership grace, termination and alumni managed by the committee or SLD: remind, give more days, end with a reason, mark alumni, undo; members are notified
- Venue booking (La Place, semester venues, big rooms) with SLD confirmation; daily club status (Active / At risk / Inactive)
- AGM periods set by SLD, AGM scheduling with Meet link and rotating-QR attendance, AGM Report (01) with AI-drafted objectives/speech/agenda and computed attendance
- Elections: nominations with full-term check, one vote per position, SLD flags, results into the AGM Report, role handover on 1 July
- Annual Activities Report (12) as PPTX/PDF from the official template, AI-written event details, SLD themes; AGM package checklist
- Approval routes per the spec: proposals Preparer → Advisor → SLD (final); withdrawals Treasurer → President → Advisor → SLD, then Finance transfers and the Treasurer uploads the bank-in receipt
- SLD rules editable in Settings: proposal lead time (14 days), minimum photos (3), Finance transfer deadline (14 days), handover overlap (7 days). A late proposal or a budget deficit needs a reason that SLD explicitly approves, and the approval is recorded
- Evidence photos required for weekly activity reports, AGMs and finished events. Event photos download as a ZIP named `Club_Event_YYYYMMDD_01.jpg`
- After SLD acknowledges the AGM, a yearly archive ZIP is built (AGM, finance, members, annual report, events, weekly reports, photos), visible only to the club and SLD
- Handover started by SLD after the acknowledged AGM: the incoming committee gets view-only training access during the overlap
- Event proposal form: live gauge of the compulsory parts; Sign & submit jumps to the first problem (flashes red) and then to each next one (blinks until touched); workshops list speakers (photo required) and need only 2 persons in charge; at least 2 objectives; collaboration with partner clubs or typed-in partners; several trips, each departure → places on the way (last = drop-off) → return, all within the event time; extra headings, paragraphs, bullets, tables and pictures (printed centred, fitted for A4) after the conclusion; unticked ICT prints "Not required"; the export adds a committee chart with student and employee IDs
- Clubs you run → All events: ongoing, upcoming and past events, with past events by year
- SLD Proposals & exports: every proposal as PDF or Word, the list as Excel/PDF, and one ZIP per event with all its documents and finance lines
- My Activities: each club's requests by stage (Draft, Submitted with approval progress, Approved, Rejected); rejected requests clear after 30 days or by hand. Approvers, Treasurers (receipts to check) and the committee (proposal submitted) are notified
- SLD submissions tracker: weekly reports missing by month, incomplete event completion packages, AGM state, overdue transfers. Finance has monthly and yearly income/expense charts for one club or all clubs
- One calendar: approved events plus my meetings, weekly activities, weekly-report due dates and AGMs, with a whole-year view
- New-club registration: the proposed President applies and signs → the advisor signs → SLD approves. The club, its committee and its advisor only exist after approval
- Role-aware assistant: answers only from records the person may open, links to them, and keeps a private history the person can delete
- Report and long forms save drafts automatically (on the server for reports, on the device for new requests); every submission ends with one sign-and-confirm step
- Higher-authority signatures on paper at SLD's step: choose the signatories, export the official document, upload the signed scan and tick who signed. The final approval opens only when everyone is ticked, and it ends with an approval animation
- AGM "year in review" compiled automatically for that session only: events with turnout and completion state, weekly activities, money by month and category, and every AGM document
- When the handover overlap ends, the outgoing committee loses access and each member gets a thank-you and their committee certificate
- SLD views a club's members and committee fee status (paid, not paid, being checked) inside the admin portal; advisors and committees see the same on the club page
- Light and dark mode belong to each account, never the device, so a shared phone never shows the previous person's theme
- Moderation for posts, comments and assistant questions: a word filter that sees through disguises (f_u_c_k, f*u*c*k, fvck, run-together words) with English, Malay, Manglish and dialect slang, then the [toxic-comment model](https://huggingface.co/martin-ha/toxic-comment-model) (set `HF_TOKEN`). Only SLD and the club's committee can delete comments
- The assistant answers by retrieval from role-scoped facts: fees and approved events for everyone; a club's money, payments and reports only for that club's committee and advisor; everything for SLD. It suggests related follow-up questions and asks when a request is unclear

## Tech stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | React 19 (strict TypeScript) + Inertia.js, Tailwind CSS 4, Vite |
| Database | MariaDB / MySQL (production), SQLite (local) |
| Auth | Google OAuth (Laravel Socialite), spatie/laravel-permission |
| Documents | PhpWord, PhpSpreadsheet, python-pptx, LibreOffice headless |
| Tests | Pest |

**Backend choice vs the original tech spec.** The spec named a Python backend with MySQL. This project uses Laravel (PHP) with MySQL/MariaDB in production and SQLite locally. It covers the same requirements:
- The same relational database design.
- The same REST-style endpoints, served through Inertia.
- Background jobs and a daily scheduler.
- Python is still used where it is the better tool (`python-pptx` for the annual report).

Rewriting the backend in Python would change nothing for users and would put the tested behaviour at risk (over 1,600 automated tests), so no rewrite is planned.

## Getting started

Requirements: PHP 8.3+, Composer, Node 20+, LibreOffice (PDFs), Python 3 with python-pptx (Annual Activities Report: `python3 -m venv tools/pptx/.venv && tools/pptx/.venv/bin/pip install python-pptx`).

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite database/camsys.sqlite
php artisan camsys:import-dump          # local camsys copy from the sample dump
php artisan migrate --seed              # roles, lookups, staff, 6 demo clubs
composer run dev                        # or: php artisan serve + npm run dev
```

- **Local sign-in without Google:** open `/dev/login-as` (registered only when `APP_ENV=local`).
- **Super Admin:** add your email to `SUPERADMIN_EMAILS` in `.env`.
- **Google sign-in:** create an OAuth client (Web) in Google Cloud, set the redirect URI to `${APP_URL}/auth/google/callback`, then fill `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.

## Tests

The automated suite (Pest, about 1,600 feature and unit tests covering roles, payments, signatures, attendance and
security boundaries, plus frontend contract tests) is kept in the private development copy and is not published.
Before every release: `php artisan test --parallel` on SQLite and on MySQL, then `npm run build`
(strict `tsc --noEmit` + Vite).

## Database

MySQL schema, tables, keys and an ER diagram: [docs/DATABASE.md](docs/DATABASE.md).

## Deploying

- **Exabytes (Plesk)**: `scripts/package-plesk.sh` builds `qiu-sld-campus-hub-plesk.zip` (built frontend, production
  packages, clean-install SQL, no demo data). Steps: [docs/DEPLOY_PLESK.md](docs/DEPLOY_PLESK.md).
- **Render / any Docker host**: the repository `Dockerfile` (includes LibreOffice and python-pptx).

Starting accounts on a clean install: Super Admin `ai@qiu.edu.my`; SLD Officer `yeeshiu.chong@qiu.edu.my` (Admin),
who can add and remove other SLD admins under **SLD Admin → Roles**. Only a Super Admin can grant Super Admin.

## Security and privacy

Everything sensitive stays on the server:

- **Secrets** (Google client secret, AI keys, DB credentials) live only in `.env`, read via `config()`. Only `VITE_APP_NAME` is exposed to the browser.
- **camsys is read-only:** the sync issues `SELECT` only and copies an allow-list of columns. IC/passport, address, religion, race, CGPA and scores are never read (enforced by tests).
- **Personal data:** student phone numbers are encrypted at rest; phone and email are hidden from serialisation; public profiles show portfolio fields only.
- **Authorisation** is enforced server-side (policies, form requests, role middleware). The UI never decides access.
- **AI calls** run server-side in queued jobs only, with minimal data.
- Sign-in is rate-limited, every feature has its own limit, and every request has a site-wide ceiling (per person or IP); every role, committee and membership change is written to the audit log.
- **Browser hardening:** a strict Content-Security-Policy (scripts only from this site, by nonce), no framing, HSTS, no-sniff, server version hidden; hidden files and scripts in the uploads folder are never served.
- **Demo login** (`DEMO_LOGIN`) is for testing only. On a hosted server it is locked behind a passcode (`DEMO_LOGIN_PASSCODE_HASH`, a bcrypt hash) and the account list is never sent before it is entered. Keep it off in production.
- The sample `database/camsys/*.sql` is **dummy data**. Never commit real student records.

## Roadmap

- [x] Phase 0: Laravel setup, design system, layouts
- [x] Phase 1: Login, camsys sync, roles, clubs, profiles
- [x] Phase 2: Feed, events, proposals, approvals, attendance, indemnity
- [x] Phase 3: Membership, payments, finance, meetings, minutes, post mortem, venues
- [x] Phase 4: AGM, elections, Annual Activities Report
- [x] Phase 5: Dashboards, reminders, hardening, pilot

Guides: [SLD admin](docs/ADMIN_GUIDE.md) · [Committees](docs/COMMITTEE_GUIDE.md) · [Deploy on Plesk](docs/DEPLOY_PLESK.md) · [Database](docs/DATABASE.md)

## Project layout

```text
app/Services/            business rules (Camsys, Time, Roles, Profiles, ...)
app/Http/                thin controllers, form requests, middleware
database/camsys/         camsys sample dump (dummy data)
resources/templates/     original/ official SLD templates (never edited), filled/, maps/
resources/js/            Components/ui design system, Layouts, Pages
public/brand/            QIU Student Activity logos and guides
tests/                   Pest unit + feature tests
```
