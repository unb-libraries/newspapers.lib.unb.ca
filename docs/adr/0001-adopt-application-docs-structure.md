---
title: Adopt the standard application documentation structure
status: active
owner: jsanford@unb.ca
last_reviewed: 2026-06-18
last_reviewed_by: jsanford@unb.ca
description: Record the move from a flat documentation folder to the standard docs/ layout.
---

# 1. Adopt the standard application documentation structure

## Summary

NBNP documentation moves from the legacy flat `documentation/` folder to the standard
`docs/` layout defined by the UNB Libraries
[application documentation guide](https://docs.lib.unb.ca/contributing/documenting-an-application/).

## Context

The old `documentation/` folder held useful operational knowledge but had no defined
structure: files mixed audiences and Diátaxis types, some pages were not linked from
the index, there was no page metadata, and the project abbreviation drifted between
NBNP, NBHNP, and NBHP. This made the docs hard to discover and to keep current.

## Decision

- Adopt the prescribed `docs/` tree: `index.md`, `architecture.md`, `deployment.md`,
  `configuration.md`, `operations.md`, `troubleshooting.md`, `howto/`, and `adr/`.
- Split legacy pages by Diátaxis type — the content model became explanation in
  `architecture.md`; the procedures became how-to runbooks.
- Add the required front matter to every page and standardize on **NBNP**.
- Retire the legacy `documentation/` folder; its history remains in Git.

## Consequences

- NBNP is the reference implementation other UNB Libraries repositories follow.
- Documentation is reviewed with code changes and has machine-readable ownership.
- Any inbound links to `documentation/` must be updated to `docs/`.
