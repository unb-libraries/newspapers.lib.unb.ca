---
title: New Brunswick Historical Newspapers Project
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-06-18
last_reviewed_by: jsanford@unb.ca
description: Documentation for newspapers.lib.unb.ca, the Drupal application behind the New Brunswick Historical Newspapers Project.
---

# New Brunswick Historical Newspapers Project

The New Brunswick Historical Newspapers Project (NBNP) gives researchers unified
access to UNB Libraries' current and historical newspaper collections, from New
Brunswick and around the world. It runs as a Drupal application at
[newspapers.lib.unb.ca](https://newspapers.lib.unb.ca).

| Field | Value |
| ----- | ----- |
| Production URL | <https://newspapers.lib.unb.ca> |
| Stack | Drupal 9, MariaDB, Apache Solr, Redis |
| Repository | [unb-libraries/newspapers.lib.unb.ca](https://github.com/unb-libraries/newspapers.lib.unb.ca) |
| Deployment | Kubernetes via [dockworker](https://github.com/unb-libraries/dockworker) |
| Owner | jsanford@unb.ca |

## Documentation

- [Architecture](architecture.md) — the stack, custom modules, and the newspaper
  content model.
- [Deployment](deployment.md) — how the application ships to dev and production.
- [Configuration](configuration.md) — environment, ports, Solr cores, and secrets.
- [Operations](operations.md) — monitoring, scheduled jobs, and the runbook index.
- [Troubleshooting](troubleshooting.md) — known issues and workarounds.

### Runbooks

- [Bulk issue operations](howto/bulk-issue-operations.md)
- [Correct improperly linked assets](howto/correct-improperly-linked-assets.md)
- [Add the copy-citation feature](howto/add-copy-citation-feature.md)
