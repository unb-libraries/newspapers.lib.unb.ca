#!/usr/bin/env sh
# Import content via Migrate API and content modules to a site.
CONTENT_DEPLOY_DIR='/tmp'
CONTENT_DIR='/app/content'
DRUSH_TEMP_LOCATION='drush-temp-disable'
DRUSH_VERSION='8.1.17'

# Deploy content if this is the first run, AND the content exists, AND we want the content deployed.
if [[ ! -f /tmp/DRUPAL_DB_LIVE && ! -f /tmp/DRUPAL_FILES_LIVE && -d "$CONTENT_DIR" && "$DRUPAL_IMPORT_CONTENT" = "TRUE" ]];
then
  # Copy content to a deploy dir to avoid altering the mounted volume.
  cp -r "${CONTENT_DIR}" "${CONTENT_DEPLOY_DIR}/"
  cd "${CONTENT_DEPLOY_DIR}/content"

  # Install Drush 8, which allows downloading and installing module dependencies automatically.
  composer require drush/drush:${DRUSH_VERSION} --prefer-dist
  DRUSH_COMMAND="${CONTENT_DEPLOY_DIR}/content/vendor/bin/drush --root=${DRUPAL_ROOT} --uri=default --yes"

  # Disable site-local drush so 8.x can run.
  if [[ -d "${DRUPAL_ROOT}/vendor/drush" ]];
  then
    mv "${DRUPAL_ROOT}/vendor/drush" "${DRUPAL_ROOT}/vendor/${DRUSH_TEMP_LOCATION}"
  fi

  # Loop over content modules and enable them.
  for MODULE_DIR in */; do
    if [ "$MODULE_DIR" != "vendor/" ]; then
      MODULE=$(echo ${MODULE_DIR%/})
      cp -r ${CONTENT_DEPLOY_DIR}/content/${MODULE} ${DRUPAL_ROOT}/modules/custom/
      ${DRUSH_COMMAND} en ${MODULE}
      ${DRUSH_COMMAND} pmu ${MODULE}
      rm -rf "${DRUPAL_ROOT}/modules/custom/${MODULE}"
    fi
  done

  # Re-enable site-local drush.
  if [[ -d "${DRUPAL_ROOT}/vendor/${DRUSH_TEMP_LOCATION}" ]];
  then
    mv "${DRUPAL_ROOT}/vendor/${DRUSH_TEMP_LOCATION}" "${DRUPAL_ROOT}/vendor/drush"
  fi

  # Remove deploy dir.
  rm -rf "${CONTENT_DEPLOY_DIR}/content"
fi
