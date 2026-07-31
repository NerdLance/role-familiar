# Progression prototype audit

## Scope

This implementation adds the first persistent Queue Wyrm domain only. It does not add AI, role or job analysis, readiness scoring, currencies, social features, additional species, or PixelLab calls.

The evolution thresholds are deliberately temporary prototype rules:

- Summoned → Attuned requires two distinct completed prototype quests.
- Attuned → Interview-ready requires all four prototype quests.

They are not a final readiness model.

## Persistent schema

- `user_familiars` stores the owning user, a public ULID route key, Queue Wyrm species key, display name, current string-backed stage, and summoning/evolution timestamps. `user_id` is unique, enforcing one prototype familiar per user.
- `familiar_quest_progress` records a familiar's completed quest keys. The `(user_familiar_id, quest_key)` unique index makes completion idempotent.
- `familiar_progression_events` is an append-only timeline with `created_at`, an occurrence timestamp, an event type, and narrowly scoped metadata. It has no `updated_at` column. Event metadata is restricted by the actions to species keys, quest keys, and stage transitions; it never stores prompts, job listings, or interview answers.

## Server authority

`SummonPrototypeFamiliar`, `CompletePrototypeQuest`, and `EvolvePrototypeFamiliar` are invokable actions. Quest completion locks the familiar and performs progress creation, the quest event, and any single allowed evolution in one database transaction. Repeated requests do not create duplicate progress or events.

The client sends only a known quest key. It cannot submit a stage. The server returns the refreshed familiar through the normal Inertia redirect, and the React page compares the previous and returned stages to choose the matching one-shot catalog evolution asset.

`UserFamiliarPolicy` allows a write only when the authenticated user owns the familiar. The POST endpoint authorizes that policy before it invokes the action. Route model binding uses `public_id`, not the database primary key.

## Interface findings

`/familiar` is the authenticated persistent page; `/familiar-lab` remains the separate development tool. The persistent page uses the validated server manifest, the existing sprite renderer, the server-returned familiar stage, and Wayfinder-generated action URLs. It supports static thinking/listening demo controls, persisted quest completion, a short accessible celebration announcement, and reduced-motion evolution fallback.

The build succeeded and tests exercised both stage transitions from actual Laravel action responses. Interactive browser checks were not run: the desktop browser environment blocks loopback navigation under its network policy, so no visual result is claimed for the transition playback, mobile layout, dark mode, or reduced-motion media-query rendering.

## Verification

- Focused Pest progression suite: passed (9 tests, 60 assertions).
- Full Pest suite: passed (57 tests, 285 assertions).
- Laravel Pint: passed.
- PHPStan: passed at 512 MB memory.
- TypeScript check, ESLint, and Prettier check: passed.
- Production frontend build: passed.

## Intentionally temporary

- The only species is `queue_wyrm`.
- The default name is `Queue Wyrm`; there is no rename flow yet.
- Quests use the four prototype keys and fixed display labels.
- Evolution thresholds are prototype-only and have no scores, attributes, XP, currencies, or permanent role-readiness meaning.
