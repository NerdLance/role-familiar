# AI reaction prototype audit

## Scope

This is the first minimal OpenAI interaction for Role Familiar. It accepts one short interview-preparation question and returns only a concise response, a controlled reaction, and an optional suggestion for one existing prototype quest. It does not persist prompts or answers, create quests, complete quests, evolve familiars, call tools, stream, or retain chat memory.

## Agent architecture

`App\Ai\Agents\PrototypeInterviewGuide` is a Laravel AI SDK `Promptable` agent implementing `HasStructuredOutput`. It is explicitly configured for the SDK's `Lab::OpenAI` provider, with the text model read from `ai.providers.openai.models.text.default` (`ROLE_FAMILIAR_TEXT_MODEL`) rather than hardcoded in application code.

`AskPrototypeInterviewGuide` checks configuration, invokes the agent, requires a `StructuredAgentResponse`, and maps it through `PrototypeInterviewGuideResponse`. The mapper rejects missing, oversized, or arbitrary reaction and quest keys. The controller exposes a generic JSON response for the Inertia v3 standalone HTTP request on unavailable configuration, provider failure, timeout, rate limiting, or malformed output.

Every handled failure receives a UUIDv7 reference and an application warning log entry. The entry records only safe diagnostic metadata: failure category, exception class/code, internal contract reason, optional type or length metadata, authenticated user ID, and submitted-message length. It deliberately excludes the submitted question, model answer, raw provider payload/error text, and API keys.

## Response contract

```json
{
  "message": "Concise user-facing guidance",
  "reaction": "neutral | encouraging | challenging | clarifying",
  "suggested_quest": "behavioral_story | star_response | company_research | technical_review | null"
}
```

The SDK JSON schema and the response mapper both enforce the finite reaction and quest sets.

## Familiar behavior

On `/familiar`, the listening overlay appears only while the user is actively typing and clears after 700 ms of inactivity or input blur. A pending standalone HTTP request always takes precedence and displays the thinking overlay until it completes, then returns to the normal familiar state. The request disables duplicate submission. A controlled reaction renders a small reduced-motion-safe CSS/SVG-equivalent glyph effect. A suggested quest is only an explicit button; it is never auto-completed. No AI request changes persistent familiar stage or quest progress.

## Verification

- SDK fakes test authentication, validation, throttling, controlled prompt input, all four reactions, allowed suggestion mapping, malformed output, missing configuration, provider failure, and unchanged progression.
- One real smoke request was performed through the Laravel AI SDK using a configured local OpenAI key and model. It completed successfully with the controlled `encouraging` reaction. No key, provider error, prompt, or model response was printed.
- Browser visual verification remains blocked by the desktop environment's loopback policy; no visual sign-off is claimed.

## Deferred AI capabilities

- Job-listing and competency analysis
- Readiness scoring or permanent progression decisions
- Story memory, conversation persistence, and chat history
- Tools, voice, streaming, attachments, and provider failover
- Automatic quest creation or completion
