<!--
PLANNING-ASSET-REGISTRY: ROLE-FAMILIAR
REGISTRY-VERSION: 2026-08-03-v1
STATUS: CURRENT
LAST-REVIEWED: 2026-08-03T06:44:51-04:00

VERSIONING RULE:
- Never silently replace a dated planning snapshot.
- Create a new date-stamped file for a materially newer review.
- Mark the old file SUPERSEDED and point SUPERSEDED-BY to the new asset.
- Update the Current assets table in this registry.
-->

# Role Familiar planning assets

This folder holds date-stamped snapshots of the application's product surface. These files describe the repository at a specific point in time; they are not evergreen specifications.

## Current assets

| Asset                                                                                    | Snapshot   | Version | Status      | Purpose                                                                             |
| ---------------------------------------------------------------------------------------- | ---------- | ------- | ----------- | ----------------------------------------------------------------------------------- |
| [Current-functionality wireframe](2026-08-03-current-functionality-wireframe-v1.md)      | 2026-08-03 | v1      | **CURRENT** | Low-fidelity map of pages, user flows, working states, prototypes, and boundaries   |
| [Current-functionality outline contract](2026-08-03-current-functionality-outline-v1.md) | 2026-08-03 | v1      | **CURRENT** | Evidence-based inventory, invariants, gaps, and recommended next step for each area |

## Status markings

- **CURRENT** — latest reviewed snapshot listed in this registry.
- **SUPERSEDED** — retained for history; use the file named in `SUPERSEDED-BY`.
- **DRAFT** — incomplete and not suitable for implementation planning.

## Update contract

When the application changes materially:

1. Copy both current assets to new `YYYY-MM-DD-...-vN.md` filenames.
2. Update their header comments, repository revision, evidence, and recommendations.
3. Change each prior asset's header to `STATUS: SUPERSEDED` and fill in `SUPERSEDED-BY`.
4. Point the Current assets table above to the new files.
5. Keep old snapshots in this folder so product decisions remain auditable.
