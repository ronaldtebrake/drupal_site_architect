# AI Site Advisor

Assess a content brief against what a Drupal site actually has, before an agent
starts building. The module collects site structure, asks typed AI Decision
questions, and returns inspectable advice about content models, presentation,
reuse and recipe relevance.

The phase-one demo uses **Jev through the TypeSafe AI provider**. It can recognise
an existing Workshop content type, distinguish its stored fields from its Canvas
presentation, and ask for clarification when the brief is ambiguous. The same
service is available to a normal Drupal form and an optional Tool API plugin.

This is an advisory prototype. It does not create content types, apply recipes,
generate layouts or publish content. Phase one includes implementation tests and
live smoke checks, but **no model eval suite, accuracy calibration or cost
comparison**.

## Requirements

- PHP 8.3 or later; Drupal 11.2 or later within Drupal 11.
- Core Node and its dependencies.
- [Drupal AI](https://www.drupal.org/project/ai) 1.5 or later within version 1
  (tested with 1.5.0-rc4; allow RC stability while 1.5 is a prerelease).
- [AI Decision](https://www.drupal.org/project/ai_decision), compatible with the
  1.0 development API (`DecisionInput`, `ChoiceQuestion`, `DecisionResponse`).
- A configured provider supporting the **Decision** operation, with a default
  provider and model selected in Drupal AI. The module has no provider fallback.

For Jev, install
[AI Provider TypeSafe AI](https://www.drupal.org/project/ai_provider_typesafeai)
and configure its credential through Drupal Key. This module neither stores nor
ships API credentials. AI Decision and the TypeSafe provider are development
dependencies at the time of this prototype; pin the tested revisions in the host
site's Composer lock file.

**Canvas, Canvas Tools, WebMCP, MCP Server, Tool API and CCC are not required by
the main module.** Canvas options are offered only when Canvas is enabled. It
does not change the site's default AI provider or replace an existing LLM.

## Installation

This directory is a self-contained module, ready to become its own contribution.
Until it has a published release, place it at
`web/modules/contrib/ai_site_advisor` (or use a Composer path/VCS repository).
From the Drupal project's root:

```sh
composer require 'drupal/ai:^1.5@RC' 'drupal/ai_decision:^1.0@dev'
drush en ai_site_advisor -y
drush cr
```

For the Jev demo:

```sh
composer require 'drupal/ai_provider_typesafeai:^1.0@dev'
drush en ai_provider_typesafeai ai_site_advisor_demo -y
```

In DDEV, prefix these commands with `ddev`.

Configure the TypeSafe provider's key and choose its Jev model as the default
**Decision** model under Drupal AI settings. Grant `access ai site advisor` to
trusted site builders. This restricted permission allows inspection of the
selected structural metadata and calls to the configured provider.

Open **Structure → AI Site Advisor**:

```
/admin/structure/ai-site-advisor
```

Set site policy and optional content-type scope at:

```
/admin/config/ai/site-advisor
```

The optional demo submodule creates a regular **Workshop** node type with date,
location, capacity and description fields. Its normal content form is
`/node/add/advisor_workshop`. The adviser reads these real definitions; it does
not receive a hard-coded recommendation. The demo creates no content records.
Uninstalling it removes its owned configuration, subject to Drupal's normal
content-deletion safeguards.

## A short demo

1. Inspect the Workshop fields in Drupal's content-type UI.
2. Open the adviser and assess **Recurring workshops**. Inspect the reuse
   candidate and the separate presentation judgment. A shared visual layout can
   have several implementations, so a review prompt is a useful outcome.
3. Add a requirement to the brief: “Each workshop also needs a separately stored
   ticket price, which visitors must be able to filter on.” Assess again. The
   Workshop type currently has no price field, so look for an extension/review
   judgment rather than unquestioned reuse.
4. Try **One campaign page**. With Canvas installed, this can favour a standalone
   Canvas page over a new repeated-record model.
5. Try **News + review** and inspect the bounded recipe catalog. Recipe relevance
   does not mean it is safe or necessary to apply: an equivalent workflow may
   already exist.
6. Open **See exactly what informed this advice**. Show the live evidence,
   versioned questions, option probabilities, model and reported token usage.
7. Try **An unclear brief** to show how a caller receives questions before it
   proceeds to build.

These are live model judgments, not guaranteed outcomes. The useful handover is
the evidence, candidate IDs and unresolved decisions. The UI does not fabricate
an explanation: displayed descriptions are the predefined option criteria.

## Service contract

Inject `Drupal\ai_site_advisor\Assessment\SiteAdvisorInterface` (service alias)
or `ai_site_advisor.advisor`:

```php
$assessment = $advisor->assess($brief, $account);
```

The account must have `access ai site advisor`. Access is checked inside the
service before evidence collection or inference, including for direct callers.
Briefs must contain 10–4,000 characters. The evidence collector supports at most
24 selected node content types; the request also has a 100 KB size limit.

The result is a serializable array containing:

| Key | Meaning |
| --- | --- |
| `status` | `assessed` or `needs_clarification`; never authorisation to build. |
| `answers` | Choices, complete probability distributions, confidence, review flags and static criteria. |
| `reuse_candidates` / `extension_candidates` | Machine names of confidently matched node types. |
| `site` / `recipes` | The exact inspected evidence; site fingerprint and recipe source hashes. |
| `questions` / `profile` | Exact questions and the versioned assessment profile. |
| `follow_up` | What the caller or human needs to resolve next. |
| `model` / `usage` / `elapsed_ms` | Reported model, input/output/total tokens and server-side elapsed time. Unknown usage remains `null`. |
| `contradictory_judgments` / `limitations` | Cross-answer conflicts and boundaries callers must retain. |

The service throws on denied access, invalid input, oversize evidence, missing
provider configuration, failed inference, missing answers or invalid probability
distributions. Callers should stop and display a safe error. They should not
treat a failed assessment as approval to build, or display raw provider errors.

All independent questions are sent in one Decision request. No LLM is asked to
invent the rubric, retrieve the site configuration or parse free-form advice.
The Drupal collector supplies facts; a versioned profile defines what to judge;
Jev supplies bounded judgments; PHP handles validation and the next-step policy.

Review flags currently use selected-option probability below 0.75, confidence
below 0.7, an unknown/unclear answer, or contradictory primary judgments. These
are **prototype display thresholds**, not measured correctness guarantees.

## Tool API and other callers

Install Tool API and enable the optional integration:

```sh
composer require 'drupal/tool:^1.0@beta'
drush en ai_site_advisor_tool -y
drush tool:info ai_site_advisor:assess_content_brief
drush tool:run ai_site_advisor:assess_content_brief --uid=1 \
  --input='{"brief":"We run recurring workshops with a date, location and capacity. Reuse an existing type where possible."}'
```

The submodule requires Tool API beta8 or newer within the current 1.x API. Its
tool takes a required `brief` string and returns an `assessment` map. It is an
`Explain` operation and repeats the permission check in the service.

An agent, MCP Server, ECA or WebMCP integration can call the same operation through
its existing Tool API adapter. Register/allowlist it in that adapter and preserve
the calling user's identity. **The module does not automatically expose a public
endpoint, register a WebMCP tool, or enable an MCP bridge.** An adapter must handle
its own transport, authentication, browser/page scope and interaction lifecycle.
WebMCP does not need to call MCP Server to use this service.

For an agentic experience, call the adviser before build tools, resolve review
flags, then use the existing tools to inspect and apply the chosen configuration.
The result is advice about suitability, not executable configuration. Independent
permission, compatibility and stale-state checks still belong to the build tools.

## Evidence and privacy

Collected evidence is allowlisted: node-type labels/descriptions, title and
configurable field metadata, entity-reference target bundles, selected module
availability, IDs/labels of Views/workflows/Canvas templates, and site policy.
No node content, user records, provider settings or arbitrary config is read.
Evidence is collected afresh on every assessment; results are not cached for reuse.

The recipe catalog reads four real Drupal core `recipe.yml` manifests: Article,
Basic page, Editorial workflow and Content search. It includes their declared
descriptions, module installs and source hashes. It does **not** inspect all recipe
configuration actions, resolve compatibility, search drupal.org, or infer existing
configuration behavior from labels. Missing recipes are omitted.

Briefs and selected structural metadata go to the configured AI provider. The
form uses Drupal's normal session/form cache; site-configured AI logging and
guardrails still apply. This module logs only the exception class on failures,
not raw provider errors. Review the host site's provider and logging settings
before using private project briefs.

## Extending and contributing

The main service, collector, profile, provider adapter, UI and Tool API adapter are
separate classes. No procedural `.module` file is needed. Decorate the evidence
collector or replace the adviser through their interfaces in Drupal's container.
Both the form and Tool API plugin consume the shared `SiteAdvisorInterface`.

Keep question changes versioned (`ContentPlanningProfile::VERSION`). Add wider
recipe catalogs, CCC-backed policies or other entity types as bounded evidence
sources. Evals, calibrated thresholds, automatic building and wider ecosystem
discovery are later work, outside phase one.

### Validation

Run from a Drupal project with its development dependencies installed:

```sh
SIMPLETEST_DB=sqlite://localhost/:memory: vendor/bin/phpunit \
  -c web/modules/contrib/ai_site_advisor/phpunit.xml.dist
vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php \
  web/modules/contrib/ai_site_advisor
node --check web/modules/contrib/ai_site_advisor/js/advisor.js
```

Adjust `contrib` to `custom` if installed there. PHPUnit bootstraps Drupal core;
the kernel test uses an isolated database and a mocked account, with no provider
credentials or inference calls. It installs the module's dependencies, verifies
operation without Canvas/WebMCP/Tool/TypeSafe, checks fresh field evidence, then
installs and removes the optional Tool API integration. Unit tests exercise the
service's access, response and uncertainty contracts. See [VALIDATION.md](VALIDATION.md)
for the live smoke-check record.

## Attribution and license

This module is original integration code under GPL-2.0-or-later. It consumes
Drupal core, Drupal AI, AI Decision, the TypeSafe AI provider and optionally Tool
API through their public APIs. It does not vendor or fork Canvas, Canvas Tools,
WebMCP Integration or their demo code. The optional Workshop configuration is
owned by this module. The core recipe manifests are read from the host Drupal
installation, not copied into this repository.

The direction grew out of discussions about shared Drupal operations across
human and agent interfaces. Please retain the upstream project links above when
adapting or contributing this work.
