#!/usr/bin/env bash

# Populate tokenized entries in this repo, building a base instance.
read -p "Site URI (eg. drupal.lib.unb.ca): "  newspapers.lib.unb.ca
DEFAULT_SITE_SLUG="$(echo $newspapers.lib.unb.ca | cut -d'.' -f1)"
DEFAULT_JIRA_PREFIX="$(echo $DEFAULT_SITE_SLUG | tr '[:lower:]' '[:upper:]')"
read -p "JIRA Prefix (default: $DEFAULT_JIRA_PREFIX): "  NBNP
NBNP=${NBNP:-$DEFAULT_JIRA_PREFIX}
read -p "Site slug (default: $DEFAULT_SITE_SLUG): "  newspapers
newspapers=${newspapers:-$DEFAULT_SITE_SLUG}
read -p "Site ID (eg. 3096): "  3095

export LC_CTYPE=C
export LANG=C

newspapers_lib_unb_ca=$(echo $newspapers.lib.unb.ca | sed 's/\./_/g')
newspapers.lib.unb.ca=$(echo $newspapers.lib.unb.ca | sed 's/\./\\\./g')

rm -rf .git

echo "Setting up:"
echo "$newspapers_lib_unb_ca"
echo "$newspapers.lib.unb.ca"

# Tokens
find . -type f -print0 | xargs -0 sed -i.bak "s/newspapers_lib_unb_ca/$newspapers_lib_unb_ca/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/newspapers.lib.unb.ca/$newspapers.lib.unb.ca/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/newspapers.lib.unb.ca/$newspapers.lib.unb.ca/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/NBNP/$NBNP/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/newspapers/$newspapers/g"
find . -type f -print0 | xargs -0 sed -i.bak "s/3095/$3095/g"
find . -name "*.bak" -type f -delete

# Move files
mv custom/themes/instance_theme/instance_theme.info.yml "custom/themes/instance_theme/$newspapers_lib_unb_ca.info.yml"
mv custom/themes/instance_theme/instance_theme.libraries.yml "custom/themes/instance_theme/$newspapers_lib_unb_ca.libraries.yml"
mv custom/themes/instance_theme "custom/themes/$newspapers_lib_unb_ca"

# Readme Shuffle
rm README.md
mv README_instance.md README.md

# Set up new git repo.
git init
git add .
git add -f ./config-yml/.gitkeep
git add -f ./custom/modules/.gitkeep
git add -f ./custom/themes/.gitkeep

git commit -m 'Initial commit from template repo.'

cd ..
mv drupal.lib.unb.ca "$newspapers.lib.unb.ca"

echo "Done!\nRun cd ..; cd $newspapers.lib.unb.ca; composer install --prefer-dist; dockworker container:start-over to bring the instance up."
