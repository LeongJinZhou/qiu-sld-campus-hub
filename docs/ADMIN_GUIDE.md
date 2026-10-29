# Campus Hub — SLD Admin Guide

For Student Life Division officers and the Super Admin. Everything here is under **SLD Admin** (the shield icon), at `/admin`.

## Signing in

Sign in with your `@qiu.edu.my` Google account. Super Admin accounts are listed in `SUPERADMIN_EMAILS` on the server. SLD officers are given the *admin* role by a Super Admin under **Roles**.

## The dashboard (`/admin`)

One screen for the whole division:

- **Active members, fees collected, overdue approvals, overdue post-event documents** — the four numbers at the top. Overdue replies to SLD requests are linked underneath.
- **Clubs by status** — Active, At risk (no activity for 2+ months) and Inactive (3+ months). Recalculated every night at 01:00.
- **Approvals waiting, by approver** — how many documents are sitting with each role (Advisor, SLD Officer, Assistant Registrar, Registrar, Bursar, COO, Dean), and how many are past their 3-working-day step.
- **AGM** — clubs that have scheduled the AGM, submitted the report, and submitted the package.
- **Events this month** and **club balances**.

## Setting up a club

1. **Clubs → New club.** Name, category, established year, bio, Instagram handle, logo (PNG or JPG; SVG logos cannot be printed on Word forms).
2. **Committee.** Pick students for President, Vice President, Secretary and Treasurer (all four are compulsory and must be different active students), plus any extra positions.
3. **Advisor.** Add the advisor. If they have no account yet, create one under **Staff** with their `@qiu.edu.my` email first.
4. **Transfer.** When the club is ready, **Transfer to committee**. The committee and advisor can then edit the club profile. You keep oversight and can **Take back** at any time.

A club cannot submit any document until all four compulsory positions are filled.

## Staff and approvers

**Staff** lists every staff account. Set the *approver role* (Assistant Registrar, Registrar, Bursar, COO, Dean) and department. The names printed on official forms come from here, never from the template, so update this list when people change jobs.

## Finance

Before a club can use its ledger, enter its **opening balance** (amount, as-of date, note) from the club's Finance page. It becomes the first line, "Opening Acc". Only SLD can change it, and every change is logged. **Club finance** shows every club's balance.

## Membership fees and drives

- **Fees & drives → Club fees**: Treasurers set a fee per session; you approve it. Members cannot join a paid club until the fee is approved.
- **Membership drives**: set a start and end date-time for all clubs or one club. During a drive the club is featured and Join is highlighted.

## Approvals

Documents reach you at the **SLD Officer** step (proposals, minutes, attendance lists, post mortems, finance records, AGM reports). Open **Approvals** (tick icon), review, then **Approve** (sign with mouse or finger), **Return** with a comment, or **Reject**. Every step is due within 3 working days; reminders go out the day before and every day it is overdue.

## Venue requests (`/admin/venues`)

La Place booths, semester venues and big rooms (at least 1 week ahead) arrive here and by email. **Confirm** or **Decline** with an optional note; the club is notified.

## Requests to clubs (`/admin/requests`)

Ask one club or all clubs for something (a document, a confirmation). Clubs must reply within **7 working days**. They see the request on their club page, get a reminder the day before it is due and daily while overdue, and their reply comes back to you here.

## AGM season (`/admin/agm`)

1. **Open an AGM period**: the session, the opening date, the AGM deadline and the report deadline (for example 1 Jul – 30 Sep, reports by 9 Oct). Outside the period the AGM module is locked for clubs.
2. Watch each club's row: AGM scheduled, attendance, AGM Report progress, election status and flags (*no candidate* for a compulsory position, *uncontested*, *tie*), and the four-part package checklist with download links.
3. Verify each AGM Report at your step in Approvals.
4. **Annual report themes**: add colour/font/background themes; clubs pick one when they generate the Annual Activities Report.
5. **Close period** when the season is over.

Election winners take their positions automatically on 1 July (or as soon as results are published, if that is later).

## Moderation

**Moderation** lets you pin posts to the top of everyone's feed, hide posts or comments, and resolve reports.

## Super Admin only

- **Roles**: grant or revoke admin/super admin.
- **camsys sync**: runs every night at 02:00 (read-only; only safe columns are copied). Run it by hand here if needed.
- **Audit log**: every sign, approval, return, role change, payment confirmation and setting change, with who and when.
- **Settings**: student status mapping, intake months (1 = March, 2 = July, 3 = November), programme short forms.

## Verifying a signature or certificate

Every signed form and certificate carries a code. Anyone can check it at `/verify/<code>` (signatures) or `/certificates/<code>` (certificates). The page confirms who signed, when and what — it never shows the signature image.
