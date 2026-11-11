# Campus Hub: SLD Admin Guide

For Student Life Division officers and the Super Admin. Everything here is under **SLD Admin** (the shield icon), at `/admin`.

## Signing in

Sign in with your `@qiu.edu.my` Google account. The Super Admin (`ai@qiu.edu.my`) is set in `SUPERADMIN_EMAILS` on the server. The SLD Officer (`yeeshiu.chong@qiu.edu.my`) is created when the system is installed. Other SLD officers are added under **Admins & roles** (see below).

## The dashboard (`/admin`)

One screen for the whole division:

- **Active members, fees collected, overdue approvals, overdue post-event documents**: the four numbers at the top. Overdue replies to SLD requests are linked underneath.
- **Clubs by status**: Active, At risk (no activity for 2+ months) and Inactive (3+ months). Recalculated every night at 01:00.
- **Approvals waiting, by approver**: how many documents are sitting with each role (Advisor, SLD Officer, Assistant Registrar, Registrar, Bursar, COO, Dean), and how many are past their 3-working-day step.
- **AGM**: clubs that have scheduled the AGM, submitted the report, and submitted the package.
- **Events this month** and **club balances**.

## Setting up a club

1. **Clubs → New club.** Name, category, established year, bio, Instagram handle, logo (JPG, PNG or WebP, up to 4 MB; SVG logos cannot be printed on Word forms).
2. **Committee.** Pick students for President, Vice President, Secretary and Treasurer (all four are compulsory and must be different active students), plus any extra positions.
3. **Advisor.** Choose the advisor from the staff list and select **Add advisor**. Every active staff member is listed, including those who have not signed in yet; their account is created for them.
4. **Transfer.** When the club is ready, **Transfer to committee**. The committee and advisor can then edit the club profile. You keep oversight and can **Take back** at any time.

A club cannot submit any document until all four compulsory positions are filled.

## Staff and approvers

**Staff** lists every staff account. Choose the *approver role* (Assistant Registrar, Registrar, Bursar, COO, Dean) and the department when you add a staff account; the approver role cannot be changed afterwards, so add a new account if it must change. The names printed on official forms come from here, never from the template, so update this list when people change jobs.

## Finance

Before a club can use its ledger, enter its **opening balance** (amount, as-of date, note) from the club's Finance page. It becomes the first line, "Opening Acc". Only SLD can change it, and every change is logged. **Club finance** shows every club's balance.

## Membership fees and drives

- **Fees & drives → Club fees**: Treasurers set a fee per session and SLD is notified to approve it. Members cannot join a paid club until the fee is approved.
- **Membership drives**: set a start and end date-time for all clubs or one club. During a drive the club is featured and Join is highlighted.

## Approvals

Documents reach you at the **SLD Officer** step (proposals, minutes, attendance lists, post mortems, finance records, AGM reports). Open **Approvals** (tick icon), review, then **Approve** (sign with mouse or finger), **Return** with a comment, or **Reject**. Every step is due within 3 working days; reminders go out the day before and every day it is overdue.

## Venue requests (`/admin/venues`)

La Place booths, semester venues and large rooms (at least 1 week in advance) arrive here and by email. **Confirm** or **Decline** with an optional note; the club is notified.

## Requests to clubs (`/admin/requests`)

Ask one club or all clubs for something (a document, a confirmation). Clubs must reply within **7 working days**. They see the request on their club page, get a reminder the day before it is due and daily while overdue, and their reply comes back to you here.

## AGM season (`/admin/agm`)

1. **Open an AGM period**: the session, the opening date, the AGM deadline and the report deadline (for example 1 Jul – 30 Sep, reports by 9 Oct). Outside the period the AGM module is locked for clubs.
2. Watch each club's row: AGM scheduled, attendance, AGM Report progress, election status and flags (*no candidate* for a compulsory position, *uncontested*, *tie*), and the four-part package checklist with download links.
3. Verify each AGM Report at your step in Approvals.
4. **Annual report themes**: add colour/font/background themes; clubs pick one when they generate the Annual Activities Report.
5. **Close period** when the season is over.

Election winners do not take over automatically. After SLD acknowledges the AGM, SLD selects **Start handover**. The incoming committee then has view-only access for the overlap period (default 7 days, set in Settings). When the overlap ends, a daily job moves the roles to the new committee and issues certificates to the outgoing committee.

## Meetings and minutes (`/admin/meetings`)

Every club's committee meetings and weekly activities in one report: who was invited, who was present (by QR scan or marked by the committee), who checked out, the attendance rate and where the minutes stand. Filter by club, type, month and session, and export the list to Excel or PDF.

For each meeting you can download the **minutes** (PDF or Word), the **attendance checklist** (Excel or PDF), or **everything in one ZIP**. When minutes reach you for approval, the attendance checklist is shown with them. Meetings also appear in your calendar.

## Moderation

**Moderation** lets you pin posts to the top of everyone's feed, hide posts or comments, and resolve reports. When someone reports a post (the **⋯** menu on any post), every SLD officer and the Super Admin receive a notification with the reason, linking straight to this page.

## Admins & roles (`/admin/roles`)

- **Add an SLD admin**: enter their name and QIU email. They sign in with their QIU Google account and land in the SLD portal.
- **SLD Officers** can grant and remove the SLD Officer role for other people.
- Only the **Super Admin** can grant or remove Super Admin.
- Nobody can change their own roles, and an SLD Officer cannot change a Super Admin.
- Every change is recorded in the audit log.

## Super Admin only

- **Student records sync**: runs every night at 02:00 (read-only; only safe columns are copied). Run it by hand if needed.
- **Audit log**: every signature, approval, return, role change, payment confirmation and setting change, in plain language: who did it, to which club, event or person, and what changed.
- **Settings**: programme short forms and the deadlines every club works to.

## Verifying a signature or certificate

Every signed form and certificate carries a code. Anyone can check it at `/verify/<code>` (signatures) or `/certificates/<code>` (certificates). The page confirms who signed, when and what. It never shows the signature image.
