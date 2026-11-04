#!/bin/sh
# Writes Haraka's runtime config from environment variables. Used by the standalone image (Dockerfile here) and by
# the Campus Hub container, where Haraka runs inside the app on 127.0.0.1 only.
#
#   HARAKA_DIR          Haraka home created with `haraka -i` (default /haraka)
#   SMTP_LISTEN         address:port to listen on (default [::0]:587; the app container uses 127.0.0.1:2525)
#   SMTP_HOSTNAME       public name of this server (default: the container hostname)
#   SMTP_USER/SMTP_PASS login the app uses (required)
#   SMTP_TLS            "on" (default) offers STARTTLS with the mounted or a self-signed certificate; "off" on loopback
#   GMAIL_USERNAME      Gmail account to relay through (smtp.gmail.com:587)
#   GMAIL_APP_PASSWORD  its Google app password (16 characters; spaces are removed)
#   SMTP_RELAY_HOST/PORT/USER/PASS  any other smart host instead of Gmail
#   DKIM_DOMAIN/DKIM_SELECTOR       sign mail with DKIM (only when Haraka delivers directly, not through Gmail)
set -eu
H="${HARAKA_DIR:-/haraka}"
C="$H/config"
: "${SMTP_USER:?set SMTP_USER}" "${SMTP_PASS:?set SMTP_PASS}"
mkdir -p "$C"

printf 'listen=%s\nnodes=%s\n' "${SMTP_LISTEN:-[::0]:587}" "${SMTP_NODES:-1}" > "$C/smtp.ini"
echo "${SMTP_HOSTNAME:-$(hostname)}" > "$C/me"
printf '[core]\nmethods=PLAIN,LOGIN\n\n[users]\n%s=%s\n' "$SMTP_USER" "$SMTP_PASS" > "$C/auth_flat_file.ini"
chmod 600 "$C/auth_flat_file.ini"

# A certificate always exists (Haraka also uses it for outgoing TLS); STARTTLS is offered to clients when SMTP_TLS=on.
if [ ! -f "$C/tls_key.pem" ] || [ ! -f "$C/tls_cert.pem" ]; then
  openssl req -x509 -newkey rsa:2048 -nodes -days 825 -subj "/CN=${SMTP_HOSTNAME:-localhost}" -keyout "$C/tls_key.pem" -out "$C/tls_cert.pem" 2>/dev/null
fi
printf 'key=tls_key.pem\ncert=tls_cert.pem\n' > "$C/tls.ini"
PLUGINS=""
[ "${SMTP_TLS:-on}" = "on" ] && PLUGINS="tls"
PLUGINS="$PLUGINS
auth/flat_file"

# Gmail is the default smart host when its app password is given.
if [ -n "${GMAIL_APP_PASSWORD:-}" ]; then
  SMTP_RELAY_HOST="${SMTP_RELAY_HOST:-smtp.gmail.com}"
  SMTP_RELAY_PORT="${SMTP_RELAY_PORT:-587}"
  SMTP_RELAY_USER="${SMTP_RELAY_USER:-${GMAIL_USERNAME:?set GMAIL_USERNAME}}"
  SMTP_RELAY_PASS="${SMTP_RELAY_PASS:-$(printf '%s' "$GMAIL_APP_PASSWORD" | tr -d ' ')}"
fi

if [ -n "${SMTP_RELAY_HOST:-}" ]; then
  # Authenticated mail from the app is forwarded to the smart host, which signs and delivers it.
  {
    printf 'host=%s\nport=%s\nenable_tls=true\nenable_outbound=true\nauth_type=plain\n' "$SMTP_RELAY_HOST" "${SMTP_RELAY_PORT:-587}"
    [ -n "${SMTP_RELAY_USER:-}" ] && printf 'auth_user=%s\nauth_pass=%s\n' "$SMTP_RELAY_USER" "${SMTP_RELAY_PASS:-}"
  } > "$C/smtp_forward.ini"
  chmod 600 "$C/smtp_forward.ini"
  PLUGINS="$PLUGINS
queue/smtp_forward"
elif [ -n "${DKIM_DOMAIN:-}" ]; then
  SEL="${DKIM_SELECTOR:-campushub}"
  D="$C/dkim/$DKIM_DOMAIN"
  mkdir -p "$D"
  [ -f "$D/private" ] || openssl genrsa -out "$D/private" 2048 2>/dev/null
  openssl rsa -in "$D/private" -pubout -out "$D/public" 2>/dev/null
  echo "$SEL" > "$D/selector"
  printf '[sign]\nenabled=true\nselector=%s\ndomain=%s\nheaders=From,Sender,Reply-To,Subject,Date,Message-ID,To,Cc,MIME-Version\n' "$SEL" "$DKIM_DOMAIN" > "$C/dkim.ini"
  PLUGINS="$PLUGINS
dkim"
  echo "DKIM DNS record: ${SEL}._domainkey.${DKIM_DOMAIN} TXT \"v=DKIM1; k=rsa; p=$(grep -v '^-' "$D/public" | tr -d '\n')\""
fi
printf '%s\n' "$PLUGINS" > "$C/plugins"
