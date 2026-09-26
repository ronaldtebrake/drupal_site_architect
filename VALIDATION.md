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

PHPUnit: **47 tests, 795 assertions**, passing against isolated SQLite databases.
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
- Search planning receives actual site evidence and selects source phrases for
  several capabilities. The supplied community brief includes event, topic,
  group, activity stream and notification options, with English singularization.
  URL/email/numeric tokens are excluded. Invented options are rejected.
- Local and uncertain routes never query remote sources. Identified
  capabilities remain in a local plan without querying the ecosystem. No suitable
  term leads to clarification.
- Multi-query discovery retains distinct capability coverage, merges duplicate
  package provenance, validates the entire batch before any source call, and
  checks permissions before searching.
- Draft plan composition preserves source descriptions and uncertainty. An
  uncertain package is an option to compare, never an endorsed selection.
- No remote adapter and an explicit keyword override both skip planning.
  Access is checked before planning. Total usage includes both Decision stages.
- Drupal/DrupalPractice coding standards, Composer metadata validation,
  JavaScript syntax and Git whitespace checks pass.

Commands and optional test dependencies are described in
[README.md](README.md#extending-and-validating).

## Live ecosystem discovery

The following initial observations used the earlier single-term assessment.
The community-plan check below exercises the current multi-query path.

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
6. After automatic planning was added, a second protocol check supplied **only
   `brief`**, the sole required input. Jev selected `search` and `workflow`, and
   assessment returned 12 candidates with per-stage usage. No keyword argument
   was passed by the client.
7. Session termination returned **200** for the automatic-planning check. Test
   credentials, cookies and session IDs were not stored in this repository or
   printed in the results.

For the editorial-workflow brief, the model classified content modeling and
presentation as `not_applicable`, found two existing workflows to consider
extending, and marked six catalogue candidates confidently relevant. The
summary advised inspecting existing workflow configuration first. The existing
Article type remained uncertain. These are observed model judgments, not
confirmed fitness or installation recommendations.

The initial explicit-query assessment call reported 11,873 input and 1,110
output tokens, with 7.497 s server time including discovery. The new brief-only
MCP call reported these stages:

| Stage | Input | Output | Total |
| --- | ---: | ---: | ---: |
| Search planning | 3,081 | 395 | 3,476 |
| Assessment | 11,873 | 1,110 | 12,983 |
| Combined | 14,954 | 1,505 | 16,459 |

The browser's full automatic workflow call reported 5.17 s server time.
Repeated UI requests were faster and reported the same assessment usage figures.
The provider stack may cache responses and their usage;
these figures must not be presented as new billed tokens on each request or
as evidence of savings. Cached-input and billing breakdown are unavailable
through this response contract.

Enabling the upstream bridge imported optional mappings from other installed
modules. Only newly imported, unrelated mappings were disabled during setup;
pre-existing mappings were preserved. The adviser owns two enabled mappings.

## Browser verification

- The form now has a single brief field. The workflow example supplies no
  separate keyword; the result shows Jev's `search` decision and `workflow` term.
- The form renders live Jev advice, two workflow starting points, local and
  remote candidate availability, review flags and source match counts.
- Transitions/bundle assignments, source evidence and upstream project links
  are inspectable. Long catalogue text is collapsed into evidence disclosures.
- Changing the brief hides the previous assessment. A subsequent AJAX
  assessment works on the same page.
- On that subsequent submission, the Workshop brief chose `local`, reported
  that external sources were not queried, and identified the existing Workshop
  content type for reuse.
- One live response was rejected by the assessment contract checks. No partial
  advice was shown. Subsequent calls, including a repeated AJAX submission,
  succeeded. The validation rules were retained; the retry message was clarified.
- Desktop layout was visually inspected in the in-app browser.
- Initial phase-one checks also verified anonymous adviser denial, anonymous
  Tool API denial, the real Workshop node form, Workshop reuse/price-field
  extension, Canvas campaign presentation and clarification for vague briefs.

## Earlier single-term search-planning checks

Three briefs were checked against the actual site snapshot and configured Jev
provider. These are observed outcomes, not assertions about every future run:

| Brief | Decision | Selected term | Selected-route probability | Confidence |
| --- | --- | --- | ---: | ---: |
| Editorial workflow, explicitly compare ecosystem options | Search | `workflow` | 0.93 | 0.89 |
| Recurring workshops with the existing date/location/capacity/description fields | Local | None | 0.82 | 0.73 |
| Something better for the website, with no clear requirement | Clarify | None | 0.96 | 0.94 |

These checks preceded the capability-plan update. Current search terms are
source phrases of one or two words, with English singularization. Arbitrary
synonym expansion is still not provided. Route thresholds remain prototype
policy; evals and calibration are outside this phase.

## Community brief and draft plan

Reproduced the original failure with this exact brief:

> We want a Community site, with events and topics, placed in groups, with an activity stream and notifications.

The previous planner selected only `community`. Its 12 candidates contained
none of the Group/event/notification building blocks expected for the brief.
Direct Project Browser searches for `group`, `event` and `notification` did
return relevant packages, locating the main failure in our query coverage and
shortlisting rather than absence of those projects from Project Browser.

The revised live run selected `community site`, `event`, `topic`, `group`,
`activity stream` and `notification`. It assessed 24 distinct candidates,
including `drupal/group`, `drupal/drupal_cms_events`, `drupal/message`,
`drupal/activitystream_entity` and `drupal/notification_message`. Its draft
selected Group for investigation and the existing Workshop type for inspection
as an event starting point. Topics, activity stream and notification choices
remained open, with source descriptions and design checks. These are observed
model choices, not verified architectural recommendations or compatibility.

The first complete revised service call reported 34,325 input and 3,778 output
tokens across both stages. The browser run reported 5.88 seconds. Cache warmth,
provider response caching and catalog latency make these smoke observations
unsuitable as a cost/speed benchmark. This broader plan asks more questions and
uses more evidence; no reduction in billed tokens is claimed.

The browser rendered the proposed plan, per-capability options, existing content
type link, upstream Group link and explicit validation/build-handoff stages.
An authenticated HTTP MCP `tools/call` with only this brief returned `plan.status`
of `draft`, the six capability queries, 24 candidates and the same structured
work areas. Group was selected for investigation; the activity stream and
notifications retained competing options. The topics judgment varied between
an open decision and investigating the core Tags recipe, while its design check
still called for clarifying discussions versus taxonomy. The temporary MCP
session was terminated successfully (HTTP 200).
No discovered package was installed and no content/configuration was changed by
the assessment. Evals remain outside this phase.

## Larger planning briefs and request batching

Removed the prototype cutoffs that kept only 12 clauses, the first 32 words of
a clause and six capabilities. Form, service and Tool API now accept up to
20,000 characters. Extraction processes every accepted segment in batches;
named paragraphs retain their constraints together. Long paragraphs use
overlapping word windows. Above 200 segments, the synchronous planner rejects
the request before inference rather than returning a truncated plan.

Each search keeps its own allowance of up to 12 candidates. Assessment batches
retain every question and its required evidence; global judgments are made
once. Usage is summed across requests, with per-request evidence IDs and sizes
available in the result. Unknown usage remains unknown.

Automated validation passed: **41 tests / 544 assertions**, Drupal and
DrupalPractice PHPCS, Composer metadata validation and `git diff --check`.
The integrated long-plan fixture verifies 14 capabilities and 168 candidates,
including the final payments requirement, across multiple extraction and
assessment calls. Tests also cover late malformed responses, missing usage,
word-window boundaries, named sections, retained source passages and access
checks. These are contract tests, not model-quality evals.

A live browser submission used the committed
[5,135-character community brief](docs/community-planning-brief.txt). It
processed **14/14 text segments**, selected **10 work areas** and assessed
**118 distinct candidates**. Queries included `group`, `event`, `topic`,
`activity stream`, `notification`, `search`, `media`, `moderation` and
`translation`, plus the overall `community website` context. Group and the
Drupal CMS Events recipe were among the retrieved candidates. The rendered
plan retained the later media, moderation and translation areas. This verifies
processing coverage, not complete understanding or candidate compatibility.

That run used two extraction requests and nine assessment requests. Its largest
compact request was **99,782 bytes**. Provider-reported usage was 243,584 input
and 23,515 output tokens, and server elapsed time was 14.55 seconds. Larger plans
clearly do more work; these observations are not a billing estimate or a speed
benchmark. Provider caching and catalog cache warmth affect repeated runs.

Live testing also exposed a floating-point boundary error in the existing 1%
probability-sum tolerance: a total of 0.99 could be rejected. The validator now
rounds the sum error before comparison. Tests accept 0.99 and 1.01 while still
rejecting 0.98. Mechanical singularization now preserves collective nouns such
as media and data instead of changing the search to medium or datum.

An authenticated HTTP MCP `tools/list` advertised `brief.minLength = 10` and
`brief.maxLength = 20000` for `tool_api__ai_site_advisor_assess`. Its temporary
session was closed successfully (HTTP 200). No extra inference was needed for
this schema check. The final long brief was exercised through the Drupal form;
the earlier MCP execution check above remains the transport smoke test.

No package was installed and no content/configuration was changed by these
assessments. Temporary diagnostic scripts were removed before committing.

## Authenticated MCP assessment after the batching update

Confirmed MCP Server, Tool API Bridge and AI Site Advisor MCP were already
enabled; no package reinstall or version change was needed. A fresh HTTPS test
used certificate verification and an in-memory Drupal login session. Anonymous
initialization returned HTTP 401. Authenticated initialization negotiated
protocol `2025-11-25`, and `tools/list` returned both adviser wire tools.

An actual `tools/call` to `tool_api__ai_site_advisor_assess` with the original
community brief succeeded. It returned six work areas, 71 distinct candidates
including `drupal/group`, and a draft plan with unresolved decisions retained
(`status = needs_clarification`). Server time was 9.241 seconds. Reported usage
was 128,727 input and 7,848 output tokens; this is an observation, not a billing
or speed benchmark. Session cleanup returned HTTP 200. No credentials, cookies
or session identifiers were printed or persisted in the repository. The
temporary test script was removed.

This verifies the authenticated HTTP tool execution path. A persistent desktop
client connection is separate and still needs its chosen authentication setup.

## Visible candidate contributions and Events regression

The reported empty Events comparison was a presentation/selection bug. The
existing result gave Recurring Events 0.94 whole-brief relevance, but only 0.06
in the competing starting-point choice. Workshop received 0.73 and configuration
0.19. The old comparison displayed only catalogue candidates with at least 0.10
starting-point probability, up to three results. It omitted both useful packages
and the preferred local option, then incorrectly suggested no suitable package
had been established.

`content-planning-v5` retains every assessed option, including local content
types, configuration, low-ranked candidates and unrelated results. Independent
per-work-area judgments classify each option as a possible foundation, possible
addition, unrelated or unknown. These are separate from the competing selection
and whole-brief relevance. The full distributions, confidence, source evidence
and review flags are inspectable. The summary distinguishes foundations and
additions, marks uncertain roles, and requires verification before combining
packages. No threshold was lowered and no project-specific rule was added.
The generic content-design check no longer asks about topics in unrelated areas.

Regression tests cover the exact low-selection/high-relevance failure, retained
local and zero-probability options, contribution questions scoped to their work
area, foundations versus additions, and contradictory role/selection judgments.
The integrated long-plan test still retains all questions and evidence across
bounded requests. PHPUnit passed **44 tests / 769 assertions**; Drupal and
DrupalPractice PHPCS and Git whitespace checks passed.

The browser submitted the complete committed community brief again and retained
14 text segments, 10 work areas and 118 distinct candidates. Events displayed
17 assessed options: 12 catalogue packages, three content types, configuration
and the unresolved choice. In the final observed run, Recurring Events had 0.91
foundation probability (0.88 confidence), 0.06 starting-point probability and
0.95 whole-brief relevance. The calendar recipe appeared as a possible addition;
programming event dispatchers remained inspectable as unrelated matches. The
score table and expandable role distribution were visually checked. One browser
response was rejected as incomplete/inconsistent; the subsequent submission
succeeded. Validation was not relaxed, and no partial advice was displayed.

A fresh authenticated HTTPS MCP `tools/call` sent the same full brief to
`tool_api__ai_site_advisor_assess`. It returned profile `content-planning-v5`,
all 17 Events options and their separate judgments. Recurring Events had 0.91
foundation probability (0.87 confidence), 0.06 starting-point probability and
0.90 whole-brief relevance. Workshop remained a preferred option needing review
at 0.70 selection probability and 0.68 confidence. Session cleanup returned
HTTP 200. This confirms the full evidence survives the Tool API/MCP transport;
these observed model scores are not calibrated quality or coverage measures.

The final browser run reported 8.69 seconds and the MCP run 11.756 seconds.
Independent role questions add provider work; these cached, single-run
observations are not cost or speed benchmarks. No package was installed and
no event model or package combination was configured or verified. Temporary
diagnostic scripts were removed, and no keys, cookies or session IDs were stored.

## Configuration-aware site-builder handoff

Profile `content-planning-v6` separates a generic, unspecified configuration
approach from concrete inspected options. The fallback always needs review and
is no longer independently scored as a foundation. A local recipe can supply
configuration; it is not an alternative to the idea of configuring Drupal.

The default UI now leads with readable next steps, actual administration links,
existing bundle fields and recipe configuration to review. The complete scored
comparison remains available in a disclosure. Jev selects a configuration area
from registered Drupal definitions; routes and link access come from Drupal,
not model-generated paths or a scenario-specific lookup. Site evidence schema
version 3 includes these areas and configurable fields on registered bundle
types, while preserving the selected node-type scope. Arbitrary configuration
values, content records, defaults and credentials remain excluded.

Local recipe evidence now includes safe structural metadata from supplied YAML,
explicitly named imports and action targets, plus whether each named item
exists in active configuration. Matching active bundle definitions have direct
edit, fields and display links where permitted. Existence is not equality or
proof of recipe application. Included recipes, wildcard imports, action effects,
dependency compatibility and exact per-requirement field changes still require
inspection; no complete recipe simulation is claimed.

Automated checks passed **47 tests / 795 assertions**, Drupal/DrupalPractice
PHPCS and `git diff --check`. New tests exercise a newly created taxonomy bundle
and field through generic metadata, real permission-checked routes, denial of
links to an unprivileged account, current versus absent recipe configuration,
exclusion of synthetic credential/default values, and guidance when evidence is
missing. The large-plan fixture retains all 14 configuration-area judgments
across request batches and exposes the final work area's handoff. Standalone
installation still works without optional modules.

In the full community-brief browser check, Media selected the Image media type
recipe and the Media types configuration area. The handoff detected the existing
Image definition and **21 explicitly listed configuration names already present,
zero missing**. It now leads with the existing Image configuration and describes
the recipe as a reference. The builder can open Image's edit, fields, form
display and display pages. The Manage fields link was followed and verified
against Drupal's real page, which lists the required `field_media_image` field.
Events separately retained Workshop as a content-model starting point and
Recurring Events among the projects to investigate. The UI and recipe-change
disclosure were visually inspected. No configuration was changed by these checks.

An authenticated HTTPS MCP assessment of the same full brief returned all ten
work areas, profile v6 and the same structured Media handoff, existing Image
record, configuration counts and `/admin/structure/media` link. The final call
reported 14.825 seconds; this is a smoke observation, not a speed or cost
benchmark. Session termination returned HTTP 200. Temporary test scripts were
removed; credentials, cookies and session identifiers were not persisted.

## Compact agent handoff

The Tool API assessment and discovery plugins now default to `detail: compact`.
Full output is opt-in with `detail: full`; the underlying PHP services and form
still receive complete evidence. The compact contract keeps every work area,
configuration pointers, a deduplicated candidate registry, conditional Composer
acquisition instructions, independent contribution roles, open checks and review
flags. Preferred packages and up to two candidates per useful role are retained;
the count of omitted package options and search boundaries remain visible.

Automated checks passed **50 tests / 855 assertions**, Drupal/DrupalPractice
PHPCS and `git diff --check`. Tests cover useful contributions with low
starting-point probability, uncertain preferred choices, unresolved areas,
existing configuration routes, candidate deduplication, unavailable sources,
local versus external acquisition and malformed package identities. Kernel tests
execute both actual Tool API plugins: omitted detail returns compact output,
full preserves the original service result, and invalid detail fails before
either service is called.

Authenticated HTTPS MCP calls sent the complete community brief, first with
detail omitted and then with `detail: full`. Both returned HTTP 200 and successful
results. `tools/list` advertises an optional compact/full enum for both plugins;
the default is described in its schema text and verified through execution.
The final compact response retained **10 work areas and 26 candidate pointers**,
covering all **14 input segments**. Full output contained 118 candidates. All
compact candidate references resolved; local code had no Composer acquisition
step. Existing Image configuration retained edit/field/display routes and 21
explicit recipe configuration names present, zero missing.

The compact plan alone was **21,946 bytes** as compact UTF-8 JSON. The complete
MCP response body was **72,968 bytes**, versus **4,662,203 bytes** for full mode,
approximately **98.43% smaller**. The bridge includes the tool result in text
content and structuredContent; those representations were checked equal. Each
mode performs a fresh assessment and choices can differ. This validates payload
size and transport behavior, not inference savings, billing, latency improvements
or recommendation quality. Internal inference work is unchanged.

Session termination returned HTTP 200. Exact requests, bodies and decoded
responses were saved as local review artifacts outside this module and the
public document root. Credentials, cookies and session IDs were not exported.
No packages were installed and no content or configuration was changed.

## Recommended choices and three-option UI

The human-facing plan now leads with Jev's selected starting point, selection
probability and confidence. The presentation distinguishes recommended,
provisional, conflicting and unresolved choices. It does not infer a new winner
from whole-brief relevance or promote a generic configuration choice to a package
recommendation. Remaining resources are ordered by independent contribution
review status, probability and confidence. The main view keeps at most three
distinct options, including a selected existing content type. All assessed
options remain in the expandable comparison.

Automated checks passed **52 tests / 876 assertions**, Drupal/DrupalPractice
PHPCS and Git whitespace checks. New tests cover selected options with lower
contribution scores than alternatives, useful additions with low starting-point
probability, the three-option limit for packages and existing content types,
provisional/conflicting/missing choices and preservation of full service evidence
through the theme preprocessor. No inference rubric, service or MCP schema changed.

The browser submitted the full community brief. All ten work areas displayed
at most three distinct highlighted options, while every expanded comparison
retained 17 assessed options. Groups clearly recommended Group; Events recommended
the existing Workshop type and kept Recurring Events as an alternative foundation
and a calendar recipe as a supporting option. Media recommended the Image recipe
as a reference for existing configuration. Uncertain choices were labelled
provisional, and generic configuration selections remained open. The recommendation
banner and option hierarchy were visually checked in the browser. Live scores
can vary; this verifies presentation and preserved evidence, not model quality.
One subsequent provider response was rejected as incomplete/inconsistent; a
retry succeeded without relaxing validation. The final rendered plan again had
ten areas with at most three highlights and 17 comparison rows each. Opening
Groups' comparison exposed Organic Groups, which was not in its main shortlist.

## Core and local capabilities participate in scoring

Site evidence schema 4 includes visible core modules shipped with Drupal,
including disabled modules, plus enabled contributed/custom modules. Module
metadata supplies descriptions, dependencies and availability. Configuration
links come from accessible declared configure routes and registered config
entities. No keyword-to-project mapping or fixed core shortlist was added.
Hidden and test modules are excluded.

Profile `content-planning-v7` screens each local module against the full brief,
then retains all except confidently unrelated modules as named candidates for
per-work-area selection and contribution scoring. The excluded screening
judgments remain inspectable. The starting-point question distinguishes creating
records from adding behavior to existing records. Search planning v4 also sees
disabled core capabilities. Local module IDs stay distinct even when they share
`drupal/core`. Enabled and available-but-disabled states are not conflated with
configuration completeness, and neither requires Composer acquisition.

Automated checks passed **56 tests / 2,024 assertions**, Drupal/DrupalPractice
PHPCS and Git whitespace checks. Kernel tests inspect actual disabled translation
and Views modules, enable Views UI and Content Translation on an isolated site,
check real routes and permission denial, and verify changing fingerprints.
Unit tests cover semantic retention without keyword overlap, uncertain/excluded
screening results, contribution questions for all retained modules, compact
handoff identities and no Composer acquisition for core. Orchestration includes
local screening in summed usage. An 80-module fixture retains every choice and
160 per-area contribution questions while each scoring packet stays below 100 KB.

The initial full-brief run exposed repeated inventory and output-pointer data
exceeding a request budget. Scoring packets now exclude redundant inventories,
routes and source paths while retaining descriptions, identities, availability
and dependencies. Full output preserves the complete evidence. The request limit
and response validation were not relaxed. A later inconsistent provider response
was rejected; the retry completed.

The live site inventory contained **114 local capabilities**. The full community
brief, including an Overviews section, produced **77 scored options per area**
while retaining at most three UI highlights. Translation recommended Content
Translation (97% selection probability, 96% confidence in the observed run).
Views was selected provisionally for overviews and the activity stream, with its
actual `/admin/structure/views` destination. These are observations of a single
run, not calibrated quality claims. Content Translation was correctly shown as
shipped core code that is not enabled; no site modules were enabled by the adviser.

An authenticated HTTPS MCP call with a focused translation/overview brief returned
HTTP 200, compact format and both `module__content_translation` and `module__views`
as starting points, preserving review flags. Its 17,600-byte response included
machine names, dependencies and `code_available` acquisition instructions; Views
had its live administration link. The call took 4.176 seconds, a smoke observation
only. Exact request/response artifacts were saved outside the public document
root and module repository. Session cleanup returned HTTP 200; no credentials,
cookies or session IDs were exported. Local screening adds inference work; its
usage and request records are reported separately.

## Targeted recovery and simpler evidence presentation

Reproduced the reported inconsistent-assessment error with the live Jev provider
and the full community brief plus overviews. One contribution answer selected
`complement` at 0.48 although `unrelated` had 0.49. All four options were present
and the distribution summed to 1.0. The highest-probability check correctly
rejected it; it was not a brief-length limit or a lack of matching modules.

`DecisionBatch` now retains valid answers and retries only failed questions once
with identical state and criteria. Missing/wrong-type normalized answers, option
mismatches, invalid distribution sums and inconsistent winners are recoverable.
Validation remains unchanged; persistent invalid answers still reject all advice.
Provider execution/normalization exceptions stop immediately. Each attempt is
recorded, including rejection codes and usage; unknown usage remains unknown.
UI logging on exhausted recovery includes only reason counts, not responses.

Automated checks passed **64 tests / 2,097 assertions**, including the observed
48%/49% mismatch, missing and invented options, invalid sums, missing answers,
preservation of valid uncertain answers, identical retry evidence, accounting for
both attempts, persistent rejection before later batches and no retry on provider
execution errors. The form kernel test covers restoration without the removed
evidence section and sanitization of failure messages. Drupal/DrupalPractice
PHPCS passed.

A full live assessment completed with 11 work areas. A separate browser check
submitted the user's exact 5,252-character community brief and rendered all 11
areas with no assessment error, no evidence panel, and at most three highlighted
resources in each area. Content Translation and Views remained selectable local
capabilities. These successful runs do not establish a provider failure rate;
targeted recovery is exercised deterministically by the regression tests.

The redundant "What the advisor can see" template, theme registration and form
dependencies were removed. The form no longer recollects site/catalog evidence
on display or AJAX rebuild; the assessment still collects its full site evidence.
The original browser tab and its brief were preserved while verification ran in
a separate tab.

An authenticated HTTPS MCP smoke call using the focused translation/overview
brief returned HTTP 200, success, compact format and two work areas. The JSON-RPC
response body was 16,196 bytes and the call took 4.255 seconds; these are smoke
observations, not an agent benchmark or billing comparison. Session termination
succeeded. Request/response bodies and the rejected normalized Choice example
are in local artifacts outside the document root and module Git repository;
credentials, cookies, login responses and session IDs were not exported.

## Requirements assembled from multiple building blocks

The overall starting point is now pinned only when both its selection and
contribution pass the review policy. Uncertain preferences remain in the evidence;
the UI and compact MCP handoff leave the overall choice open. Independent
contribution ordering is shared by the full comparison, highlights and agent
shortlist. Checked matches for individual source requirements get priority over
generic useful components. UI highlights still contain at most three resources
(counting a recommended existing content type).

`RequirementPlanner` maps source sentences/list items to inspected options, then
independently checks each selected match. Several parts can use the same component,
and a work area can combine a record model with supporting capabilities. Original
source items and judgment distributions remain available. Partial support always
needs review; supported source evidence never marks a combination integrated.
Ambiguous context/planning instructions remain visible checks and cannot promote
settings tools as product features. Open record requirements explicitly prompt
inspection of existing fields or design of a suitable new type/entity.

**68 tests / 2,284 assertions** passed, together with Drupal/DrupalPractice PHPCS
and Git whitespace checks. Tests cover a record plus reply module, partial delivery
coverage, access conditions, uncovered configuration, rejection by independent
verification, ambiguous direct/partial support, ambiguous planning instructions,
exact source retention, actual field types in evidence, usage across both passes,
requirement matches surviving the UI/MCP shortlist, and weak preferences losing
their automatic first position. The long-plan integration test exercises the real
requirement planner with a mocked Decision boundary, preserving request budgets
and accounting across all stages.

A live focused brief covered discussion records, replies, nested replies, club
relationships/access, subscriptions, notification delivery and opt-out. The
assessment separated the record choice from Comment as a reply building block;
Notification System and Workflow Notifications appeared as notification options.
Remaining field, relationship, subscription and access questions stayed partial,
open or subject to verification. A before/after site fingerprint check matched.
No modules or configuration were installed by the adviser.

The browser exercised the user's full 5,252-character brief: 11 work areas,
54 visible source parts in the observed run, no assessment error, and no more
than three resource cards per area. Topics highlighted components for its own
parts, including Comment; Notifications highlighted notification packages.
Model results can vary, so these are smoke observations, not quality evals.

The final authenticated HTTPS MCP check returned HTTP 200 and
`schema_version: agent-plan-v2`, two work areas and ten source parts. It preserved
the record-type administration links, Comment for replies, notification candidates,
review flags and `integration_verified: false`. The 27,628-byte response took
7.269 seconds; session cleanup succeeded. This is not a billing or autonomous
agent benchmark. Exact bodies and a receipt are saved outside the public document
root and module repository, without credentials, cookies or session IDs.

Limits: decomposition uses source sentence/list boundaries, so a single compound
sentence may still need further breakdown. Each work area uses its bounded
inspected candidates. Cross-area dependencies and compatibility between the
proposed components require additional inspection. Two additional assessment
passes increase provider work; their usage is included under
`usage_by_stage.requirement_planning`. No package combination is declared verified.

## Remaining boundaries

The MCP protocol and Drupal UI have been exercised; an autonomous LLM agent's
complete planning/building session has not been benchmarked. No WebMCP or ECA
adapter was configured specifically for this adviser. Those interfaces can
reuse its services or Tool API plugins through their own adapters.

Package compatibility, recipe conflict simulation, role permissions, ECA runtime
behavior, approved installation/build operations and model evals remain separate
work. The current tools provide grounded discovery and typed advisory judgments.
