<!--
PLANNING-ASSET-ID: RF-CURRENT-FUNCTIONALITY-OUTLINE
SNAPSHOT-DATE: 2026-08-03
VERSION: v1
STATUS: CURRENT
REPOSITORY-REVISION: e20f76a
SUPERSEDES: none
SUPERSEDED-BY: none
LAST-REVIEWED: 2026-08-03T06:44:51-04:00

FUTURE EDITOR NOTE:
This is an evidence-based point-in-time contract. Create a new dated version
instead of rewriting history, then update docs/planning/README.md.
-->

# Role Familiar current-functionality outline contract

**Snapshot:** August 3, 2026 · **Version:** v1 · **Status:** CURRENT  
**Repository revision inspected:** `e20f76a`

## Purpose and evidence standard

This document records what the application currently does, what remains intentionally prototypical, and the most useful next step for each area. It was derived from the actual routes, controllers, actions, agents, models, policies, Inertia pages, migrations, tests, and existing milestone audits—not from the older baseline descriptions alone.

Classification rules:

- **Working:** reachable implementation with domain and authorization behavior, normally covered by automated tests.
- **Prototype:** functional but governed by explicitly temporary product rules or presentation.
- **Foundation:** installed or rendered, but not yet a meaningful product outcome.
- **Deferred:** deliberately absent and not a defect.

## Area-by-area contract

| Area                              | State                                     | Working functionality                                                                                                                                                      | Boundary or gap                                                                                | Recommended next step                                                                                                                       | Completion signal                                                                                                              |
| --------------------------------- | ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Authentication                    | **Working**                               | Registration, login, password reset, email verification, password confirmation, two-factor authentication, passkeys, throttling                                            | Product onboarding begins only after the generic starter flow                                  | Add a short first-run handoff that sends a verified user to create or review a target role                                                  | A new verified user can reach a meaningful role setup action without using the placeholder dashboard                           |
| Account settings                  | **Working**                               | Profile update/delete, password, 2FA, passkey management                                                                                                                   | No product-specific preferences are needed yet                                                 | Keep stable; add preferences only when a real coaching or notification requirement exists                                                   | No speculative settings; security flows retain coverage                                                                        |
| Application navigation            | **Working**                               | Dashboard, Your Familiar, Target Role, Familiar Lab, user/settings navigation                                                                                              | Familiar Lab is a development surface in normal authenticated navigation                       | Gate Familiar Lab to local/development use and make Target Role or Your Familiar the primary product entry                                  | Production navigation contains only user-facing product surfaces                                                               |
| Dashboard                         | **Foundation**                            | Authenticated placeholder page                                                                                                                                             | It does not summarize the user's role, familiar, analysis state, or next action                | Replace with a compact active-role launchpad using existing safe props and links                                                            | Dashboard shows one current state and one or two real next actions without duplicating role/familiar pages                     |
| Target-role domain                | **Working**                               | User-owned roles; public immutable ULIDs; string-backed enums; ordered responsibilities/competencies; append-oriented analyses/reviews; soft deletion; policies and scopes | Historical navigation is basic                                                                 | Preserve aggregate boundaries; add filtering/pagination only when actual role volume warrants it                                            | Multi-role history stays private, understandable, and performant without merging contexts                                      |
| Manual role creation/editing      | **Working**                               | Draft creation, editing, validation, empty-row cleanup, add/remove/reorder, nested errors, focus recovery, unsaved-change warning                                          | Visual and keyboard behavior has strong markup but limited browser-level regression coverage   | Add automated browser checks at 320 px and for keyboard add/remove/reorder focus                                                            | Browser tests prove narrow layout, focus order, and unsaved-change behavior                                                    |
| Role confirmation/lifecycle       | **Working**                               | Explicit confirmation, required summary/responsibility/competency, edit after confirmation, archive, restore, active switching, safe delete of non-active roles            | Historical roles share a single index with limited grouping                                    | Improve active/archived/draft presentation before adding longitudinal features                                                              | Users can tell which role is active and what restore will do before acting                                                     |
| One-active-role rule              | **Working**                               | Confirming/restoring a role transactionally archives the prior active role; no role is overwritten                                                                         | No separate role-switcher, by design                                                           | Keep switching explicit through confirm/restore until product evidence supports a faster selector                                           | Tests continue proving one active role and preserved history/familiars                                                         |
| Job-listing entry                 | **Working**                               | Private listing capture, optional known context, length bounds, unsaved warning, listening/thinking presentation                                                           | No explicit retention controls are exposed to users                                            | Define and document a listing/analysis retention policy before broader production use                                                       | Policy covers raw listing, analyses, reviews, archive, soft delete, and permanent deletion                                     |
| Structured AI analysis            | **Working**                               | Dedicated Laravel AI SDK agent; strict bounded schema; prompt-injection boundary; no tools; queued execution; provider/model configuration; controlled failures            | Operational visibility is test-focused rather than production-focused                          | Add privacy-safe queue metrics and staging smoke monitoring; never log source text or model payloads                                        | Operators can distinguish queue delay, rate limit, invalid output, and provider outage without seeing private content          |
| Analysis status                   | **Working**                               | Pending and failed states, retry, manual refresh, 2.5-second Inertia polling, stop on terminal state, stop while hidden                                                    | No user notification after leaving the page                                                    | Defer notifications until there is a real channel requirement; keep bounded polling now                                                     | No duplicate requests or endless polling; failure remains recoverable                                                          |
| Analysis review/correction        | **Working**                               | Evidence and confidence display; responsibilities, competencies, tooling, focus areas, ambiguities, assumptions; correction/reordering; save without confirmation          | Very large proposals may be cognitively dense                                                  | Consider an optional “changes before confirmation” summary after browser usability review                                                   | Users can distinguish source evidence, inference, their corrections, and unresolved uncertainty                                |
| AI trust/privacy                  | **Working**                               | Listing treated as data; protected-trait inference prohibited; raw listing encrypted; safe logs; owner-only props; analysis excluded from normal familiar context          | Formal data-retention and deletion language is not yet user-facing                             | Add retention/deletion policy and audit production logging/telemetry configuration                                                          | No raw listing, prompt, response, key, or cross-user metadata appears in logs or ordinary props                                |
| Queue Wyrm assignment             | **Working**                               | Confirmation preserves an existing role familiar or assigns a new Stage 1 Queue Wyrm; future roles receive separate familiars                                              | Familiar naming is fixed                                                                       | Keep default name until naming has a product purpose; do not add matching yet                                                               | Role/familiar pairing remains one-to-one and progression never crosses roles                                                   |
| Confirmed role context            | **Working**                               | Controlled service returns confirmed title, company, seniority, summary, ordered confirmed responsibilities/competencies, tooling, focus areas, unresolved clarifications  | Context is displayed but not consumed by the existing AI guide                                 | Make this value object the only role input to the next coaching agent                                                                       | Tests prove raw listing, evidence, assumptions, analysis metadata, and other roles never enter the agent context               |
| Familiar page                     | **Working + prototype elements**          | Role summary, deterministic acknowledgement, animated Queue Wyrm, reduced-motion handling, transient reactions, quests, active familiar state                              | It combines product context with temporary quest/progression mechanics                         | Reframe the page around role-grounded coaching while clearly labeling prototype progression until replaced                                  | The main action is useful role-specific preparation; progression remains secondary and server-authoritative                    |
| Prototype interview guide         | **Prototype**                             | One short question; structured response; controlled reaction; optional suggestion of an existing quest; no persistence or tools                                            | Guidance is general and receives neither ConfirmedRoleContext nor conversation memory          | **Next milestone:** create a role-grounded coaching agent that receives only the confirmed context plus the current user question           | Answers reference confirmed role themes without inventing employer/user facts; no automatic quest, score, or evolution changes |
| Prototype quests                  | **Prototype**                             | Four fixed, idempotent quest completions with safe domain events                                                                                                           | They are labels and completion buttons, not real practice artifacts                            | Freeze feature growth until a practice-session contract defines what completion actually proves                                             | No new quest types are added without user work products and completion evidence                                                |
| Familiar evolution                | **Prototype**                             | Summoned → Attuned after two quests; Attuned → Interview-ready after all four; animated transitions and persisted events                                                   | Thresholds are not readiness science                                                           | Do not expand stages or thresholds; redesign only after assessment/practice evidence exists                                                 | Product copy never equates current stage with hiring readiness                                                                 |
| Familiar Lab                      | **Prototype/development**                 | Stage, overlay, animation, playback, reduced-motion, and quest-asset inspection without persistence                                                                        | Exposed like a normal product page                                                             | Environment-gate it and keep it as an asset QA tool                                                                                         | Production users do not mistake debug controls for permanent progression                                                       |
| Accessibility/responsive behavior | **Implemented, needs browser regression** | Persistent labels, keyboard buttons, focus restoration, live regions, reduced-motion handling, 320 px-oriented layouts                                                     | Prior audits did not claim complete interactive browser sign-off                               | Add focused browser tests for role composer, analysis transitions, review reordering, familiar motion fallback, and mobile layout           | Automated checks cover keyboard-only flow, focus after async changes, and 320 px rendering                                     |
| Authorization/data isolation      | **Working**                               | Authenticated/verified routes, owner-scoped ULID binding, policies, inherited child ownership, private Inertia props, cross-user denial tests                              | Ongoing requirement for every new coaching/session resource                                    | Make TargetRole/Familiar ownership inheritance mandatory in future session policies                                                         | Every future endpoint has same-owner positive tests and cross-user 404/denial tests                                            |
| Queue/operations                  | **Working foundation**                    | Database queue configuration, ID-only analysis job payload, retries, stale checks, sanitized terminal failure                                                              | No queue dashboard or privacy-safe operational runbook                                         | Add operational metrics/runbook before production traffic; avoid payload inspection tooling that exposes listings                           | Support can diagnose stuck/failed work from IDs, categories, timing, and counts only                                           |
| CI and database parity            | **Working**                               | GitHub Actions runs PHP 8.5, MySQL 8.4, migrations, frontend checks, PHP quality checks, and Pest                                                                          | Local fast path remains SQLite; exact production MySQL version should remain aligned           | Pin CI to the production MySQL minor/LTS line and add browser tests when introduced                                                         | Fresh migrations and the full suite pass on the production-equivalent engine                                                   |
| Documentation                     | **Mixed freshness**                       | Target-role product/AI contracts and milestone audit reflect the current role flow                                                                                         | Older baseline/prototype audits contain intentionally historical statements that are now stale | Add superseded markers to historical audits during the next documentation pass; use this folder's dated registry for current-state planning | Readers can immediately distinguish historical audit evidence from the latest product snapshot                                 |

## Current non-negotiable invariants

Future work must preserve these behaviors unless a new approved product contract explicitly replaces them:

1. The Queue Wyrm is the only familiar species in the current product.
2. A user may own multiple target roles, and each confirmed role keeps its own familiar and progression history.
3. At most one confirmed role is active; switching archives rather than overwrites history.
4. AI analysis is a proposal. User-reviewed fields are authoritative.
5. Raw listing text never becomes familiar coaching context.
6. The listing, prompt, raw model response, provider internals, and keys are not logged or exposed in ordinary props.
7. The analysis agent treats the listing as untrusted data and has no tools or browsing.
8. Only confirmed role records feed `ConfirmedRoleContext`.
9. AI cannot complete quests, evolve familiars, score readiness, or make permanent progression decisions.
10. Public route binding and authorization continue to prevent cross-user role/familiar inference.

## Recommended delivery sequence

### Next milestone — confirmed-role coaching integration

Build a dedicated role-grounded coaching interaction around the existing `ConfirmedRoleContext`.

Contract:

- send only the compact confirmed context and the current user question;
- preserve the no-tools, no-hiring-prediction, no-invented-experience boundaries;
- keep responses structured and bounded;
- do not add conversation memory yet unless separately approved;
- do not mutate quests, progression, stages, scores, or role data;
- keep the general guide available as a fallback only if its product role is explicit;
- test raw-analysis exclusion, cross-role isolation, cross-user denial, and no progression mutation.

Exit criteria:

- Queue Wyrm can answer a concise preparation question using only the active confirmed role profile.
- The answer can cite role themes but never claim unsupported company or user facts.
- Archived-role context cannot leak into the active interaction.
- No AI result changes permanent progression.

### Following quality milestone — product entry and browser confidence

1. Replace the placeholder Dashboard with a role/familiar launchpad.
2. Gate Familiar Lab outside development.
3. Add browser regression tests for 320 px layouts, keyboard reordering, async focus, retry, reduced motion, and familiar transitions.
4. Publish a privacy-safe queue operations and retention runbook.

### Still deferred

Do not treat the following as implied by the next milestone:

- baseline assessment;
- persistent practice sessions or chat history;
- STAR story library;
- readiness scoring;
- mock interviews;
- familiar matching or additional species;
- AI-created or AI-completed quests;
- PixelLab runtime integration.

Each requires its own product contract, persistence design, privacy review, authorization model, and tests.

## Snapshot verification notes

- Inspected 26 non-vendor application routes.
- Inspected current Target Role, job-listing analysis/review, Queue Wyrm, progression, authentication/settings, and navigation implementations.
- Existing suite at this snapshot: 148 Pest tests and 907 assertions, verified against MySQL 8.4 during the immediately preceding CI database update.
- The working tree was clean when this snapshot inspection began.
- This document is an analysis artifact only; it does not authorize implementation of the recommendations above.
