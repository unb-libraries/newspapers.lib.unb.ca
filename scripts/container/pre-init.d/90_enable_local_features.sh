#!/usr/bin/env sh
# Local alterations for your instance.
# i.e. drush --root=${DRUPAL_ROOT} --uri=default --yes en thirty_two_project
DRUSH_COMMAND="drush --root=${DRUPAL_ROOT} --uri=default --yes"
$DRUSH_COMMAND cr

# Squash update emails.
drush --root=${DRUPAL_ROOT} --uri=default --yes config-set update.settings notification.emails.0 ''
