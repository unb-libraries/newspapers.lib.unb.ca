#!/usr/bin/env sh
if [ -n "$DEV_WEB_URI" ] && [ "$DEPLOY_ENV" = "local" ]; then
  echo "Visit your instance at:"
  echo "$DEV_WEB_URI"
  echo "Log-in to your instance via:"
  /scripts/drupalUli.sh | sed -e "s|http://default|$DEV_WEB_URI|g"
fi
