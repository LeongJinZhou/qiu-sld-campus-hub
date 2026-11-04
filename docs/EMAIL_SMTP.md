# Email notifications (SMTP with Haraka)

Every notification in Campus Hub is also sent to the person's email:

- **Approvals, payments, reminders and SLD requests** are always emailed, with a button that opens the action
  directly (for example **Review and sign** opens the approval).
- **Club activity** (likes, comments, saves, new followers, new posts, events and activities from followed clubs) is
  emailed only while that category is switched on in **Notifications → Settings**. Switching a category off stops
  it in the app and by email.
- Each email also links to **all notifications** and to **notification settings**.

The app sends mail by SMTP (`MAIL_*` in `.env`). The SMTP server is [Haraka](https://github.com/haraka/Haraka),
packaged in `deploy/haraka/`. Any other SMTP server (for example Plesk's own mail server) also works with the same
settings.

## How it fits together

```text
Campus Hub (Laravel) --SMTP 587, STARTTLS + login--> Haraka --DKIM-signed--> recipient's mail server
                                                            \--or via SMTP_RELAY_HOST (smart host)
```

Emails are queued, so the page never waits for the mail server. On Plesk, the every-minute scheduler sends them
(`QUEUE_VIA_SCHEDULER=true`).

## 1. Run Haraka

On any Docker host (an Exabytes VPS, or a Render **Private Service** using `deploy/haraka/Dockerfile`):

```bash
docker build -t campushub-haraka deploy/haraka
docker run -d --name haraka -p 587:587 \
  -e SMTP_HOSTNAME=mail.qiu.edu.my \
  -e SMTP_USER=campushub -e SMTP_PASS='a-long-random-password' \
  -e DKIM_DOMAIN=qiu.edu.my -e DKIM_SELECTOR=campushub \
  -v haraka-keys:/haraka/config/dkim \
  campushub-haraka
```

| Variable | Meaning |
| --- | --- |
| `SMTP_HOSTNAME` | The server's public name (used in the greeting and TLS certificate). |
| `SMTP_USER`, `SMTP_PASS` | The login the app uses. Only logged-in senders may send. |
| `DKIM_DOMAIN`, `DKIM_SELECTOR` | Sign every email with DKIM. The start-up log prints the DNS record to publish. |
| `SMTP_RELAY_HOST`, `SMTP_RELAY_PORT`, `SMTP_RELAY_USER`, `SMTP_RELAY_PASS` | Optional smart host. Use it when the host blocks outgoing port 25 (common on cloud platforms) or to send through the university's mail server. |

To use a real TLS certificate, mount it as `/haraka/config/tls_cert.pem` and `/haraka/config/tls_key.pem`;
otherwise a self-signed one is created.

## 2. DNS for the sending domain (ask QIU IT)

- **DKIM**: the `TXT` record printed in Haraka's log, e.g. `campushub._domainkey.qiu.edu.my`.
- **SPF**: add the Haraka server's IP (or the smart host) to the domain's SPF record.
- **Reverse DNS** for the server's IP should match `SMTP_HOSTNAME`.

Without these, mail may land in spam.

## 3. Point the app at Haraka

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=mail.qiu.edu.my      # or the private hostname of the Haraka service
MAIL_PORT=587
MAIL_USERNAME=campushub
MAIL_PASSWORD=a-long-random-password
MAIL_VERIFY_PEER=true          # false only with Haraka's self-signed certificate on a private network
MAIL_FROM_ADDRESS=noreply@qiu.edu.my
MAIL_FROM_NAME="QIU Campus Hub"
```

Then `php artisan config:cache`.

## 4. Check it

```bash
php artisan tinker --execute 'App\Models\User::where("email","you@qiu.edu.my")->first()->notify(new App\Notifications\DocumentNotification("Test email", "Email delivery works.", url("/notifications")));'
```

Haraka's log shows the login, `dkim signed for qiu.edu.my`, and the delivery.
