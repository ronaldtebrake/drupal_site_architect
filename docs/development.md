# Development reference

[Back to README](../README.md)

Inject `Drupal\site_architect\Assessment\SiteArchitectInterface`, or service
`site_architect.architect`:

```php
$assessment = $architect->assess($brief, $account);
```

For discovery alone, inject `site_architect.candidates`:

```php
$discovery = $catalog->discover('workflow', $account, limit: 12);
```

| Assessment key | Meaning |
| --- | --- |
| `status`, `summary`, `follow_up` | Planning outcome and unresolved questions; never authorisation to build. |
| `answers` | Choices, full distributions, confidence, individual review flags and static criteria. |
| `reuse_candidates`, `extension_candidates` | Confidently matched node content-type IDs. |
| `workflow_candidates` | Existing workflow IDs with a `ready` or `extend` judgment. |
| `adoption_candidates` | Confidently relevant local-module and catalogue candidate IDs, not verified install targets. |
| `local_discovery` | Semantic screening of shipped core and enabled local modules: retained candidates, every screening judgment, questions and usage. |
| `site`, `candidates`, `discovery` | Exact evidence, site fingerprint, source reports and search boundaries. |
| `plan` | Draft work areas, visible preferred selections, every assessed option with separate contribution/selection/brief-relevance judgments, foundation/addition investigation paths, checks and handoff boundaries. |
| `search_plan` | Route, grouped capabilities, queries, unmapped clauses, segment coverage and planning questions/answers (`ecosystem-search-v5`). `query` remains the first term for compatibility; `terms_truncated` is false after successful extraction. |
| `questions`, `profile` | Reviewed questions and versioned rubric (`content-planning-v8`). |
| `model`, `usage`, `usage_by_stage`, `elapsed_ms` | Assessment model, summed provider usage, per-stage usage and elapsed server time including planning/discovery. |
| `requests_by_stage` | Model, question IDs, evidence IDs, compact bytes and reported usage for every provider request. |
| `build_guidance`, `limitations`, `contradictory_judgments` | Boundaries callers must retain. |

`recipes` remains an alias of `candidates`, and candidate question IDs retain
the `recipe__` prefix for compatibility with the initial prototype. These now
include module candidates; inspect each candidate's `kind`. Local module IDs
use `module__<machine_name>`, so core modules remain separate choices despite
sharing `drupal/core`. A discovered project representing the same local module
is coalesced in the comparison; the raw catalogue result remains in `discovery`.

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
planning precedes discovery and assessment; each stage may make multiple Decision
requests. Valid global content-model and presentation answers are reused throughout
the assessment.
Candidate relevance needs its source description; a work-area choice sees the
matching candidates and the complete brief. Evidence is repacked when a full
plan would exceed the per-request limit, without dropping questions or candidates.
Large plans group independent contribution questions with the candidates they
actually inspect, keeping each work area's complete source text separate. A
starting-point selection still sees all its alternatives. The full brief, site
evidence, question wording and validation policy are retained.
Independent `role__<work-area>__<option>` questions assess potential contributions
within that work area. They run in the assessment stage and increase reported
usage; the starting-point distribution is not reused as a relevance score.
Local module screening precedes detailed assessment, including when no ecosystem
adapter is installed. Its usage and requests are recorded as `local_discovery`.
`usage` sums all requests; `usage_by_stage` preserves the breakdown. Unknown counts stay
`null` rather than being treated as zero. An explicit search override skips
planning, as does a site without a remote adapter.

If a normalized response has missing answers, mismatched options, an invalid
distribution total or a selected option below the highest score, the architect
retries only the affected questions once. Evidence and criteria stay identical;
valid answers (including uncertain ones) are kept. Scores are never repaired
or normalized locally. A second invalid response rejects the entire plan.
Provider execution errors still stop immediately. `requests_by_stage` records
each attempt and `rejected_answers` reason codes; usage includes rejected
attempts and recovery. Persistent contract failures log only reason counts,
without provider responses or site evidence.

The page presents site reuse within each work area's plan and configuration
links. There is no separate "What the architect can see" panel or extra site scan
when the form rebuilds. Removing that panel does not narrow assessment evidence.

Site evidence and local recipe files are inspected afresh; the architect does not
cache assessments. The Project Browser adapter reuses successful
catalogue pages for up to five minutes, scoped by source configuration, query,
account/permissions, language and Composer lockfile. Source refresh tags and
changes to enabled sources/modules invalidate these entries. Package availability
is checked again when mapping results. Failures are not cached. Discovery reports
include cache hit, stored time and lifetime; upstream fetch age may still be unknown.
The provider may cache its own responses, including their usage metadata. Reported tokens are **not necessarily
newly billed tokens for this call**, and elapsed time is not a full agent-task
measurement. Unknown usage remains `null`; cached-input/billing breakdown is not
available through this response contract.

The full result exposes `timings_ms` for site inspection, search planning,
catalogue discovery, local screening, scoring, requirement checks and composition.
Each provider attempt has `elapsed_ms`, including targeted retries. These timings
also appear under the UI's expandable evidence. See [performance checks and
measurements](performance.md) for the observed gains and quality limits.

These are module resource limits, not claims about Jev's context window:

- A brief is at most 20,000 characters; automatic extraction accepts up to 200
  text segments. An over-limit brief is rejected before inference, never clipped.
- Extraction batches contain up to 12 questions; assessment batches up to 48.
  Each compact UTF-8 JSON request is at most 100,000 bytes. Larger plans use
  additional requests. A single oversized evidence packet fails explicitly.
- A keyword sent to a catalogue is at most 120 characters. It is a short search
  phrase selected from the brief, not the planning brief itself.
- Each query has up to 12 retained candidates from bounded source pages. Search
  coverage is still limited and is reported separately from extraction coverage.
- Site inspection supports at most 24 selected node types; narrow the scope in
  settings for larger sites. This is independent of brief length.

The constants are in `BriefCapabilities` and `DecisionBatch`; batching is an
implementation detail, not a request for the site builder to split ordinary
briefs manually. Longer plans increase latency and provider usage. This remains
a synchronous prototype; very large planning jobs need a resumable background
workflow rather than unbounded request limits. Display formatting is not included
in request size checks. Missing answers and invalid options/distributions stop
assessment if targeted recovery fails; denied access and failed inference stop
immediately. Review flags use probability below 0.75, confidence below 0.7,
unknown/unclear answers or contradictory primary judgments. These are prototype
display thresholds, not calibrated correctness guarantees. Callers must retain
individual review flags even when the overall status is `assessed`.

## Extending and validating

The collector, search planner, profile, provider adapter, architect and UI are
separate classes. Replace the planner through `SearchPlannerInterface`, decorate
the collector or replace the architect through their interfaces. Add a
catalogue adapter by implementing `CatalogSourceInterface` and tagging its
service `site_architect.catalog_source`. No procedural `.module` file is needed.
Keep rubric changes versioned. New entity types, package compatibility checks,
approved installation workflows and model evals are separate follow-up work.

Run from a Drupal project with Site Architect and development tools installed:

```sh
SIMPLETEST_DB=sqlite://localhost/:memory: vendor/bin/phpunit \
  -c web/modules/contrib/site_architect/phpunit.xml.dist
vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php \
  web/modules/contrib/site_architect
node --check web/modules/contrib/site_architect/js/architect.js
node --test web/modules/contrib/site_architect/tests/js/architect.test.cjs
```

Adjust `contrib` to `custom` as needed. Tests use an isolated SQLite database,
real local manifests and a fixture Project Browser source. They make no external
catalogue or inference requests and require no API key. See
[VALIDATION.md](../VALIDATION.md) for verified behavior and live MCP/browser checks.
