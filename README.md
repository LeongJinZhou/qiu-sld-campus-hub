# QIU SLD Campus Hub

Student Life Division portal for Quest International University: an Instagram-style social face (feed, club profiles, stories, lifelong student profiles) and a back-office portal that turns every official SLD template into a web form printed back in the exact official layout.

Decisions and business rules: [`CLAUDE.md`](CLAUDE.md). Phase plan: [`docs/BUILD_PROMPT.md`](docs/BUILD_PROMPT.md).

## Stack

Laravel 13 · PHP 8.3+ · Vue 3 (TypeScript) + Inertia · Tailwind CSS 4 · MariaDB/MySQL (SQLite locally) · Pest

## Quick start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
composer run dev            # or: php artisan serve + npm run dev
```

## Tests

```bash
composer test               # Pest
npm run build               # vue-tsc type-check + Vite build
```

## Status

- [x] Phase 0 — Laravel setup, two DB connections, design tokens, UI components, layouts *(Stitch design import pending: no Stitch tool available)*
- [ ] Phase 1 — Login, camsys sync, roles, clubs, profiles
- [ ] Phase 2 — Feed, events, proposals, approvals, attendance, indemnity
- [ ] Phase 3 — Membership, payments, finance, meetings, minutes, post mortem, venues
- [ ] Phase 4 — AGM, elections, Annual Activities Report
- [ ] Phase 5 — Dashboards, reminders, hardening, pilot

## Layout

```text
app/Services/            business rules (Camsys, Time, Roles, Documents, …)
database/camsys/         camsys sample dump (read-only source)
resources/templates/     original/ official files (never edited), filled/ placeholders, maps/
resources/js/            Components/ui design system, Layouts, Pages
public/brand/            QIU Student Activity logos and guides
docs/reference/          Club & Society briefing
```
