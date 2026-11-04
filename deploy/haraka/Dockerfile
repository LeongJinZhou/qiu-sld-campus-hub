# Standalone Haraka SMTP server (https://github.com/haraka/Haraka) for Campus Hub, e.g. on an Exabytes VPS.
# The Campus Hub container already runs Haraka internally; use this image only for a separate mail server.
FROM node:22-alpine
RUN apk add --no-cache openssl tini \
    && npm install -g --omit=dev Haraka@3 \
    && haraka -i /haraka
COPY config/ /haraka/config/
COPY configure.sh /usr/local/bin/haraka-configure
RUN chmod +x /usr/local/bin/haraka-configure
EXPOSE 587
ENTRYPOINT ["/sbin/tini", "--", "sh", "-c", "haraka-configure && exec haraka -c /haraka"]
