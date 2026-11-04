#!/bin/sh
# Builds the runtime config from environment variables, then starts Haraka.
#   SMTP_HOSTNAME   public name of this server, e.g. mail.qiu.edu.my (required)
#   SMTP_USER       login the app uses (MAIL_USERNAME)                (required)
#   SMTP_PASS       password the app uses (MAIL_PASSWORD)             (required)
#   DKIM_DOMAIN     domain mail is sent from, e.g. qiu.edu.my         (optional, enables DKIM signing)
#   DKIM_SELECTOR   DNS selector, default "campushub"
#   SMTP_RELAY_HOST optional smart host (e.g. the Plesk mail server) and SMTP_RELAY_PORT/USER/PASS
set -eu
C=/haraka/config
: "${SMTP_HOSTNAME:?set SMTP_HOSTNAME}" "${SMTP_USER:?set SMTP_USER}" "${SMTP_PASS:?set SMTP_PASS}"

echo "$SMTP_HOSTNAME" > "$C/me"
printf '[core]\nmethods=PLAIN,LOGIN\n\n[users]\n%s=%s\n' "$SMTP_USER" "$SMTP_PASS" > "$C/auth_flat_file.ini"
chmod 600 "$C/auth_flat_file.ini"

# STARTTLS: use mounted certificates if present, otherwise a self-signed one (the app still encrypts the link).
if [ ! -f "$C/tls_key.pem" ] || [ ! -f "$C/tls_cert.pem" ]; then
  openssl req -x509 -newkey rsa:2048 -nodes -days 825 -subj "/CN=$SMTP_HOSTNAME" -keyout "$C/tls_key.pem" -out "$C/tls_cert.pem" 2>/dev/null
fi

if [ -n "${DKIM_DOMAIN:-}" ]; then
  SEL="${DKIM_SELECTOR:-campushub}"
  D="$C/dkim/$DKIM_DOMAIN"
  mkdir -p "$D"
  [ -f "$D/private" ] || openssl genrsa -out "$D/private" 2048 2>/dev/null
  openssl rsa -in "$D/private" -pubout -out "$D/public" 2>/dev/null
  echo "$SEL" > "$D/selector"
  printf '[sign]\nenabled=true\nselector=%s\ndomain=%s\nheaders=From,Sender,Reply-To,Subject,Date,Message-ID,To,Cc,MIME-Version\n' "$SEL" "$DKIM_DOMAIN" > "$C/dkim.ini"
  KEY=$(grep -v '^-' "$D/public" | tr -d '\n')
  echo "DKIM DNS record: ${SEL}._domainkey.${DKIM_DOMAIN} TXT \"v=DKIM1; k=rsa; p=${KEY}\""
fi

if [ -n "${SMTP_RELAY_HOST:-}" ]; then
  {
    printf 'enable_outbound=true\nhost=%s\nport=%s\nenable_tls=true\n' "$SMTP_RELAY_HOST" "${SMTP_RELAY_PORT:-587}"
    [ -n "${SMTP_RELAY_USER:-}" ] && printf 'auth_type=plain\nauth_user=%s\nauth_pass=%s\n' "$SMTP_RELAY_USER" "${SMTP_RELAY_PASS:-}"
  } > "$C/smtp_forward.ini"
  grep -q '^queue/smtp_forward' "$C/plugins" || echo 'queue/smtp_forward' >> "$C/plugins"
fi

exec haraka -c /haraka
