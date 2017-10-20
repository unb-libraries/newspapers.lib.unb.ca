#!/usr/bin/env sh
if [ "$DEPLOY_ENV" == "local" ]; then
  # Drupal tree.
  chgrp -R "${LOCAL_USER_GROUP}" "${DRUPAL_ROOT}"
  chmod g+w -R "${DRUPAL_ROOT}"

  # Test tree.
  chgrp -R "${LOCAL_USER_GROUP}" "${DRUPAL_TESTING_ROOT}"
  chmod g+w -R "${DRUPAL_TESTING_ROOT}"
fi
