# Demonstrating Site Architect

[Back to README](../README.md)

## Optional Workshop example

Apply the included recipe on a development site to create a normal Workshop
content type with date, location, capacity and description fields:

```sh
vendor/bin/drush recipe modules/contrib/site_architect/recipes/workshop
```

It creates no content records. Inspect `/node/add/advisor_workshop`, then try the
Recurring workshops brief. The configuration survives uninstalling Site Architect.

## A community site

Select **A community site** and click **Propose a plan**. The example asks for
events and topics in groups, an activity stream and notifications. Show the
separate queries, the existing Workshop type as a possible event starting point,
and `drupal/group` as a discovered candidate. Activity-stream and notification
options remain reviewable; a package's description does not prove the combination
works. The brief should distinguish discussions from taxonomy when asking for
topics. No project name is embedded in the example or retrieval logic.

The plan has three parts: work areas with evidence and open decisions, validation
of the chosen combination, and preparation of build tasks. Work areas follow the
brief; they are not a verified dependency graph. Candidate descriptions come
from sources. Actions and checks are predefined text composed from typed choices,
not an LLM-generated implementation narrative. A work area leads with a
**Recommended starting point** only when its selection and contribution both pass
the review policy. Otherwise it shows **No recommendation yet**, while useful
components and requirement matches remain visible. The original preference and
all scores stay in the expanded evidence, including weak or conflicting choices.

The main view highlights at most **three distinct options**, including any
recommended existing content type. A confirmed choice leads. Checked matches for
specific requirement parts come next; other resources are ordered by contribution
review status, contribution probability and
confidence, with a stable ID tie-break. They are labelled as alternative
foundations or supporting options. Their separate starting-point probability
does not exclude an independently useful addition. Every assessed option remains
available in **Options, scores and remaining gaps**, including existing content
types, Drupal configuration, uncertain packages and unrelated matches.
This limit affects resource highlights, not the requirement breakdown. Every
source part remains available even when its component is outside those cards.

The plan now separates three different judgments:

- **Starting point**: one competing Choice across the inspected options. Its
  probability can be low for a useful module when an existing content type is
  preferred. Weak preferences remain in the evidence without leading the ranking.
- **Contribution here**: an independent Choice for each option in each work area:
  possible foundation, possible addition, unrelated or insufficient evidence.
  Several building blocks can be useful. The role's distribution, confidence,
  criterion and review flag are available beside its source evidence.
- **Whole-brief relevance**: the earlier independent package relevance question,
  explicitly labelled as applying to the complete brief. A package can be useful
  elsewhere without contributing to this particular work area.

Foundation and addition summaries suggest investigation paths. They do not assert
that the packages integrate. For example, a module that supplies its own event
entities may be an alternative foundation to a Workshop node type, while a
field-oriented recurrence module may extend an existing model. The architect uses
retrieved descriptions to judge this distinction; it does not contain package
rules or automatically install combinations. Roles needing review are identified
in the summary and table. Missing evidence and runtime compatibility remain open.
The same structured `plan`, full option list and judgments are available to MCP
with `detail: "full"`; the default agent handoff is compact.

### Several components for one work area

`RequirementPlanner` adds a separate `requirement-parts-v2` stage after candidate
assessment. It retains grouped source passages and their sentence/list items;
no feature-to-module map is embedded. Jev distinguishes record subjects, stored
fields, listings, presentation, other behavior and conditions. It
selects a possible component from that area's inspected options, including existing
node types, local modules and discovered packages. A second call independently
checks that selected component against the exact item using its description,
actual bundle fields and any inspected recipe configuration.

In the same two passes it can select an inspected target node type, then check
individual field definitions for the requested storage or filter/sort usage.
Only confident matches become field references. Ambiguous, conflicting or absent
targets remain unresolved, and an uncertain field match never becomes an asserted
mapping. A suggested component and target content type are separate decisions:
the former supplies behavior; the latter identifies the records it operates on.

The UI's **How the parts fit together** groups these connections into content and
fields, listing, presentation and remaining behavior/conditions. For example,
inspected workshop fields can appear under the Workshop type, while an independently
selected listing component points to that same type and its Location field.
There are no workshop names, field names or Views/Canvas mappings in this logic.
Module choices still come from source descriptions and typed judgments. Settings
links come from inspected configuration routes. Existing listing filters and
template mappings are not declared verified.

The compact Tool API/MCP plan includes each part's optional `target` (entity type,
bundle and configuration links) and `fields` (name, type and proposed purpose).
It retains the selected probabilities and confidence while omitting full option
distributions; the full response retains every judgment.
This is an additive extension of `agent-plan-v2`, shared with the normal form.

For example, storing an opening post and adding replies are separate needs.
They can point to an existing record model and a reply capability, while club
access remains a condition to verify. A named component can serve several parts.
Different record models remain alternatives; this is not an install-all list.

Each part reports direct source support, a partial building block, an open gap
or an acceptance check. The `supported` status requires a direct judgment passing the
existing 75% probability / 70% confidence policy. If the combined probability of
direct or partial support is at least 75%, a match can remain **partial** even
when the distinction between those two roles is uncertain. Partial matches always
need review. These are prototype thresholds, not calibrated correctness claims.
An uncertain choice between two viable candidates does not erase independently
supported usefulness, but its selection review flag is preserved.

The service reports this under `plan.areas.*.requirements` and keeps version,
answers, requests and usage under `requirement_plan`. Usage totals include both
new passes under `requirement_planning`, including any targeted recovery. Valid
earlier assessments are not rerun. All request-size and response checks still apply.

This is a source-based breakdown, not exhaustive semantic extraction: one sentence
may contain several needs, and candidate metadata may not establish their coverage.
Such items stay partial or open. Integration, entity compatibility, field wiring
and access behavior across components still require inspection and testing; this
stage never marks a combination verified. It uses the area's bounded candidate
set, so cross-area dependencies can still require broader discovery.

### A handoff for site builders

The default view presents readable next steps; probabilities remain in the
expandable evidence table. `plan.areas.*.handoff` contains the same guidance in
the service response and the full Tool API/MCP response:

- A concrete starting point and an explanation of what remains undecided.
- A suggested administration area, selected from Drupal's registered config
  entity definitions. Its collection/edit/Field UI links are generated from
  actual routes and checked against the caller's access.
- Existing bundle definitions and configurable fields, including non-node
  bundles discovered through entity metadata. The node scope setting is retained.
- Recipe configuration names already present versus proposed additions, with
  direct links to matching current bundle definitions. When all explicitly
  listed names exist, the recipe is presented as a reference to review.
- Project details for remote candidates and a clear distinction between
  enabled modules, locally available recipes and catalog-only resources.

The former “Configure Drupal” option is now explicitly an unspecified approach,
not a competing component or a scored foundation. A named recipe can supply
the same configuration. This fallback always needs review; it never establishes
an implementation on its own.

Jev selects configuration areas and judges candidates. Human-authored interface
copy composes those judgments with current evidence; there is no scenario-to-
module mapping or generated configuration URL. The prose does not invent field
names, integrations or installation instructions. Where the evidence cannot
establish the exact change, the handoff tells the builder what to inspect and
retains the requirement. Applying recipes, simulating their effects and detailed
field-by-field implementation design remain separate work.

For the earlier workflow example:

1. Open the architect and expand **Available capabilities and recipe catalog**.
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
7. In an MCP agent, ask: “Use the architect to assess our news workflow requirement.
   Compare what we have with available solutions before proposing custom work.
   Do not install or change anything yet.” Only the original brief is required.

The site must have a moderation workflow for the reuse part of this example.
Without one, discovery still works and the result reports no existing workflow.
The Workshop and campaign examples remain available for content-model planning.
Live judgments vary; the UI shows predefined criteria and review flags rather
than inventing a generated explanation.

For a longer exercise, paste [the detailed community brief](community-planning-brief.txt).
It covers groups, events, discussions, an activity stream, notifications, search,
media, moderation and translation. This is a sample requirement document, not
a fixed catalogue or a model-quality benchmark.
