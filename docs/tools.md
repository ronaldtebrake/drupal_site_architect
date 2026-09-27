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

The compact assessment (`agent-plan-v2`) contains:

- `work_areas`: starting points, configuration links, requirement parts, target
  content types and field references. Parts remain `supported`, `partial`, `open`
  or `check`; `integration_verified` is always false.
- `candidates`: shared candidate references, availability and conditional
  acquisition/configuration steps. Alternatives are not an install-all list.
- `discovery`: search action and reason, extraction coverage, source warnings,
  unassigned passages and truncation.
- The site fingerprint, `needs_review` and individual judgment evidence:
  choice, probability, confidence and review status. Unknown scores remain null.

Component usefulness, candidate selection and requirement coverage are separate
judgments. Preserve review flags and inspect dependencies before building.
Probabilities are not percentages of requirements completed or proof of compatibility.
The full response retains omitted alternatives and score distributions.

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
