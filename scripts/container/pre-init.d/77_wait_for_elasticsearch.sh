#!/usr/bin/env sh
# Ensure elasticsearch cluster is up before importing config.

# Check if Elasticsearch env vars exist.
if [[ -z "$ELASTICSEARCH_HOSTNAME" ]]; then
 ELASTICSEARCH_HOSTNAME='drupal-elasticsearch-lib-unb-ca'
fi
if [[ -z "$ELASTICSEARCH_PORT" ]]; then
 ELASTICSEARCH_PORT='9200'
fi

# Check to see if Elasticsearch is accepting connections
nc -zw10 ${ELASTICSEARCH_HOSTNAME} ${ELASTICSEARCH_PORT}
RETVAL=$?
while [ $RETVAL -ne 0 ]
do
  nc -zw10 ${ELASTICSEARCH_HOSTNAME} ${ELASTICSEARCH_PORT}
  RETVAL=$?
  echo -e "\t Waiting for Elasticsearch on $ELASTICSEARCH_HOSTNAME:$ELASTICSEARCH_PORT..."
  sleep 10
done
