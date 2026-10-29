# Pilot checklist — 3 clubs

Goal: run Campus Hub with three real clubs for one month before opening it to every club. Pick clubs that cover the main paths: one with an **outdoor event** (indemnity), one with **paid membership** (online and cash), and one running **weekly activities** (minutes and CCA).

## 1. Server (IT)

- [ ] PHP 8.3+, MariaDB 10.4+/MySQL 8, Node 20+ for the build, LibreOffice, Python 3 with `python-pptx`.
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` (HTTPS), database, `SUPERADMIN_EMAILS`, mail settings.
- [ ] Google Cloud OAuth client (Web): redirect URI `${APP_URL}/auth/google/callback`, Calendar API enabled; set `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`.
- [ ] `ANTHROPIC_API_KEY` for receipt reading and drafting (without it, those steps go to manual review).
- [ ] camsys read-only connection (`SELECT` only) and the nightly sync tested once by hand from **camsys sync**.
- [ ] `php artisan migrate --force`, `php artisan db:seed --class=SettingsSeeder`, `php artisan storage:link`.
- [ ] Queue worker running (`php artisan queue:work`) under a process manager.
- [ ] Scheduler: cron `* * * * * php artisan schedule:run`.
- [ ] HTTPS on (the camera, the installable app and the service worker need it).
- [ ] Nightly database and `storage/app` backup, and one restore tested.
- [ ] `QA_TEMPLATES=1 php artisan test --filter=TemplateFidelity` on the server; open `storage/qa/` and compare with the originals.

## 2. SLD set-up

- [ ] Staff accounts for every approver, with approver role and department (names print on forms).
- [ ] Settings: student status mapping, intake months, programme short forms.
- [ ] The three clubs: profile, PNG/JPG logo, committee (4 compulsory positions), advisor, then **Transfer**.
- [ ] Opening balance for each club.
- [ ] Approve each club's membership fee.
- [ ] Brief the three committees (30 minutes, using `docs/COMMITTEE_GUIDE.md`) and the approvers (15 minutes on Approvals).

## 3. Scenarios to complete during the pilot

| # | Scenario | Club | Done |
| --- | --- | --- | --- |
| 1 | Students scan the club QR, sign in with Google, join a free club | any | [ ] |
| 2 | Paid membership online: receipt auto-confirmed; one sent to Payment review | paid | [ ] |
| 3 | Paid membership in cash: full two-phone handover with the QR photo | paid | [ ] |
| 4 | Event proposal through the whole chain to COO, with one Return and resubmit | outdoor | [ ] |
| 5 | Poster checklist and upload; event appears in the feed | outdoor | [ ] |
| 6 | Indemnity signed by participants; an unsigned student is blocked at clock-in | outdoor | [ ] |
| 7 | QR clock-in/out on the day; CCA shows on profiles | outdoor | [ ] |
| 8 | Fund withdrawal (07) through to COO; downloaded PDF checked by Bursary | outdoor | [ ] |
| 9 | Post-event: receipts in 1 week, post mortem and others in 2 weeks; reminders received | outdoor | [ ] |
| 10 | Weekly activity: meeting with Google Meet, AI-drafted minutes, attendance, CCA awarded | weekly | [ ] |
| 11 | Ledger entries with receipts; 02 Finance Record export checked by Bursary | all | [ ] |
| 12 | Venue request (La Place) confirmed by SLD | any | [ ] |
| 13 | SLD request to all three clubs; one reply on time, one overdue reminder seen | all | [ ] |
| 14 | Members list filters and Excel/PDF export | all | [ ] |
| 15 | Certificate downloaded and verified via its code | any | [ ] |
| 16 | App installed on Android and iPhone ("Add to Home Screen"); QR scanning works | any | [ ] |

If the pilot runs during AGM season, also: open a period, one club schedules an AGM, holds an election, submits the AGM Report and generates the Annual Activities Report, then submits the package.

## 4. What to collect

- Every printed/downloaded form checked by its approver against the paper original (layout, names, signatures, dates).
- Time from submission to final approval for each proposal (the dashboard shows overdue steps).
- Problems reported by committees (a short form or a shared chat), each with the page and what happened.
- Notifications and reminders: did people receive them, and were any too many?

## 5. Go / no-go

Open to all clubs when:

- [ ] All 16 scenarios are done without a workaround.
- [ ] Bursary and Registry accept the 07 and 06 PDFs as the official copies.
- [ ] No data or permission issue was found (a student never saw another club's internal pages or anyone's private data).
- [ ] Nightly sync, reminders and backups ran every day of the pilot (check the audit log and server logs).
