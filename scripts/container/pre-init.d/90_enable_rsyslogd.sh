#!/usr/bin/env sh
if [ "$DEPLOY_ENV" == "prod" ]; then
  echo "Starting rsyslogd..."
  sed -i "s|LOGZIO_KEY|$LOGZIO_KEY|g" /etc/rsyslog.d/21-logzio-nginx.conf
  /usr/sbin/rsyslogd -f /etc/rsyslog.conf
fi
