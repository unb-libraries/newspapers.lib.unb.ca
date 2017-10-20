#!/usr/bin/env sh
curl -OL http://github.com/unb-libraries/docker-drupal-scripts/archive/container.zip
unzip container.zip
mv docker-drupal-scripts-container/*.sh /scripts/
rm -rf container.zip docker-drupal-scripts-container
