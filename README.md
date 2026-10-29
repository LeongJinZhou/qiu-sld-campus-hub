# QIU SLD Campus Hub

**Student Life Division portal for Quest International University (QIU), Ipoh.**
An Instagram-style social face for clubs and student life, plus a back-office portal that turns every official SLD template into a web form that prints back in the exact official layout, with digital approval chains.

> Core principle: **nobody types a fact the system already knows.** Identity, programme, year group, positions, club and event details are pulled from university records and locked.

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white)
![Tailwind](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)
![Pest](https://img.shields.io/badge/tests-Pest-F28D1A)

## Features

**Social (students)**
- Club profiles with logo, category, bio, Instagram, permanent club QR
- Committee tab with session selector: photo, position, course short form, year group; tap for profile card
- Lifelong student profile with a personal permanent QR (portfolio fields only)
- *Coming:* news feed with auto-posts for approved events, stories, highlights, gallery

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
- Meetings with Google Meet, minutes (13) with AI drafting, weekly CCA
- Venue booking (La Place, semester venues, big rooms) with SLD confirmation; daily club status (Active / At risk / Inactive)
- AGM periods set by SLD, AGM scheduling with Meet link and rotating-QR attendance, AGM Report (01) with AI-drafted objectives/speech/agenda and computed attendance
- Elections: nominations with full-term check, one vote per position, SLD flags, results into the AGM Report, role handover on 1 July
- Annual Activities Report (12) as PPTX/PDF from the official template, AI-written event details, SLD themes; AGM package checklist

## Tech stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | Vue 3 (TypeScript) + Inertia.js, Tailwind CSS 4, Vite |
| Database | MariaDB / MySQL (production), SQLite (local) |
| Auth | Google OAuth (Laravel Socialite), spatie/laravel-permission |
| Documents | PhpWord, PhpSpreadsheet, python-pptx, LibreOffice headless |
| Tests | Pest |

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

```bash
composer test     # Pest
npm run build     # vue-tsc type-check + Vite build
QA_TEMPLATES=1 php artisan test --filter=TemplateFidelity   # every official template → storage/qa/ side by side
```

## Security and privacy

Everything sensitive stays on the server:

- **Secrets** (Google client secret, AI keys, DB credentials) live only in `.env`, read via `config()`. Only `VITE_APP_NAME` is exposed to the browser.
- **camsys is read-only:** the sync issues `SELECT` only and copies an allow-list of columns. IC/passport, address, religion, race, CGPA and scores are never read (enforced by tests).
- **Personal data:** student phone numbers are encrypted at rest; phone and email are hidden from serialisation; public profiles show portfolio fields only.
- **Authorisation** is enforced server-side (policies, form requests, role middleware). The UI never decides access.
- **AI calls** run server-side in queued jobs only, with minimal data.
- Sign-in is rate-limited; every role and committee change is written to the audit log.
- The sample `database/camsys/*.sql` is **dummy data**. Never commit real student records.

## Roadmap

- [x] Phase 0: Laravel setup, design system, layouts
- [x] Phase 1: Login, camsys sync, roles, clubs, profiles
- [x] Phase 2: Feed, events, proposals, approvals, attendance, indemnity
- [x] Phase 3: Membership, payments, finance, meetings, minutes, post mortem, venues
- [x] Phase 4: AGM, elections, Annual Activities Report
- [x] Phase 5: Dashboards, reminders, hardening, pilot

Guides: [SLD admin](docs/ADMIN_GUIDE.md) · [Committees](docs/COMMITTEE_GUIDE.md) · [Pilot checklist](docs/PILOT.md) · [Template fidelity check](docs/QA_TEMPLATES.md)

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
