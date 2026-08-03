# Target Role implementation plan

## Repository findings

Inspection was performed on the `issue2` branch with a clean working tree before these documents were added.

- Runtime/package inventory from Boost: PHP 8.5, Laravel 13.23.0, Laravel AI SDK 0.10.1, Inertia Laravel 3.2.1, Inertia React 3.6.1, Wayfinder 0.1.20, Pest 5.0.2, and MySQL.
- `boost.json` enables guidelines, MCP, and the AI SDK, Laravel, Wayfinder, Pest, Inertia React, and Tailwind skills.
- `config/ai.php` uses OpenAI by default, reads `OPENAI_API_KEY` server-side, and reads the text model from `ROLE_FAMILIAR_TEXT_MODEL`. The local environment has an OpenAI key configured; its value was not read or recorded. `.env.example` declares the key but currently omits `ROLE_FAMILIAR_TEXT_MODEL`.
- The database queue is configured and the standard `jobs`, `job_batches`, and `failed_jobs` tables exist. No application job currently implements `ShouldQueue`; database `retry_after` defaults to 90 seconds.
- The AI SDK conversation tables exist, but the prototype agent is deliberately non-conversational.
- `PrototypeInterviewGuide` already uses `Agent`, `Promptable`, `HasStructuredOutput`, bounded enums, OpenAI attributes, a server-side model, and no tools. `AskPrototypeInterviewGuide` maps structured output and logs only safe failure metadata.
- `User` currently has one `UserFamiliar`. `user_familiars.user_id` is unique. `UserFamiliar` uses a public ULID route key, is owner-authorized by `UserFamiliarPolicy`, and belongs to append-oriented progression records.
- `SummonPrototypeFamiliar`, `CompletePrototypeQuest`, and `EvolvePrototypeFamiliar` are invokable actions. Writes are transactional and progression is server-authoritative.
- `/familiar` receives controlled Inertia props and uses Wayfinder-generated controller actions. Listening/thinking states are transient React state; familiar stage and quests are persisted.
- Existing Pest tests cover authentication, owner authorization, route-key privacy, AI structured fakes, failure-log privacy, throttling, progression idempotency, and Inertia prop boundaries.
- Existing product documentation explicitly deferred role/job analysis, and the AI audit already prohibits prompt persistence, raw provider logging, and AI progression writes.

## Verified installed APIs

Laravel Boost documentation search verified the following version-specific contracts before this plan was written:

- AI agents implement `Agent` with `Promptable`; structured agents implement `HasStructuredOutput::schema(JsonSchema $schema)`. Nested objects and arrays of objects use bounded JSON schema builders. Structured responses are array-accessible.
- Agent tests use `AgentClass::fake()` with array responses, `assertPrompted`, `assertQueued`, and `preventStrayPrompts`.
- The SDK has a `queue()` prompt helper, but this plan intentionally uses an explicit Laravel job so private listing text is not serialized into a queue payload.
- Laravel 13 unique jobs use `ShouldBeUnique` and an atomic-lock-capable cache. Queue tests use `Queue::fake()`/`assertPushed`, and job queue interactions can be tested with `withFakeQueueInteractions()`.
- Inertia React v3 polling uses `usePoll(interval, reloadOptions, options)`, supports partial reload `only`, manual `start`/`stop`, background throttling, and `mode: 'rest'` to avoid overlapping polls.
- Inertia deferred/optional props remain available, but analysis status should use polling because it changes after the initial response.
- Wayfinder's Vite plugin is installed with `formVariants: true`; frontend forms and visits must import generated controller actions or named routes rather than hard-coded internal URLs.
- Pest 5 supports Laravel feature tests, datasets, and architecture expectations. This repository currently uses `RefreshDatabase` globally for Feature tests and `test()` naming; new tests should follow that convention.

## Refined data model

### `target_roles`

| Column | Contract |
| --- | --- |
| `id` | Internal bigint primary key. |
| `public_id` | Unique ULID and route key. |
| `user_id` | Indexed foreign key; cascade on user deletion. |
| `status` | `TargetRoleStatus`, indexed with `user_id`. |
| `source_type` | `TargetRoleSourceType`. |
| `title` | Nullable while draft/pending; required for confirmation. |
| `company_name` | Nullable; never inferred as fact without evidence/user confirmation. |
| `seniority` | Non-null `Seniority`, default `unspecified`. |
| `role_summary` | Nullable text while draft; required for confirmation. |
| `original_job_listing` | Nullable encrypted `longText`; hidden from serialization. |
| `interview_date` | Nullable date. |
| `biggest_concern` | Nullable text. |
| `confirmed_at` | Nullable timestamp. |
| `archived_at` | Nullable timestamp. |
| timestamps | Standard timestamps. |

The title and summary must be nullable at the database layer because a pending job-listing role has no user-approved profile yet. Confirmation validation supplies the stronger invariant.

### `role_responsibilities`

Belongs to a target role and contains `content` text, `priority`, `source`, nullable `source_evidence` text, unsigned `sort_order`, nullable `user_confirmed_at`, and timestamps. Add a unique `(target_role_id, sort_order)` index. `source_evidence` is added to the proposed domain so each accepted extraction can retain listing support.

### `role_competencies`

Belongs to a target role and contains `name`, `category`, `priority`, nullable `source_evidence` text, `source`, nullable unsigned tiny `user_confidence` constrained by validation to 1–5, unsigned `sort_order`, nullable `user_confirmed_at`, and timestamps. Add a unique `(target_role_id, sort_order)` index. Accepted technologies/tools use category `tooling`; a separate technology table is unnecessary in this milestone.

### `role_analyses`

Belongs to a target role and contains `status`, `prompt_version`, `provider`, `model`, `source_hash`, nullable `structured_result` JSON, nullable `assumptions` JSON, nullable bounded `failure_category`, nullable `usage_metadata` JSON, nullable `started_at`, nullable `completed_at`, and timestamps. Index `(target_role_id, created_at)` and `(status, created_at)`.

Rows are mutable only while transitioning into a terminal state. After `completed` or `failed`, application actions and model tests must reject changes. Every retry appends a new row. This preserves auditability, prompt-version comparison, failure history, and the exact proposal the user reviewed.

### Familiar relationship change

Add nullable, unique `target_role_id` to `user_familiars`, with a foreign key that restricts target-role deletion. Drop the existing unique index on `user_id` but retain a normal user index. Change `User::familiar()` to `User::familiars(): HasMany`; add explicit query methods/relations for the active confirmed role and its familiar. Add `TargetRole::familiar(): HasOne`.

The first confirmation transaction attaches the user's existing unassigned familiar. A later confirmation archives the old role and creates a new `queue_wyrm` familiar. Do not retarget an attached familiar. Draft target roles do not receive familiars.

No baseline assessment, role-familiar matching table, readiness entity, or interview-practice table is warranted.

## Enums

Use string-backed PHP enums with TitleCase case names and the following stored values:

- `TargetRoleStatus`: `draft`, `analysis_pending`, `review_required`, `confirmed`, `analysis_failed`, `archived`.
- `TargetRoleSourceType`: `manual`, `job_listing`.
- `Seniority`: `intern`, `junior`, `mid`, `senior`, `staff`, `principal`, `lead`, `manager`, `director`, `executive`, `unspecified`.
- `CompetencyCategory`: `technical`, `behavioral`, `leadership`, `domain`, `tooling`, `communication`, `delivery`.
- `Priority`: `low`, `medium`, `high`, `critical`.
- `DataSource`: `user`, `job_listing`, `ai_inferred`, `user_corrected`.
- `RoleAnalysisStatus`: `pending`, `processing`, `completed`, `failed`.

`RoleAnalysisStatus` is the only added enum beyond the proposed set because queue idempotency and terminal immutability require a separate lifecycle. Keep failure categories as bounded constants on a failure mapper rather than another enum. Confidence remains an integer, not an enum.

## Status lifecycle

```text
manual:      draft -> review_required -> confirmed -> archived
job listing: draft -> analysis_pending -> review_required -> confirmed -> archived
                                  \-> analysis_failed -> analysis_pending (new attempt)
```

- Manual data can be saved as a draft; submission for review requires confirmable fields.
- Creating a listing analysis sets the role to `analysis_pending` and creates a `pending` analysis in one transaction, then dispatches after commit.
- A completed analysis changes only the role status to `review_required`; proposal data remains on the analysis row.
- A failed analysis changes the role status to `analysis_failed`; retry appends an analysis and returns to `analysis_pending`.
- Applying review edits writes the user-owned profile/children but does not confirm it.
- Confirmation locks the user and role, validates the full aggregate, archives any other confirmed role, attaches/creates Queue Wyrm, stamps child confirmations, and sets `confirmed_at`.
- Editing a confirmed role uses one atomic “save and reconfirm” action; no unconfirmed partial update is exposed to coaching.
- Archiving sets both status and `archived_at`; unarchive and delete are deferred.

## Application components

### Models and factories

Add `TargetRole`, `RoleResponsibility`, `RoleCompetency`, and `RoleAnalysis` models with explicit fillable attributes, enum/date/JSON/encrypted casts, typed relationships, public ULID behavior on `TargetRole`, and factory states for each lifecycle. Update `User` and `UserFamiliar` relationships/factories without changing familiar stage semantics.

### Policies

Add `TargetRolePolicy`:

- `viewAny`/`create`: authenticated user;
- `view`, `update`, `analyze`, `confirm`, `archive`: exact `user_id` ownership;
- `update` rejects archived roles;
- `analyze` accepts only job-listing roles in draft/review/failed states and rejects confirmed/archived roles in this milestone;
- `confirm` requires a non-archived owner role and no pending analysis;
- `delete`, `restore`, and `forceDelete`: false.

There is no public `RoleAnalysis` route. Analysis access is authorized through its parent target role. Nested route bindings must be scoped where a child identifier is ever accepted. `UserFamiliarPolicy` continues checking familiar ownership.

### Invokable actions

- `CreateTargetRole`: creates a manual draft or listing draft for the authenticated user.
- `UpdateTargetRoleDraft`: persists validated user profile fields and replaces ordered children transactionally; records `user`, `user_corrected`, or accepted proposal provenance.
- `StartJobListingAnalysis`: hashes the current source, creates a pending attempt, updates role status, and dispatches `AnalyzeJobListing` after commit.
- `CompleteJobListingAnalysis`: validates terminal output and atomically completes the attempt/status without writing profile fields.
- `FailJobListingAnalysis`: stores a safe category and terminal timestamps.
- `ConfirmTargetRole`: locks the user aggregate, archives the old confirmed role, stamps confirmation, and delegates familiar attachment.
- `AttachQueueWyrmToTargetRole`: attaches the existing unassigned familiar once or calls the existing summon logic in a role-aware form to create a new Queue Wyrm.
- `UpdateConfirmedTargetRole`: atomically replaces user-approved content and reconfirms without exposing a transient draft to coaching.
- `ArchiveTargetRole`: archives the owned confirmed role without moving its familiar.
- `BuildTargetRoleCoachingContext`: returns the minimal confirmed-profile DTO used by Queue Wyrm.

### Form requests

- `StoreTargetRoleRequest`: validates source type and the source-specific initial payload; listing length should be bounded (recommended 1–50,000 characters).
- `UpdateTargetRoleRequest`: validates title, summary, enum-backed fields, ordered nested responsibility/competency arrays, evidence length, confidence 1–5, date, concern, and item-count limits.
- `StartJobListingAnalysisRequest`: authorizes the parent policy and validates that retained/replaced source text is present.
- `ConfirmTargetRoleRequest`: authorizes confirmation and requires an accepted boolean acknowledgement for visible assumptions/ambiguities.
- `ArchiveTargetRoleRequest`: authorizes archive and accepts no mutable role data.

All controllers consume `validated()` data only. Authorization may live in each request or an explicit `Gate::authorize`, but follow one consistent pattern; the existing code generally uses the policy gate in controllers for bound models.

### Controllers and routes

Under the existing `auth` and `verified` middleware group, use named routes and Wayfinder:

| Method/path | Name | Controller |
| --- | --- | --- |
| `GET /target-roles` | `target-roles.index` | `TargetRoleController@index` |
| `GET /target-roles/create` | `target-roles.create` | `TargetRoleController@create` |
| `POST /target-roles` | `target-roles.store` | `TargetRoleController@store` |
| `GET /target-roles/{targetRole}` | `target-roles.show` | `TargetRoleController@show` |
| `PATCH /target-roles/{targetRole}` | `target-roles.update` | `TargetRoleController@update` |
| `POST /target-roles/{targetRole}/analysis` | `target-roles.analysis.store` | invokable `TargetRoleAnalysisController` |
| `POST /target-roles/{targetRole}/confirm` | `target-roles.confirm` | invokable `ConfirmTargetRoleController` |
| `POST /target-roles/{targetRole}/archive` | `target-roles.archive` | invokable `ArchiveTargetRoleController` |

Keep controller methods thin: authorize, invoke one action, and return an Inertia response/redirect. Bind `TargetRole` by `public_id`. Apply a named throttle to analysis creation in addition to queue/provider rate limiting.

### Inertia React pages and props

- `target-roles/index.tsx`: active confirmed role first, archived roles separately, drafts/pending roles with status. Defer the archived collection only if measurement justifies it.
- `target-roles/create.tsx`: manual and pasted-listing entry paths with TypeScript form data and Wayfinder form variants.
- `target-roles/show.tsx`: one review/editor page for draft, pending, failed, review-required, confirmed, and archived states.

While pending, `show.tsx` uses `usePoll(2000, { only: ['targetRole', 'analysis'] }, { mode: 'rest' })` and stops on a terminal status. It renders a skeleton/status region and accessible live update. The review state visibly labels evidence, `ai_inferred` items, assumptions, ambiguities, and confidence. The page never shows provider/model/usage metadata. The original listing may appear only in an owner-authorized, explicitly labeled source panel.

Normal `/familiar` props gain only a small confirmed role summary needed for display, not the original listing or analysis metadata. Existing transient familiar animation state stays client-side.

### AI agent and queue job

Add structured `JobListingAnalyzer` following `docs/ai/job-listing-analysis-contract.md`. Pin a prompt version in application configuration or the agent class and keep provider/model resolution in `config/ai.php`.

Add `AnalyzeJobListing implements ShouldQueue, ShouldBeUnique`. Its unique ID is the immutable analysis ID. Serialize only the model/ID, fetch and decrypt listing text inside `handle`, set processing under a lock, invoke the agent, and finalize through actions. Recommended limits: agent timeout 45 seconds, job timeout 60 seconds, three tries, backoff `[10, 60, 300]`, and a dedicated `ai-analysis` queue. Configure database `retry_after` above the job timeout (recommended 120 seconds) before deployment. A sanitized application exception—not a provider exception containing response content—drives retries. `failed()` invokes the safe failure action.

Do not use the SDK queued-prompt helper for listing text, and do not put listing text in Laravel `Context`, job properties, event payloads, notifications, or logs.

### Queue Wyrm integration

Refactor `AskPrototypeInterviewGuide` to resolve the authenticated user's confirmed target role and build a server-side `TargetRoleCoachingContext`. Inject that context into `PrototypeInterviewGuide` through its constructor or a dedicated context message. The submitted question remains the only user prompt. The agent instructions must distinguish confirmed context from user experience and continue prohibiting invented employer facts, readiness scores, tool calls, and progression writes.

When no role is confirmed, retain controlled general guidance or direct the user to create a role; do not silently use a draft or latest analysis.

## Requirement traceability

Requirement IDs below correspond to: R1 manual creation, R2 listing analysis, R3 review/correction, R4 explicit confirmation, R5 private original, R6 visible assumptions, R7 no fabrication, R8 coaching context, R9 edit confirmed, R10 active/archive, R11 role-tied familiar, and R12 deferred domains.

| Requirement | Migration/model/enum | Policy/action/request/controller/route | Page/agent/job/test |
| --- | --- | --- | --- |
| Manual creation | `target_roles`, children; source/status/data enums | create/update actions and requests; resource store/update | create/show pages; feature tests for draft/review validation |
| Listing analysis | encrypted source; append-only `role_analyses`; analysis status | start/complete/fail actions; analysis request/controller/route | analyzer + explicit job; queue, fake, failure, injection tests |
| Review/correction | responsibility/competency provenance and order | owner policy; transactional update action/request | review page; source-label, reorder, tamper, rollback tests |
| Explicit confirmation | confirmation timestamps and confirmed status | confirm policy/action/request/controller/route | confirmation UI; missing acknowledgement and invariant tests |
| Private original | encrypted hidden cast | owner-only view/update; no child analysis route | source panel; cross-user, serialization, log, queue-payload tests |
| Visible assumptions | immutable analysis assumptions/result | review mapper only | review page; Inertia prop presence in review and absence elsewhere |
| No fabrication | nullable company/title, unspecified seniority, evidence/source | review action never auto-applies proposal | bounded agent schema/instructions; adversarial fake cases |
| Familiar coaching context | unique familiar `target_role_id` | confirm/attach/context actions | familiar/agent integration tests proving confirmed-only context |
| Edit confirmed role | same aggregate/timestamps | update-confirmed policy/action/request/route | save-and-reconfirm UI; atomicity and context refresh tests |
| One active; archive older | user/status index; timestamps | lock in confirm action; archive action/route | concurrency/idempotency and archive-list tests |
| Familiar remains role-tied | familiar FK; drop per-user unique | attach action never retargets | first attachment/new-role familiar/history tests |
| Deferred domains | no schema | no endpoints/actions | architecture assertions and prop tests prevent scope leakage |

### Artifact-to-requirement map

This inverse map makes every required implementation artifact explicit. “None” is intentional where a requirement must not invoke AI or queue work.

| Artifact | Planned component | Requirements covered |
| --- | --- | --- |
| Migration | Create `target_roles`, `role_responsibilities`, `role_competencies`, and `role_analyses`; alter `user_familiars` relationship/indexes | R1–R11; R12 is proven by absence of other tables |
| Model | `TargetRole`, `RoleResponsibility`, `RoleCompetency`, `RoleAnalysis`; update `User` and `UserFamiliar` | R1–R11; R12 has no model |
| Enum | Target role status/source, seniority, competency category, priority, data source, analysis status | R1–R10; R11 keeps existing familiar enums; R12 adds none |
| Policy | `TargetRolePolicy`, parent-authorized analysis access, existing `UserFamiliarPolicy` | R1–R11; R12 exposes no ability |
| Action | Create/update/start/complete/fail/confirm/archive/attach/context actions described above | R1–R11; R12 has no action |
| Form request | Store, update, start-analysis, confirm, and archive requests | R1–R10; R11 is server-derived during confirm; R12 has no request |
| Controller | Resource target-role controller plus invokable analysis, confirm, and archive controllers | R1–R10; R11 is delegated by confirm; R12 has no controller |
| Route | Named authenticated/verified routes in the controller table above | R1–R10; R11 has no direct mutation route; R12 has no route |
| Inertia page | Target-role index/create/show-review pages plus minimal familiar-page context | R1–R11; R12 has no page |
| AI agent | `JobListingAnalyzer` for R2/R6/R7 and updated confirmed-context `PrototypeInterviewGuide` for R8; none for manual writes, confirmation, archive, or familiar attachment | R2, R6–R8; explicitly none for R1, R3–R5, R9–R12 |
| Queue job | ID-only `AnalyzeJobListing` | R2, R5–R7; explicitly none for R1, R3–R4, R8–R12 |
| Test | Feature/unit/architecture coverage listed in the Pest plan | R1–R12 |

## Pest test plan

Create focused Feature tests for target-role CRUD/authorization, analysis dispatch/lifecycle, review/confirmation, archive/active-role behavior, familiar attachment, Inertia props, and coaching context. Add unit/feature coverage for the structured response mapper and terminal analysis immutability. Use factories and datasets for enum validation.

Required high-risk assertions:

- another user receives 403/404-equivalent protection and never sees source/proposal data;
- a fake analyzer is never prompted on validation/auth/config failure;
- a successful fake proposal does not change profile fields before user review;
- `Queue::fake()` sees one ID-only job dispatched after commit;
- serialized job payload and captured logs omit a distinctive listing sentence;
- malformed output produces a safe failed attempt without raw provider data;
- retry appends rather than mutates a terminal analysis;
- two confirmations cannot leave two confirmed roles for one user;
- first confirmation preserves the existing familiar and progression;
- later confirmation creates a new Queue Wyrm and does not retarget the old one;
- familiar coaching uses confirmed user-reviewed content and excludes raw listing, assumptions, and internal metadata;
- normal Inertia props omit internal IDs, user IDs, raw source text, provider/model/usage, and other users' data.

After implementation, run the focused Pest files, then `vendor/bin/pint --dirty --format agent`, PHPStan, TypeScript, ESLint, Prettier check, production build, and the full Pest suite.

## Milestone exit criteria

1. An authenticated verified user can create a manual role, review it, and explicitly confirm it.
2. A pasted listing creates an encrypted private source, an append-only analysis attempt, and an ID-only queued job.
3. A successful bounded structured proposal is visible for correction with evidence, sources, assumptions, ambiguities, and confidence; it is not authoritative before review.
4. Failure and retry are safe and recoverable, and retry creates a new analysis row.
5. Every target-role read/write is owner-authorized and cross-user tests pass.
6. Confirmation is atomic, enforces one active confirmed role, and archives the previous role.
7. A Queue Wyrm is attached one-to-one to the confirmed role; later roles do not reuse an attached familiar.
8. Queue Wyrm receives only the minimal confirmed profile as coaching context, with no role-based progression or readiness behavior.
9. Full listing text, prompts, responses, provider bodies, keys, and internal analysis metadata are absent from logs and normal browser props.
10. No out-of-scope assessment, practice, STAR, scoring, matching, mock-interview, or PixelLab behavior exists.
11. Focused and full backend tests, formatting/static analysis, frontend types/lint/format, and production build pass.
12. Manual browser review confirms pending polling, review labels, confirmation, mobile layout, dark mode, reduced motion, and accessible status announcements.

## Open decisions

- Choose and document the production OpenAI text model in `ROLE_FAMILIAR_TEXT_MODEL`; add the variable (blank) to `.env.example` during implementation.
- Confirm the 50,000-character listing limit and retention/deletion policy for archived raw listings. The contract defaults to retaining the encrypted source with the archived role.
- Decide whether a user may manually acknowledge unresolved assumptions or must edit/remove every inferred item. This plan permits explicit acknowledgement while keeping uncertainty out of coaching context.
- Decide whether archived roles ever become restorable. Restoration is intentionally not part of this milestone.
- Decide whether a user with no confirmed role receives general Queue Wyrm guidance or a role-creation call to action. In either case, draft/analysis content must not be used.

## Recommended next prompt

Implement the Target Role persistence and manual create/review/confirm/archive flow only. Include the familiar-to-role migration and owner authorization, but do not add AI analysis yet. Use the contracts in `docs/product/target-role-mvp.md`, `docs/implementation/target-role-plan.md`, and `docs/ai/job-listing-analysis-contract.md`; use Boost docs first, Pest tests, Wayfinder, thin controllers, form requests, and invokable actions.
