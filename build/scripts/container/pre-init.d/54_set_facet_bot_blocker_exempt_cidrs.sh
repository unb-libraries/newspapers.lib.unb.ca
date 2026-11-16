#!/usr/bin/env sh
# Write FACET_BOT_BLOCKER_EXEMPT_CIDRS (comma-separated CIDRs, set by the cluster's Values so the
# ranges stay out of this public repository) over its token in base.settings.php. Only digits,
# hex, '.', ':', '/' and ',' are written, so nothing else can reach PHP.
SETTINGS_FILE="$DRUPAL_ROOT/sites/all/settings/base.settings.php"
VALUE="${FACET_BOT_BLOCKER_EXEMPT_CIDRS:-}"

case "$VALUE" in
  *[!0-9A-Fa-f.:/,\ ]*)
    echo "Warning: FACET_BOT_BLOCKER_EXEMPT_CIDRS holds characters other than CIDRs; nobody is exempt from the facet limit."
    VALUE=""
    ;;
esac

sed -i "s|FACET_BOT_BLOCKER_EXEMPT_CIDRS_VALUE|$VALUE|g" "$SETTINGS_FILE"
