# Development

[Back to README](../README.md) · [Tool contracts](tools.md)

## How a brief becomes a plan

1. Inspect current content types, fields, workflows, configuration links and
   visible module metadata. Core modules are considered even when disabled;
   enabled contributed and custom modules are included.
2. Divide the brief into source passages. Jev selects source phrases and groups
   details under work areas. Uncertain ownership remains provisional or unassigned.
3. Choose local evidence, ecosystem discovery or clarification. A selected
   read-only search still runs when uncertain, retaining its review flag.
4. Discover local recipes and query enabled Project Browser sources. There are
   no hardcoded project recommendations or keyword-to-module mappings.
5. Screen relevance, score starting points and independent contributions, then
   check requirement parts against candidate descriptions and actual fields.
6. Compose the plan for the UI and compact agent handoff. The application writes
   the guidance from typed judgments; Jev does not generate free-form plan prose.

Local manifests come from core recipes, Composer recipe packages, conventional
recipe directories and configured extra roots. Scans reach two subdirectory levels.
Manifest/configuration presence is evidence to inspect, not application history
or a simulation of recipe effects. Failed catalogs and successful zero-match
queries are reported separately. Only confident unrelated local modules are
excluded from detailed assessment.

## Evidence and limits

The collector exports structural metadata, not content records, user records,
credentials or arbitrary configuration. The brief and selected evidence go to
the Decision provider; catalogs receive search terms rather than the full brief.
Host AI logging and cache settings still apply.

- Briefs: 10–20,000 characters and at most 200 text segments; over-limit input is
  rejected, not silently clipped. Discovery retains up to 12 candidates per query.
- Up to 24 selected node types are inspected. Narrow the settings scope if needed.
- Independent questions are batched within each stage. Requests are bounded to
  100,000 UTF-8 JSON bytes; large briefs require additional requests.
- Probability below 0.75, confidence below 0.7, unknown answers and contradictory
  judgments trigger review. These are prototype policies, not calibrated accuracy.
- Invalid distributions, missing answers or conflicting choices get one targeted
  retry. A second invalid response rejects the plan; scores are never repaired.

Site evidence is collected afresh. Successful catalog pages can be reused for
five minutes, scoped by source, query, caller permissions, language and lockfile.
Failures are not cached. The full response includes per-stage timing and usage;
provider-reported tokens can include cached work and do not establish billing savings.
Planning is synchronous. Package compatibility, combined behavior and runtime
access still need validation before implementation.

## Extending

Inject `Drupal\site_architect\Assessment\SiteArchitectInterface`
(`site_architect.architect`) and call `assess($brief, $account)`. For discovery,
inject `site_architect.candidates` and call `discover($query, $account, limit: 12)`.
Services repeat permission checks even if a caller skips Tool API access checks.

Replace the planner through `SearchPlannerInterface`, decorate the collector,
or implement `CatalogSourceInterface` with the `site_architect.catalog_source`
service tag. Keep rubric changes versioned. Model evals, additional entity types
and installation workflows remain separate follow-up work.

## Tests

From a Drupal project with this module and development dependencies installed:

```sh
SIMPLETEST_DB=sqlite://localhost/:memory: vendor/bin/phpunit \
  -c web/modules/contrib/site_architect/phpunit.xml.dist
vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php \
  web/modules/contrib/site_architect
node --test web/modules/contrib/site_architect/tests/js/architect.test.cjs
```

Adjust `contrib` to `custom` if needed. Tests use isolated SQLite storage and
fixture catalogs, with no provider key or external requests. Reusable checks
belong in `tests/`; keep one-off diagnostics outside the repository.

Verified: 82 PHP tests, 3 JavaScript tests, fresh installation, recipe-based
OAuth setup, and live HTTPS MCP discovery/assessment with authorization-code
S256 PKCE. Missing scope, missing permission and invalid tokens were rejected.
External-client dynamic registration, refresh tokens and upstream revocation
were not covered by that live check.
