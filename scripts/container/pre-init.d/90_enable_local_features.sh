#!/usr/bin/env sh
# Local alterations for your instance.
# i.e. drush --root=${DRUPAL_ROOT} --uri=default --yes en thirty_two_project
DRUSH_COMMAND="drush --root=${DRUPAL_ROOT} --uri=default --yes"
$DRUSH_COMMAND cr

# Squash update emails.
$DRUSH_COMMAND config-set update.settings notification.emails.0 ''

# Enable serial holding and import taxonomy terms.
$DRUSH_COMMAND en serial_holding

# Import content.
if [ "$DEPLOY_ENV" = "local" ]; then
  cp -r ${DRUPAL_TESTING_ROOT}/visual-regression/visreg_content ${DRUPAL_ROOT}/modules/custom
  $DRUSH_COMMAND en visreg_content
  $DRUSH_COMMAND pmu visreg_content migrate migrate_plus migrate_source_csv migrate_tools
  rm -rf ${DRUPAL_ROOT}/modules/custom/visreg_content
fi
