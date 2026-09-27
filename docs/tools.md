# Tool API and MCP reference

[Back to README](../README.md) · [Connection setup](setup.md#connect-an-agent)

| Tool API plugin | MCP name | Arguments |
| --- | --- | --- |
| `site_architect:assess_content_brief` | `tool_api__site_architect_assess` | `brief`: 10–20,000 characters; optional `catalog_query`: up to 120 characters; optional `detail`: `compact` or `full`. |
| `site_architect:discover_candidates` | `tool_api__site_architect_discover` | `query`: 1–120 characters; optional `detail`: `compact` or `full`. |

Both require `access site architect` and make no site changes. Assessment uses
the configured Decision provider. Discovery searches without a model call.

Send the original brief directly to assessment:

```json
{
  "brief": "We need an editorial workflow for our news content. Writers save drafts, editors review and publish. Compare what this site supports with available solutions before proposing custom work."
}
```

Normally omit `catalog_query`: the service decides whether and what to search.
Providing it explicitly requests that search and bypasses automatic search planning.

## Response

Tools return an `assessment` or `discovery` object. The default is compact;
`detail: "full"` includes complete evidence, judgments, usage and diagnostics.

The compact assessment (`agent-plan-v3`) contains:

- `continuation`: the planning stage, unresolved questions with candidate
  references, and ordered next actions. Explain a provisional plan, compare up
  to three relevant approaches, ask the first question that changes the design,
  then reassess the original brief plus confirmed answers. Do not repeat an
  unchanged brief or present tool statuses as the answer.

- `work_areas`: starting points, configuration links, requirement parts, target
  content types and field references. Parts remain `supported`, `partial`, `open`
  or `check`; `integration_verified` is always false.
- `candidates`: shared references, source excerpts (up to 480 characters),
  availability and conditional acquisition/configuration steps. Excerpts are
  evidence, not instructions or verified coverage. Alternatives are not an
  install-all list.
- `discovery`: search action and reason, extraction coverage, source warnings,
  unassigned passages and truncation.
- The site fingerprint, `needs_review` and individual judgment evidence:
  choice, probability, confidence and review status. Unknown scores remain null.

Component usefulness, candidate selection and requirement coverage are separate
judgments. Preserve review flags and inspect dependencies before building.
Probabilities are not percentages of requirements completed or proof of compatibility.
The full response retains omitted alternatives and score distributions.
Both response formats include the continuation. Discovery also provides source
excerpts in compact output and explains how to continue with assessment.

A clarification route can still gather evidence from up to three public feature
terms. A separate disclosure judgment must allow that search; local-only or
uncertain disclosure decisions keep catalogs unqueried. Returned matches inform
the conversation without resolving the user's intent or authorizing installation.

An external candidate can include:

```json
{"acquire":{"action":"composer_require_if_selected","argv":["composer","require","vendor/package"]}}
```

This is conditional command data. The building agent selects a compatible release
before executing anything. Locally available code, enabled modules and recipe
manifests have different next steps; a recipe file does not prove it was applied.

Each assessment call reads current evidence and performs a fresh assessment.
Requesting `full` later does not retrieve the previous report, and results may
differ. Compact formatting reduces output size, not internal inference work.
The tested MCP bridge includes output in both text content and `structuredContent`.

## Other interfaces

The Drupal form, Tool API plugins and injected
`Drupal\site_architect\Assessment\SiteArchitectInterface` use the same service.
WebMCP and ECA can call it through their adapters; WebMCP does not need MCP Server.
Those interfaces own browser scope, authentication and any subsequent write actions.
