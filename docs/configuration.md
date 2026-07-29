---
title: Configuration
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-06-18
last_reviewed_by: jsanford@unb.ca
description: Environment files, ports, Solr cores, and secrets for the NBNP application.
---

# Configuration

## Environment files

Local configuration is split across env files in `env/`, referenced by
`docker-compose.yml`:

| File | Configures |
| ---- | ---------- |
| `env/drupal.env` | Drupal application: deploy environment, local hostname and port, admin account |
| `env/mysql.env` | MariaDB database name, user, and passwords |
| `env/redis.env` | Redis cache |
| `env/solr.env` | Solr search |

The committed env files hold local development values only (`DEPLOY_ENV=local`).
Production values are supplied by the deployment platform, not this repository.

## Ports and dependencies

| Service | Local port | Notes |
| ------- | ---------- | ----- |
| Application (nginx) | 3095 | `3095:80` in `docker-compose.yml` |
| MariaDB | 3306 (internal) | `mariadb:10.11` |
| Redis | 6379 (internal) | `redis:7-alpine`, `allkeys-lru`, 128 MB |
| Solr | 8983 (internal) | Two cores, below |
| MailHog | 4095 → 8025 | Optional, `mailhog` compose profile only |

## Solr cores

The Solr container provisions two cores from the shared Drupal Solr configuration:

- `newspapers.lib.unb.ca` — the main site index.
- `pages.newspapers.lib.unb.ca` — the page-level index.

## Secrets

This application requires database credentials (MariaDB user and root passwords) and
a Drupal administrator password. The image also bundles LDAP support for
authentication.

Do not store production values in this repository. The committed `env/*` files contain
local development values only.

Production values: approved UNB Libraries secret store (not this repository)
Access group: Libraries Systems Operators
Rotation owner: Systems Team
