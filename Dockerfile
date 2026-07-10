FROM ghcr.io/unb-libraries/drupal:9.x-2.x-unblib

# Install additional OS packages.
ENV ADDITIONAL_OS_PACKAGES="postfix php7-ldap php7-xmlreader php7-zip php7-redis"
ENV DRUPAL_SITE_ID="newspapers"
ENV DRUPAL_SITE_URI="newspapers.lib.unb.ca"
ENV DRUPAL_SITE_UUID="655af73f-dc1a-48f1-84a1-3da88d2d1ad4"

# Build application.
COPY ./build/ /build/
RUN ${RSYNC_MOVE} /build/scripts/container/ /scripts/ && \
  /scripts/addOsPackages.sh && \
  /scripts/initOpenLdap.sh && \
  /scripts/setupStandardConf.sh && \
  /scripts/build.sh

# Deploy configuration.
COPY ./configuration ${DRUPAL_CONFIGURATION_DIR}
RUN /scripts/pre-init.d/72_secure_config_sync_dir.sh

# Deploy custom modules, themes.
COPY ./custom/themes ${DRUPAL_ROOT}/themes/custom
COPY ./custom/modules ${DRUPAL_ROOT}/modules/custom

# Container metadata.
ARG BUILD_DATE
ARG VCS_REF
ARG VERSION
LABEL ca.unb.lib.generator="drupal9" \
  org.opencontainers.image.authors="UNB Libraries <libsupport@unb.ca>" \
  org.opencontainers.image.created="$BUILD_DATE" \
  org.opencontainers.image.description="newspapers.lib.unb.ca provides researchers with unified access to UNB Libraries' current and historical newspaper collections in all formats, from New Brunswick and across the world." \
  org.opencontainers.image.revision="$VCS_REF" \
  org.opencontainers.image.source="https://github.com/unb-libraries/newspapers.lib.unb.ca" \
  org.opencontainers.image.title="newspapers.lib.unb.ca" \
  org.opencontainers.image.url="https://newspapers.lib.unb.ca" \
  org.opencontainers.image.vendor="University of New Brunswick Libraries" \
  org.opencontainers.image.version="$VERSION"
