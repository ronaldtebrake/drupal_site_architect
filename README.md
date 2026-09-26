# AI Site Advisor

Help an agent decide what to reuse, investigate or build on a Drupal site.
The adviser reads the current site structure, identifies capabilities in a
brief, decides whether ecosystem searches would help, and returns a draft plan
grounded in the site and discovered recipe/module candidates.
The phase-one demo uses **Jev through the TypeSafe AI provider**.

A site builder can inspect the same advice in a normal Drupal form. Optional
submodules connect Project Browser, Tool API and MCP Server. The main service
works independently of those integrations and of Canvas, WebMCP and ECA.

For example: “We need an editorial workflow for our existing news content.
What can we reuse here, which ecosystem solutions should we investigate, and
what would remain to build?” The adviser compares actual moderation states,
transitions and assigned content types with discovered recipes and modules.
It works whether the existing workflow came from a recipe, a site builder or
custom code. Recipe application history is not required.

This is read-only advice. A relevance judgment is not a compatibility check or
permission to install. No recipe is applied, package installed, content created
or configuration changed by either adviser tool. Phase one includes software
tests and live checks; model evals and cost comparisons remain later work.

## Requirements and standalone installation

- PHP 8.3+, Drupal 11.2+ within Drupal 11, and core Node.
- Symfony String `^7.3` for English singularization (declared in Composer).
- [Drupal AI](https://www.drupal.org/project/ai) `^1.5@RC`.
- [AI Decision](https://www.drupal.org/project/ai_decision) `^1.0@dev`.
- A configured default **Decision** provider and model in Drupal AI.

This directory is a self-contained module with its own Composer metadata and
license. Until it has a published release, place it at
`web/modules/contrib/ai_site_advisor`, or use a Composer path/VCS repository.
From the Drupal project root:

```sh
composer require 'drupal/ai:^1.5@RC' 'drupal/ai_decision:^1.0@dev'
drush en ai_site_advisor -y
```

For Jev, install
[AI Provider TypeSafe AI](https://www.drupal.org/project/ai_provider_typesafeai):

```sh
composer require 'drupal/ai_provider_typesafeai:^1.0@dev'
drush en ai_provider_typesafeai -y
```

Configure its credential through Drupal Key and choose the Jev model as the
default **Decision** model. Credentials and provider configuration are never
shipped with this module. Pin development dependencies in the host site's lock
file; tested versions are recorded in [VALIDATION.md](VALIDATION.md).

Grant `access ai site advisor` to trusted site builders. It permits structural
metadata inspection, configured catalogue searches and provider calls.

- Adviser: `/admin/structure/ai-site-advisor` (Structure → AI Site Advisor).
- Policy, content-type scope and additional recipe directories:
  `/admin/config/ai/site-advisor`.
- Optional sample content type: `drush en ai_site_advisor_demo -y` creates a
  regular Workshop node type with description, date, location and capacity.
  Its normal form is `/node/add/advisor_workshop`. It creates no content records.

In DDEV, prefix Composer and Drush commands with `ddev`.

## Dynamic recipe discovery

There is no fixed list of recipe names. The local source discovers `recipe.yml`
files from:

- Drupal core's `core/recipes` directory.
- Composer packages of type `drupal-recipe`, using their recorded install paths.
- Conventional `recipes` directories at the Composer root and Drupal docroot.
- Additional directories configured in the adviser settings, relative to the
  Composer root or absolute. Directory scans include manifests up to two
  subdirectory levels down and follow links. Add a nearer root for deeper trees.

The source reads names, descriptions, declared extensions, included recipe names,
configuration-action targets and a source hash. It also reads a sibling
`composer.json` for package identity when present. A newly added or edited
manifest appears on the next discovery call without editing this module.
Malformed manifests are reported while valid results remain available.

Local availability means **code is present**. It does not prove that a recipe
was applied, that its configuration remains in use, or that applying it would
be compatible. The site collector separately reads current configuration.

## Discovering the ecosystem through Project Browser

Enable the optional adapter:

```sh
composer require 'drupal/project_browser:^2.1'
drush en ai_site_advisor_project_browser -y
```

The adapter calls Project Browser's public source plugin API and respects its
enabled-source configuration. It accepts recipe and module projects. The built-in
local recipe source is skipped because the main module already reads those
manifests with richer evidence.

Project Browser supplies a contributed-module catalogue. To include recipes
that have **not** been downloaded or applied, the demo uses
[API Browser](https://www.drupal.org/project/api_browser):

```sh
composer require 'drupal/api_browser:^2.0@beta'
drush en api_browser -y
```

At `/admin/config/development/project_browser`, enable **Packagist Drupal
Recipes**, keeping any existing sources you need. API Browser ships this source
configuration; its plugin ID is `api_browser_project:packagist_recipes`.
Use its **API Browser Services** settings to configure other external JSON
catalogues. The adviser has no dependency on Packagist or a particular source ID.
Project Browser's installation UI does not need to be enabled for discovery.

Enter the requirement in the original brief; there is no separate search field.
When an ecosystem adapter is available, Jev receives the brief and current site
structure and chooses one of three paths:

- **Search** when comparing existing solutions would help. Jev selects source
  phrases for several capabilities, such as events, groups and activity stream.
- **Local** when current site/core configuration is a sufficient starting point,
  or the brief explicitly asks to stay local.
- **Clarify** when the requirement or search decision is too uncertain.

Only the search path queries external catalogue adapters. Local recipe files
remain available on every path. The resulting candidates then inform a second
Decision request assessing fit. Without an ecosystem adapter, the adviser skips
search planning and assesses the local evidence directly.

The route and capability questions share one planning request. Code splits the
brief at punctuation and common conjunctions, supplying adjacent one- and
two-word source phrases for up to 12 clauses, 32 words per clause. URLs, email
addresses and tokens with digits are excluded. Jev selects a phrase or `none`
for each clause. Up to six distinct capabilities are retained, including compound
nouns such as `activity stream`; English singularization turns `groups` into
`group` and `events` into `event`. Only the search route sends these terms to
external sources. Local plans retain their capability scope without searching.

No topic-to-module mapping or recipe list is hardcoded. This is bounded source
selection, not arbitrary synonym generation or guaranteed anonymization. The
English clause splitter can miss implicit requirements and complex prose. The
UI exposes unmapped clauses and extraction truncation for review. Current module
labels/descriptions enrich the site evidence, but do not prove that their
configuration or integrations work.

The result shows the selected path, its predefined criterion and any search
term. Uncertain routes and the absence of a suitable term trigger a review flag
and no ecosystem search. These criteria are static descriptions of the options,
not generated explanations of the model's reasoning.

Discovery reports source IDs, candidate packages, availability, match counts,
truncation and failures. A direct search returns at most 12 candidates,
alternating local and Project Browser results and Project Browser sources.
The adapter fetches up to 24 source results and prefers project-name matches
over incidental description mentions. Compound assessments search each selected
capability separately, deduplicate results and share a 24-candidate assessment
budget across queries. Each candidate retains its matching queries and each
source report retains its query. These lexical preferences are not semantic
relevance judgments; Jev assesses the retrieved descriptions afterward.
Stable IDs are based on kind and package, with separate core component IDs.
Duplicate packages retain additional source references.

Search is bounded, and upstream sources manage their own caches. A source may
hide upstream fetch failures or provide fixed compatibility flags. Such flags
remain explicitly **unverified source claims**. No results, partial results or
high relevance cannot establish that custom development is necessary or that a
package is safe to adopt. Inspect dependencies, recipe actions, configuration
overlap and target-site compatibility before choosing an installation plan.

## Tool API and MCP Server

Enable Tool API integration:

```sh
composer require 'drupal/tool:^1.0@beta'
drush en ai_site_advisor_tool -y
```

| Tool API plugin | Input | Output |
| --- | --- | --- |
| `ai_site_advisor:discover_candidates` | `query`: 1–120 characters | `discovery`: candidates and source reports; no inference call. |
| `ai_site_advisor:assess_content_brief` | `brief`: 10–4,000 characters; optional advanced `catalog_query` override: up to 120 | `assessment`: draft capability plan, search decisions, fresh evidence and typed judgments. |

The operations are `Read` and `Explain`. Both check `access ai site advisor`
inside the service, including for callers that skip Tool API's access method.

For an MCP client, install
[MCP Server Tool Bridge](https://www.drupal.org/project/mcp_server_tool_bridge)
at a version compatible with your MCP Server installation:

```sh
composer require 'drupal/mcp_server_tool_bridge:^1.0@beta'
drush en ai_site_advisor_mcp -y
drush cr
```

This optional submodule installs two enabled Tool API mappings. The demo uses
MCP Server `2.0.0-beta2` and bridge `1.0.0-beta1`; newer bridge releases require
newer server APIs. Review the host's existing MCP tool mappings when enabling
the bridge: other installed modules can supply optional mappings of their own.
This module owns only its two adviser mappings.

The default MCP HTTP endpoint is `/mcp`. Use the host's configured authenticated
MCP connection and an account with both `access mcp server` and `access ai site
advisor`. This module does not provision credentials or anonymous access. The
verified wire names are:

- `tool_api__ai_site_advisor_discover`
- `tool_api__ai_site_advisor_assess`

An agent can send the original brief directly to assessment:

```json
{
  "brief": "We need an editorial workflow for our existing news content. Writers save drafts, editors review them, then publish approved articles. Compare the existing configuration with available solutions before proposing custom development."
}
```

The service handles search planning for both the form and Tool API/MCP. A caller
that already knows the desired search can use discovery with
`{"query":"workflow"}` without an inference call. Supplying the optional
`catalog_query` to assessment explicitly requests that search and bypasses the
planning stage; normally omit it.

Assessment reads the site and discovers candidates afresh. It never accepts an
agent-supplied evidence packet as proof. Candidate IDs are stable; the set may
change when a source updates. Resolve review flags and inspect candidate details
before invoking separate installation or build tools.

```mermaid
flowchart LR
  Agent --> MCP[MCP Server]
  MCP --> Bridge[Tool API bridge]
  Bridge --> Tools[Discovery and assessment tools]
  Tools --> Advisor[Shared adviser services]
  Form[Drupal form] --> Advisor
  Advisor --> Site[Current fields and workflows]
  Advisor --> Local[Local recipe manifests]
  Advisor --> Plan[Decide local / search / clarify]
  Plan -->|search only| PB[Enabled Project Browser sources]
  PB --> Catalogs[Module and recipe catalogues]
  Advisor --> Decision[AI Decision / Jev]
```

ECA, WebMCP and other callers can use the same service or Tool API plugins through
their adapters. WebMCP can invoke them directly; it does not need MCP Server.
Browser path scope, human handover, transport authentication and write-tool
permissions remain responsibilities of those interfaces.

## A short demo

Select **A community site** and click **Propose a plan**. The example asks for
events and topics in groups, an activity stream and notifications. Show the
separate queries, the existing Workshop type as a possible event starting point,
and `drupal/group` as a discovered candidate. Activity-stream and notification
options remain reviewable; a package's description does not prove the combination
works. Topics may mean discussions or taxonomy, so the plan asks to resolve that
distinction. No project name is embedded in the example or retrieval logic.

The plan has three parts: work areas with evidence and open decisions, validation
of the chosen combination, and preparation of build tasks. Work areas follow the
brief; they are not a verified dependency graph. Candidate descriptions come
from sources. Actions and checks are predefined text composed from typed choices,
not an LLM-generated implementation narrative. Uncertain winners are shown as
open decisions rather than endorsed selections; up to three plausible options
are visible for comparison. The same structured `plan` is returned to MCP.

For the earlier workflow example:

1. Open the adviser and expand **Available capabilities and recipe catalog**.
   Show the site's actual fields, moderation states and discovered local files.
2. Select **Editorial workflow** and click **Propose a plan**. Show Jev's
   search decision and selected term above the results. In the live check it
   selected `workflow` from the brief and queried the configured sources.
3. Compare the existing workflows with the discovered recipe/module cards.
   Expand a workflow to inspect its transitions and content-type assignments.
4. Expand a candidate's **Evidence and adoption checks**. A remote recipe is
   shown as available in the ecosystem; its presence is not called “applied”.
5. Inspect **Which sources were searched?** and the exact questions and evidence.
   Explain that Jev judges fit from supplied facts; it does not discover Drupal
   projects from memory or decide installation permissions.
6. Try **Recurring workshops**, then **An unclear brief** to demonstrate when
   an ecosystem search may add nothing or the requirement needs clarification.
7. In an MCP agent, ask: “Use the adviser to assess our news workflow requirement.
   Compare what we have with available solutions before proposing custom work.
   Do not install or change anything yet.” Only the original brief is required.

The site must have a moderation workflow for the reuse part of this example.
Without one, discovery still works and the result reports no existing workflow.
The Workshop and campaign examples remain available for content-model planning.
Live judgments vary; the UI shows predefined criteria and review flags rather
than inventing a generated explanation.

## Service contract and evidence

Inject `Drupal\ai_site_advisor\Assessment\SiteAdvisorInterface`, or service
`ai_site_advisor.advisor`:

```php
$assessment = $advisor->assess($brief, $account);
```

For discovery alone, inject `ai_site_advisor.candidates`:

```php
$discovery = $catalog->discover('workflow', $account, limit: 12);
```

| Assessment key | Meaning |
| --- | --- |
| `status`, `summary`, `follow_up` | Advisory outcome and unresolved questions; never authorisation to build. |
| `answers` | Choices, full distributions, confidence, individual review flags and static criteria. |
| `reuse_candidates`, `extension_candidates` | Confidently matched node content-type IDs. |
| `workflow_candidates` | Existing workflow IDs with a `ready` or `extend` judgment. |
| `adoption_candidates` | Confidently relevant catalogue candidate IDs, not verified install targets. |
| `site`, `candidates`, `discovery` | Exact evidence, site fingerprint, source reports and search boundaries. |
| `plan` | Draft work areas, evidence-backed selections or open decisions, candidate options, checks and handoff boundaries. |
| `search_plan` | Route, capabilities, queries, unmapped clauses, truncation and planning questions/answers (`ecosystem-search-v2`). `query` remains the first term for compatibility. |
| `questions`, `profile` | Reviewed questions and versioned rubric (`content-planning-v3`). |
| `model`, `usage`, `usage_by_stage`, `elapsed_ms` | Assessment model, summed provider usage, per-stage usage and elapsed server time including planning/discovery. |
| `build_guidance`, `limitations`, `contradictory_judgments` | Boundaries callers must retain. |

`recipes` remains an alias of `candidates`, and candidate question IDs retain
the `recipe__` prefix for compatibility with the initial prototype. These now
include module candidates; inspect each candidate's `kind`.

Collected evidence is allowlisted: node-type labels/descriptions, title and
configurable field definitions, reference targets, selected enabled features,
Views/template identities, active moderation states/transitions/bundles and site
policy, plus enabled module names/descriptions. Node content, user records,
provider settings and arbitrary config are
not collected. Role permissions, notifications, ECA models and runtime behavior
are not inferred from workflow labels.

The brief and selected structural metadata go to the configured Decision
provider for search planning; the assessment also includes bounded candidates.
Catalogue sources receive the selected search term (or explicit override),
not site evidence or the full brief. Drupal's normal form/session
handling and host AI logging/cache settings still apply. This module sanitizes
provider exceptions before they reach Tool API or MCP transport logs.

Independent questions are batched within each stage. With an ecosystem adapter,
planning precedes discovery and assessment, so there are two Decision requests.
`usage` sums both; `usage_by_stage` preserves the breakdown. Unknown counts stay
`null` rather than being treated as zero. An explicit search override skips
planning, as does a site without a remote adapter.

Evidence is collected afresh; the adviser does not cache assessments. The
provider may cache its own responses, including their usage metadata. Reported tokens are **not necessarily
newly billed tokens for this call**, and elapsed time is not a full agent-task
measurement. Unknown usage remains `null`; cached-input/billing breakdown is not
available through this response contract.

Assessment supports at most 24 selected node types and a 100 KB compact JSON
request per stage. Display formatting is not included in that size check. Missing
answers, invalid options/distributions, denied access and failed inference stop
assessment. Review flags use probability below 0.75, confidence below 0.7,
unknown/unclear answers or contradictory primary judgments. These are prototype
display thresholds, not calibrated correctness guarantees. Callers must retain
individual review flags even when the overall status is `assessed`.

## Extending and validating

The collector, search planner, profile, provider adapter, adviser and UI are
separate classes. Replace the planner through `SearchPlannerInterface`, decorate
the collector or replace the adviser through their interfaces. Add a
catalogue adapter by implementing `CatalogSourceInterface` and tagging its
service `ai_site_advisor.catalog_source`. No procedural `.module` file is needed.
Keep rubric changes versioned. New entity types, package compatibility checks,
approved installation workflows and model evals are separate follow-up work.

Run from a Drupal project with development tools and the optional integration
dependencies installed:

```sh
SIMPLETEST_DB=sqlite://localhost/:memory: vendor/bin/phpunit \
  -c web/modules/contrib/ai_site_advisor/phpunit.xml.dist
vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php \
  web/modules/contrib/ai_site_advisor
node --check web/modules/contrib/ai_site_advisor/js/advisor.js
```

Adjust `contrib` to `custom` as needed. Tests use an isolated SQLite database,
real local manifests and a fixture Project Browser source. They make no external
catalogue or inference requests and require no API key. See
[VALIDATION.md](VALIDATION.md) for verified behavior and live MCP/browser checks.

## Attribution and license

Original integration code, GPL-2.0-or-later. It consumes Drupal core, Drupal AI,
AI Decision, the TypeSafe provider and optional
[Tool API](https://www.drupal.org/project/tool),
[Project Browser](https://www.drupal.org/project/project_browser),
[API Browser](https://www.drupal.org/project/api_browser),
[MCP Server](https://www.drupal.org/project/mcp_server) and
[MCP Server Tool Bridge](https://www.drupal.org/project/mcp_server_tool_bridge)
through their APIs and configuration.

No upstream module was forked or vendored for this work. Recipe manifests are
read from the host installation; API Browser supplies the Packagist source
configuration. Symfony String supplies the English inflector. The optional
Workshop configuration belongs to this module.
Please retain these upstream attributions when contributing or adapting it.
