#!/usr/bin/env sh
if [ "$DEPLOY_ENV" != "local" ] && [ "$DEPLOY_ENV" != "dev" ]; then
  postfix start
fi
