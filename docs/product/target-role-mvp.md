# Target Role MVP

## Outcome

This milestone lets an authenticated user create, review, and confirm the role that Queue Wyrm will coach them toward. A role may be entered manually or proposed from a pasted job listing. The confirmed, user-owned profile—not the raw listing or unreviewed AI output—becomes Queue Wyrm's coaching context.

Queue Wyrm remains the only familiar. Familiar matching, readiness scoring, assessments, practice sessions, STAR libraries, mock interviews, and new progression rules are outside this milestone.

## Locked product decisions

1. Queue Wyrm is the only familiar created or displayed.
2. Familiar matching is deferred.
3. Job-listing analysis is a proposal and never authoritative user data.
4. A user must review and explicitly confirm a role profile before it can affect coaching.
5. The original pasted listing is retained privately for the owning user.
6. AI assumptions, ambiguities, confidence, and source evidence are visible during review.
7. AI must not invent employer facts, role facts, or user experience.
8. Only a confirmed role profile becomes coaching context.
9. A confirmed role remains editable through an explicit “save and reconfirm” action.
10. The MVP has at most one confirmed, active role per user. Confirming a new role archives the prior role.
11. A familiar is attached to one target role. A later role receives its own Queue Wyrm; an older Queue Wyrm remains attached to its archived role.
12. No baseline assessment, practice session, STAR library, readiness score, mock interview, or speculative product domain is added.

## User journeys

### Manual role

1. The user starts a manual target role.
2. They enter a title, optional company and seniority, a summary, ordered responsibilities and competencies, and optional interview date and biggest concern.
3. The role remains a draft until it has the minimum confirmable fields.
4. The user reviews a single profile preview and explicitly confirms it.
5. Confirmation makes the role active, archives any older active role, and attaches a Queue Wyrm.

Manual fields are marked as user-supplied. The interface must not imply that manual content was AI-verified.

### Pasted job listing

1. The user pastes listing text and starts analysis.
2. The application privately saves the original text, records an analysis attempt, and queues work.
3. The review page polls a small status prop while analysis is pending. It shows a recoverable failure state if analysis fails.
4. A successful proposal displays normalized fields, ordered responsibilities, competencies, tools, likely interview focus, source evidence, ambiguities, assumptions, and confidence.
5. The user corrects, removes, reorders, or adds content. AI-inferred items remain visibly labeled until corrected or accepted.
6. The user explicitly confirms the reviewed profile. The raw listing and unreviewed proposal never become coaching context by themselves.

If analysis fails, the original listing remains private and available to its owner. The user may retry as a new analysis attempt or complete the profile manually.

### Edit and archive

A confirmed role can be opened in the same review editor. Saving changes requires an explicit “save and reconfirm” submission, updates the confirmation timestamp atomically, and keeps the role active. Archiving removes the role from active coaching but does not detach or repurpose its familiar. Archived roles are read-only in this milestone; restoration is deferred.

## Confirmation rules

A role is confirmable only when it has:

- a non-empty title;
- a non-empty role summary;
- at least one responsibility;
- at least one competency;
- no pending analysis attempt; and
- explicit user confirmation in the submitted request.

Unresolved ambiguities and assumptions must be shown immediately before confirmation. They do not have to be converted into invented facts: the user may correct the profile, remove the affected proposal item, or explicitly acknowledge the remaining uncertainty.

## Queue Wyrm coaching context

The server builds a minimal coaching-context value object from the active confirmed profile. It may contain the role public ID, title, explicitly confirmed company, seniority, summary, ordered responsibilities and competencies, interview date, and biggest concern.

It must exclude the original listing, source evidence, unresolved AI assumptions, analysis confidence, provider/model/usage data, other roles, and unrelated user attributes. Existing listening/thinking animation remains transient client state. Existing prototype progression remains server-authoritative and is not recalculated from the role.

On first confirmation, the user's existing unassigned prototype Queue Wyrm is attached to that role so current progression is preserved. Confirming a later role creates a new summoned Queue Wyrm for the new role; it does not move the older familiar.

## Trust, privacy, and safety

- Listing text is untrusted data, not agent instructions.
- Full listing text, full prompts, and full model responses are not logged by default.
- The listing is encrypted at rest through an Eloquent encrypted cast and stored in a `longText` column.
- Queue payloads contain model identifiers only, never listing text.
- Only the owning authenticated user may view or mutate a role or see its source listing.
- Review-specific Inertia props may expose the proposal, assumptions, evidence, and original listing to the owner. Normal familiar props must not expose raw source text or internal AI metadata.
- OpenAI configuration remains server-only under `config/ai.php`; no API key or provider secret enters Vite configuration or browser props.
- The model receives only the listing and fixed analysis instructions. It must not infer protected personal traits.

## Acceptance summary

The milestone is complete when both entry paths lead to an owner-authorized review, explicit confirmation is the only route into coaching context, analysis failures are recoverable, one active role is enforced transactionally, archived role/familiar history remains intact, and automated tests prove the privacy and authorization boundaries.
