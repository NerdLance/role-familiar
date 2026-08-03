# Target Role milestone audit

## Scope and conclusion

The Target Role milestone now has a complete owner-authorized path from manual entry or pasted-listing analysis through review, confirmation, and role-specific Queue Wyrm assignment. A confirmed role owns one familiar; a user may own many role/familiar pairs. No familiar matching, baseline assessment, practice chat, or readiness model was added.

## Schema

- `target_roles` remains the user-owned aggregate root with encrypted raw listing text, confirmation/archive timestamps, ordered responsibilities and competencies, and append-oriented analyses/reviews.
- `user_familiars.target_role_id` is nullable for pre-role legacy familiars, unique for one familiar per role, and protected by a restrictive foreign key. The former unique `user_id` constraint is replaced by a normal ownership index.
- Existing unassigned Queue Wyrms are attached only to the user's active confirmed role during backfill or first confirmation. An active confirmed role without a familiar receives a new Stage 1 Queue Wyrm. Attached familiars are never retargeted.
- Quest progress and progression events continue to belong to `user_familiars`, so each role keeps isolated progression history.

## Policies and authorization

- Target-role route binding is owner-scoped and returns no cross-user role record.
- Familiar mutation still requires ownership and now also requires the familiar's target role to be active. Archived familiar history remains viewable through its role but cannot receive quest progress.
- Familiar assignment validates user/role ownership again inside the locked transaction. There is no public endpoint that accepts a target-role/familiar pairing.

## Manual and analysis flows

- Manual role writes remain transactional and user-authored.
- Listing analysis remains a proposal. The structured agent treats listing text as untrusted data, uses bounded schema values, has no tools, and does not receive résumé or familiar progression data.
- Review persistence keeps corrected role fields authoritative and retains the immutable analysis attempt. Focus suggestions and clarification decisions are copied into the applied review snapshot; future familiar context never reads the raw structured result.
- Prompt-injection tests verify embedded listing commands remain data. Provider access stays behind Laravel AI SDK agents and the queue job contains identifiers rather than listing text.

## Confirmation and familiar association

- Confirmation locks the user aggregate, archives the prior active role, confirms ordered child records, and assigns the Queue Wyrm in the same database transaction.
- First confirmation may preserve the one unassigned prototype familiar and its progression. Later roles receive new Queue Wyrms at `summoned` / Stage 1.
- Reconfirmation preserves the existing familiar identity, timestamps, stage, quests, and events.
- Assignment records a `target_role_assigned` progression event containing only species key and public role ID. It stores no listing, prompt, analysis, employer detail, or user attributes.

## Confirmed role context

`ConfirmedRoleContext` accepts only an active confirmed role and assembles:

- confirmed title, company, seniority, and summary;
- ordered responsibilities whose `user_confirmed_at` is set;
- ordered confirmed competencies, with tooling separated as technologies;
- focus areas copied into the user-applied review snapshot;
- clarification cards still explicitly marked unresolved in that snapshot.

It excludes raw listing text, analysis structured output, source evidence, confidence scores, assumptions, provider/model/usage metadata, progression, other roles, and unrelated user data. The deterministic acknowledgement uses at most three confirmed competency/technology names, falling back to confirmed responsibilities. No LLM is invoked.

## Familiar page and public props

- `/familiar` resolves only the active role and its own familiar.
- Props expose public familiar identity/stage/quest completion plus the controlled role context, interview date, and deterministic acknowledgement.
- The page shows company, interview date, top responsibilities, top competencies, unresolved ambiguities, edit link, and the familiar's existing stage presentation.
- With no active role, it renders a Stage 1 Queue Wyrm preview and confirmation guidance, without borrowing an archived familiar.
- Raw source, internal IDs, ownership IDs, analysis metadata, and assumptions are absent from normal Inertia props.

## Queue and logging review

- Analysis requests append an analysis record and dispatch the ID-only `AnalyzeJobListing` job after commit.
- Duplicate in-flight requests, stale source hashes, provider failures, and terminal analysis immutability remain covered.
- Application failure logging uses sanitized categories/summaries; full listings, prompts, model responses, and API keys are not logged by default.
- Familiar assignment is synchronous domain work inside confirmation and dispatches no AI or external job.

## Test coverage

Coverage includes role/familiar relationships, first assignment, existing familiar preservation, safe assignment metadata, later-role isolation, archive behavior, active-only familiar mutation, confirmed-context filtering, review-snapshot ambiguities, assumption exclusion, cross-user denial, Inertia prop privacy, no AI prompt, and no evolution from confirmation.

## Findings and follow-ups

1. The core milestone boundaries are satisfied: confirmed user data is the only familiar context, and progression is role-specific.
2. Existing prototype interview guidance remains deliberately general and does not receive `ConfirmedRoleContext`. Connecting this context to a coaching agent is the next milestone.
3. Confirmation now independently requires a non-empty role summary as well as title, responsibilities, and competencies, matching the product contract before any context can be built.
4. Restore is already an explicit active-role switch: it archives the current active role and preserves both familiar histories. No additional role-switching selector was introduced.
