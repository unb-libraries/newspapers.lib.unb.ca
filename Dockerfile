FROM unblibraries/drupal:8.x-1.x
MAINTAINER UNB Libraries <libsupport@unb.ca>

LABEL name="newspapers.lib.unb.ca"
LABEL vcs-ref=""
LABEL vcs-url="https://github.com/unb-libraries/newspapers.lib.unb.ca"

ENV DRUPAL_SITE_ID newspapers
ENV DRUPAL_SITE_URI newspapers.lib.unb.ca
ENV DRUPAL_SITE_UUID NULL

# Deploy upstream scripts, and then override with any local.
RUN curl -sSL https://raw.githubusercontent.com/unb-libraries/CargoDock/drupal-8.x-1.x/container/deploy.sh | sh
COPY ./scripts/container /scripts

# Add additional OS packages.
ENV ADDITIONAL_OS_PACKAGES rsyslog postfix php7-ldap php7-xmlreader php7-zip php7-redis
RUN /scripts/addOsPackages.sh && \
  /scripts/initRsyslog.sh && \
  echo "TLS_REQCERT never" > /etc/openldap/ldap.conf

# Add package conf.
COPY ./package-conf /package-conf
RUN /scripts/setupStandardConf.sh

# Build the contrib Drupal tree.
ARG COMPOSER_DEPLOY_DEV=no-dev
ENV DRUPAL_BASE_PROFILE minimal
ENV DRUPAL_BUILD_TMPROOT ${TMP_DRUPAL_BUILD_DIR}/webroot

COPY ./build/ ${TMP_DRUPAL_BUILD_DIR}
RUN /scripts/build.sh ${COMPOSER_DEPLOY_DEV} ${DRUPAL_BASE_PROFILE}

# Deploy repo assets.
COPY ./tests/ ${DRUPAL_TESTING_ROOT}/
COPY ./config-yml ${TMP_DRUPAL_BUILD_DIR}/config-yml
COPY ./custom/themes ${TMP_DRUPAL_BUILD_DIR}/custom_themes
COPY ./custom/modules ${TMP_DRUPAL_BUILD_DIR}/custom_modules

# Universal environment variables.
ENV DEPLOY_ENV prod
ENV DRUPAL_DEPLOY_CONFIGURATION TRUE
ENV DRUPAL_CONFIGURATION_EXPORT_SKIP devel
