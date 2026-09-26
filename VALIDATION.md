# Validation record

Verified on 26 September 2026. Software checks and live smoke observations,
not a model evaluation dataset, accuracy calibration or cost comparison.

## Environment

- Drupal 11.4.6, PHP 8.3.19, Drupal AI 1.5.0-rc4.
- AI Decision and AI Provider TypeSafe AI `dev-1.0.x`.
- Decision default: `typesafeai` / `jev-latest`; reported model `jev-1.13.0`.
- Tool API 1.0.0-beta8; Canvas 1.11.0 on the demo site.
- Project Browser 2.1.5; API Browser 2.0.0-beta1.
- MCP Server 2.0.0-beta2; MCP Server Tool Bridge 1.0.0-beta1. The site's
  pinned server version requires this older bridge; newer bridge releases have
  newer server requirements.
- No provider credentials or credential-bearing configuration exported.

## Automated verification

PHPUnit: **18 tests, 122 assertions**, passing against isolated SQLite databases.
No inference or external catalogue requests are made by the automated tests.

- Standalone installation brings in declared dependencies and optional Workshop
  configuration without enabling Canvas, Canvas Tools, Tool API, WebMCP,
  Project Browser, API Browser, MCP Server or the TypeSafe provider.
- Adding a real node field changes collected evidence and its fingerprint.
  Ownership metadata and arbitrary provider configuration are excluded.
- The form survives serialization/restoration for subsequent AJAX submissions.
- A rejected model response clears previous advice and displays a safe retry
  message without exposing raw response details.
- A new recipe in a configured directory appears without changing catalogue
  code or clearing caches. Editing its manifest changes its hash; adding a
  second recipe is discovered immediately. A malformed manifest is reported
  while the valid one remains available. An `ECA` keyword matches local recipes.
- Local code presence never becomes an “applied” or compatibility claim.
- A real moderation workflow created directly as configuration supplies states,
  transitions and assigned bundles. Changing a transition changes the site
  fingerprint. Disabled workflows are excluded. No recipe history is required.
- The optional Project Browser adapter queries a configured public source plugin
  using only the search keywords. It preserves unverified compatibility claims,
  distinguishes a remote recipe from an enabled module, strips catalogue HTML,
  reports truncation and retains local results if the source fails.
- Discovery checks access before calling sources, supports local-only searches,
  alternates source results, and limits the candidate set. Source exception
  details are not returned.
- Optional Tool API and MCP mappings install through their declared dependencies.
- Assessment tests cover access before inspection/inference, independent
  presentation advice, uncertainty, contradictory judgments, unavailable Canvas,
  incomplete answers and malformed probability distributions.
- Drupal/DrupalPractice coding standards, Composer metadata validation,
  JavaScript syntax and Git whitespace checks pass.

Commands and optional test dependencies are described in
[README.md](README.md#extending-and-validating).

## Live ecosystem discovery

Enabled Project Browser sources on the demo site:

- `drupalorg_jsonapi` (contributed modules).
- `recipes` (local UI source; the adviser uses its own manifest reader).
- `api_browser_project:packagist_recipes` (API Browser's upstream configuration).

The local source discovered **28 manifests**. A `workflow` search found one local
recipe, 604 module matches reported by Project Browser, and three remote recipe
matches reported by the configured Packagist source:

- `cntlscrut/drupal-eca-news-workflow`
- `drupal/orchestration_recipe_workflow_blog`
- `drupal/varbase_workflow_base`

The adviser assessed **12 candidates**, including all three remote recipes, and
marked the search as truncated. These counts are observations of a bounded,
source-cached search and can change. None of these recipe packages was installed
or applied for this verification; their dependencies and applicability have
not been verified. Project Browser's package-installation UI remains disabled.

## Real MCP HTTP verification

Used the site's actual `/mcp` endpoint over HTTP, not direct PHP plugin invocation:

1. Anonymous `initialize` returned **401**.
2. An authenticated Drupal session initialized successfully (**200**) using MCP
   protocol version `2025-03-26`.
3. `tools/list` exposed both adviser wire names and their string-length limits:
   `tool_api__ai_site_advisor_discover` and `tool_api__ai_site_advisor_assess`.
4. `tools/call` on discovery returned source reports and local/remote candidates
   through MCP Server → Tool Bridge → Tool API → Project Browser.
5. `tools/call` on assessment used the configured Jev provider and returned the
   current site evidence and judgments over that discovered candidate set.
6. A DELETE request was sent to terminate the temporary MCP session. Test credentials, cookies and
   session IDs were not stored in this repository or printed in the results.

For the editorial-workflow brief, the model classified content modeling and
presentation as `not_applicable`, found two existing workflows to consider
extending, and marked six catalogue candidates confidently relevant. The
summary advised inspecting existing workflow configuration first. The existing
Article type remained uncertain. These are observed model judgments, not
confirmed fitness or installation recommendations.

The assessment call reported 11,873 input and 1,110 output tokens, with 7.497 s
server time including discovery. Repeated UI requests were faster and reported
the same usage figures. The provider stack may cache responses and their usage;
these figures must not be presented as new billed tokens on each request or
as evidence of savings. Cached-input and billing breakdown are unavailable
through this response contract.

Enabling the upstream bridge imported optional mappings from other installed
modules. Only newly imported, unrelated mappings were disabled during setup;
pre-existing mappings were preserved. The adviser owns two enabled mappings.

## Browser verification

- The workflow example populates both the brief and the `workflow` search term.
- The form renders live Jev advice, two workflow starting points, local and
  remote candidate availability, review flags and source match counts.
- Transitions/bundle assignments, source evidence and upstream project links
  are inspectable. Long catalogue text is collapsed into evidence disclosures.
- Changing the search hides the previous assessment. A subsequent AJAX
  assessment works on the same page.
- One live response was rejected by the assessment contract checks. No partial
  advice was shown. Subsequent calls, including a repeated AJAX submission,
  succeeded. The validation rules were retained; the retry message was clarified.
- Desktop layout was visually inspected in the in-app browser.
- Initial phase-one checks also verified anonymous adviser denial, anonymous
  Tool API denial, the real Workshop node form, Workshop reuse/price-field
  extension, Canvas campaign presentation and clarification for vague briefs.

## Remaining boundaries

The MCP protocol and Drupal UI have been exercised; an autonomous LLM agent's
complete planning/building session has not been benchmarked. No WebMCP or ECA
adapter was configured specifically for this adviser. Those interfaces can
reuse its services or Tool API plugins through their own adapters.

Package compatibility, recipe conflict simulation, role permissions, ECA runtime
behavior, approved installation/build operations and model evals remain separate
work. The current tools provide grounded discovery and typed advisory judgments.
