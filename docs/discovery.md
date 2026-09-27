# Discovery and evidence

[Back to README](../README.md)

## Dynamic recipe discovery

There is no fixed list of recipe names. The local source discovers `recipe.yml`
files from:

- Drupal core's `core/recipes` directory.
- Composer packages of type `drupal-recipe`, using their recorded install paths.
- Conventional `recipes` directories at the Composer root and Drupal docroot.
- Additional directories configured in the architect settings, relative to the
  Composer root or absolute. Directory scans include manifests up to two
  subdirectory levels down and follow links. Add a nearer root for deeper trees.

The source reads names, descriptions, declared extensions, included recipe names,
configuration-action targets and a source hash. It also reads a sibling
`composer.json` for package identity when present. A newly added or edited
manifest appears on the next discovery call without editing this module.
Malformed manifests are reported while valid results remain available.

For local recipes it also inspects `config/*.yml` and explicitly named imports
and action targets. Only structural metadata is retained: configuration names,
labels, entity/bundle/field identifiers, field types, required flags and file
hashes. Arbitrary settings, defaults, action arguments and credentials are not
exported. Each explicit name is checked against active configuration. Existence
does not establish matching settings or application history. Included recipes,
wildcard imports and action behavior still require inspection; this is not a
complete recipe simulation.

Local availability means **code is present**. It does not prove that a recipe
was applied, that its configuration remains in use, or that applying it would
be compatible. The site collector separately reads current configuration.

## Core and existing module capabilities

The architect reads visible module metadata from Drupal's extension list. Every
module shipped with core is considered even when disabled; enabled contributed
and custom modules are also included. Hidden and test modules are excluded.
Names and descriptions come from the modules themselves. There is no list mapping
planning keywords to specific projects.

An independent semantic screening stage compares each module with the complete
brief. Only a confidently unrelated result (at least 75% probability and 70%
confidence) excludes it from detailed comparison. Useful and uncertain modules
remain named candidates, with starting-point and contribution judgments for each
work area. The full screening results, including exclusions, are inspectable in
**Local capability screening**. These prototype thresholds are not calibrated
coverage guarantees.

This lets core capabilities such as Content Translation and Views participate
alongside recipes and external projects. The starting-point question distinguishes
creating records from adding behavior to those records. For example, an event
content type is not automatically the best choice for translating existing events
or building an overview of them.

Local modules carry their machine name, core/contributed identity, enabled state,
declared dependencies and accessible configuration links. Links come from module
configure routes and registered configuration entities. A disabled core module
needs enabling and configuration, **not Composer acquisition**. For extensions
without a declared Drupal project, `local/<module>` is a local identity, not an
installable Composer recommendation. Available code does not establish configured
languages, translatable fields, listing filters or operational access behavior.

The inventory is collected fresh and included in the site fingerprint. Scoring
packets retain module descriptions and dependencies but omit duplicate inventories
and output-only routes/source paths. The complete result keeps that evidence.
Screening adds provider work, reported separately under `local_discovery`; the
normal three-option UI and compact MCP handoff still apply.

## Discovering the ecosystem through Project Browser

The Project Browser adapter is part of the main module and is enabled when
Site Architect is installed.

The adapter calls Project Browser's public source plugin API and respects its
enabled-source configuration. It accepts recipe and module projects. The built-in
local recipe source is skipped because the main module already reads those
manifests with richer evidence.

Project Browser supplies a contributed-module catalogue. To include recipes
that have **not** been downloaded or applied, Site Architect includes
[API Browser](https://www.drupal.org/project/api_browser).

At `/admin/config/development/project_browser`, enable **Packagist Drupal
Recipes**, keeping any existing sources you need. API Browser ships this source
configuration; its plugin ID is `api_browser_project:packagist_recipes`.
Use its **API Browser Services** settings to configure other external JSON
catalogues. The architect has no dependency on Packagist or a particular source ID.
Project Browser's installation UI does not need to be enabled for discovery.

Enter the requirement in the original brief; there is no separate search field.
Jev receives the brief and current site
structure and chooses one of three paths:

- **Search** when comparing existing solutions would help. Jev selects source
  phrases for several capabilities, such as events, groups and activity stream.
- **Local** when current site/core configuration is a sufficient starting point,
  or the brief explicitly asks to stay local.
- **Clarify** when the requirement or search decision is too uncertain.

Only the search path queries external catalogue adapters. Local recipe files
remain available on every path. The resulting candidates then inform the
assessment stage. The local and clarification paths assess local evidence
without querying external catalogs.

The form and assessment tool accept **10–20,000 characters**. Named paragraphs
such as `Groups: ...` retain their requirements together. Other prose is split
at punctuation and common conjunctions. Long sections or clauses use overlapping
96-word windows, supplying adjacent one- and two-word source phrases without
discarding the tail. URLs, email addresses and tokens with digits are excluded.
The route and extraction questions run in batches of up to 12 questions, each
with the complete brief and site context. Jev selects a phrase or `none` for
each segment, and classifies its scope as a work area, detail or context.
`BriefGrouping` then assigns details to proposed subjects before discovery. Stored
attributes, filters and presentation can belong to the same subject instead of
becoming separate catalogue queries. Each assignment must pass the 75% probability /
70% confidence policy. Uncertain assignments retain the original independent work
area or remain visible as unassigned passages; source requirements are not discarded.
Repeated search phrases merge while preserving their source passages. Compound nouns such as
`activity stream` remain intact; English singularization turns `groups` into
`group` and `events` into `event`. Only the search route sends these terms to
external sources. Local plans retain their capability scope without searching.

No topic-to-module mapping or recipe list is hardcoded. This is bounded source
selection, not arbitrary synonym generation or guaranteed anonymization. The
English clause splitter can miss implicit requirements and complex prose. The
UI reports segments processed and exposes unmapped passages for review. Processing
every segment does not establish that every requirement was understood. Current module
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
capability separately and retain up to 12 candidates **per query**, deduplicating
shared packages. There is no shared 24-candidate cap on a plan. The assessment
packs questions with their relevant evidence into multiple requests as needed.
Every work-area choice sees all candidates retained for that query. Each
candidate retains its matching queries and each
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
