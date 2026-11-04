# Haraka SMTP server for QIU Campus Hub notifications (https://github.com/haraka/Haraka).
# The app submits mail on port 587 with a username and password; Haraka signs it with DKIM and delivers it
# (directly, or through a smart host when SMTP_RELAY_HOST is set).
FROM node:22-alpine
RUN apk add --no-cache openssl tini \
    && npm install -g --omit=dev Haraka@3 \
    && haraka -i /haraka
COPY config/ /haraka/config/
COPY entrypoint.sh /usr/local/bin/haraka-start
RUN chmod +x /usr/local/bin/haraka-start
EXPOSE 587
ENTRYPOINT ["/sbin/tini", "--", "haraka-start"]
