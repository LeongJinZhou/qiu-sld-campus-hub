# Email notifications (Haraka SMTP + Gmail)

Every notification in Campus Hub is also sent to the person's email:

- **Approvals, payments, reminders and SLD requests** are always emailed, with a button that opens the action
  directly (for example **Review and sign** opens the approval).
- **Club activity** (likes, comments, saves, new followers, new posts, events and activities from followed clubs) is
  emailed only while that category is switched on in **Notifications → Settings**. Switching it off stops it in the
  app and by email.
- Each email links to **all notifications** and to **notification settings**.

## How it works

```text
Campus Hub (Laravel) --SMTP 127.0.0.1:2525, login--> Haraka --STARTTLS + login--> smtp.gmail.com --> recipient
        \--------------------- if Haraka cannot be reached -----------------------> smtp.gmail.com (direct)
```

- [Haraka](https://github.com/haraka/Haraka) runs **inside the Campus Hub container** (see `Dockerfile` and
  `deploy/start.sh`). It listens on `127.0.0.1` only, so nothing outside the container can reach it, and the app
  logs in with a random password created at every start.
- Haraka relays every email through the Gmail account **qiuairesearch@gmail.com** using its Google app password.
  Gmail signs (DKIM) and delivers the email, so no DNS changes are needed.
- `MAIL_MAILER=failover` sends through Haraka first and Gmail directly if Haraka is down. If both fail, the queued
  email is retried, never dropped.
- Emails are queued: the container runs a queue worker and the scheduler in the background.
- Haraka refuses mail from anyone who has not logged in (it is not an open relay), and a wrong password is rejected.
  Email subjects are kept to one line and message text is escaped, so a club or event name cannot inject headers or
  HTML.

## Render

Render's **free** plan blocks outgoing email ports (25, 465 and 587), so neither Haraka nor Gmail can be reached from
the free demo; it keeps `MAIL_MAILER=log` (emails are written to the log). On a paid Render instance, add two
environment variables to the web service (**Environment** tab), set `MAIL_MAILER=failover`, then redeploy:

| Key | Value |
| --- | --- |
| `GMAIL_USERNAME` | `qiuairesearch@gmail.com` |
| `GMAIL_APP_PASSWORD` | the 16-character Google app password |

`MAIL_FROM_ADDRESS` defaults to the Gmail address. Haraka's log is `storage/logs/haraka.log`.

## Exabytes (Plesk)

In `.env`, fill `GMAIL_APP_PASSWORD` (the template already has the other values). With `MAIL_MAILER=failover` the
app sends through Gmail directly when Haraka is not running, so this works on any Plesk plan.

To run Haraka on Plesk too (optional, needs the Node.js extension or SSH):

```bash
npm install -g Haraka@3 && haraka -i ~/haraka && cp deploy/haraka/config/* ~/haraka/config/
HARAKA_DIR=~/haraka SMTP_LISTEN=127.0.0.1:2525 SMTP_TLS=off SMTP_USER=campushub SMTP_PASS='a-long-random-password' \
  GMAIL_USERNAME=qiuairesearch@gmail.com GMAIL_APP_PASSWORD='xxxx xxxx xxxx xxxx' sh deploy/haraka/configure.sh
haraka -c ~/haraka &
```

Then set `MAIL_PASSWORD` in `.env` to the same `SMTP_PASS`. A standalone Haraka server (for example on a VPS) can also
be built from `deploy/haraka/Dockerfile`.

## Check it

```bash
php artisan tinker --execute 'Illuminate\Support\Facades\Notification::route("mail","you@example.com")->notify(new App\Notifications\DocumentNotification("Test email", "Email delivery works.", url("/notifications")));'
```

Gmail limits a personal account to about 500 emails a day. For more, switch `GMAIL_*` to a Google Workspace account
or set `SMTP_RELAY_HOST` to another provider.

## Keep the password safe

The Google app password lives only in Render's environment and the server's `.env`, never in Git. To change it,
create a new app password in the Google account (**Security → App passwords**), update both places, and remove the
old one.
