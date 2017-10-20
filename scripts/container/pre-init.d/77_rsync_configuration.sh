#!/usr/bin/env sh
# Ensure Directory Exists for First Deploy.
if [ "$DRUPAL_DEPLOY_CONFIGURATION" != "FALSE" ] && [ ! -d "$DRUPAL_CONFIGURATION_DIR" ]; then
  echo "Creating App Configuration Dir..."
  mkdir -p "$DRUPAL_CONFIGURATION_DIR"
fi

# RSync New Configuration.
if [ "$DRUPAL_DEPLOY_CONFIGURATION" != "FALSE" ] && [ -d "$TMP_DRUPAL_BUILD_DIR/config-yml" ] && [ "$(ls $TMP_DRUPAL_BUILD_DIR/config-yml)" ]; then
  echo "RSyncing Configuration..."
  rsync -a ${RSYNC_FLAGS} --no-perms --no-owner --no-group --delete "$TMP_DRUPAL_BUILD_DIR/config-yml/" "$DRUPAL_CONFIGURATION_DIR/"
fi
