# Performance checks

This pass reduces repeated candidate evidence and repeated catalogue fetches.
It preserves the 100 KB request budget, all candidates and questions, review
thresholds, targeted recovery and independent requirement verification. It does
not cache finished plans or introduce concurrent provider calls.

## What changed

For large plans, independent contribution questions are packed with the candidate
evidence they reference. Every packet retains the full brief and site evidence.
Each work area's source requirements stay separate. Competing starting-point
choices still receive all matching alternatives. Small plans keep the existing
shared-state path.

The Project Browser adapter caches successful result pages for up to
300 seconds through the public source-plugin API. It does not depend on Project
Browser's internal QueryManager. Cache identity includes source ID/configuration,
complete query, user/permission/language contexts, Drupal version and Composer
lockfile hash. Source refresh tags, enabled-source configuration and module changes
invalidate the cache. Error pages and exceptions are not cached. Installation
and enabled state are mapped afresh. Upstream sources may retain older data;
this cache cannot establish upstream freshness. Local recipe discovery is unchanged.

The full assessment and UI evidence report stage timings. Individual Decision
attempts report elapsed milliseconds, including retries. Monotonic clocks avoid
wall-clock adjustments affecting durations.

## Measurements, 27 September 2026

The input was the unchanged [community planning brief](community-planning-brief.txt),
using Jev 1.13.0, ten work areas and 56 source requirements on the development
site. These are sequential smoke observations, not latency percentiles or a
controlled end-to-end benchmark.

| Observed full assessment | Total | Time inside Decision calls | Decision calls |
| --- | ---: | ---: | ---: |
| Before | 70.59 s | 39.08 s | 76 |
| Final implementation, no architect catalogue cache hits | 31.42 s | 29.36 s | 68 |
| Final implementation, repeated catalogue queries | 30.31 s | 29.60 s | 68 |

The final repeat's catalogue discovery took 0.259 s, versus 1.619 s in the
preceding final run. Twenty external source pages were reused across ten searches;
ten local recipe searches still ran. The baseline spent about 31.5 s outside
Decision calls but lacked a separate discovery timer. Upstream cache warmth and
service latency changed during the session, so the entire end-to-end difference
must not be attributed to our code. The site fingerprint remained unchanged.
Live screening retained 175 candidates in the baseline and 174 in the final runs;
threshold-sensitive model responses can change the subsequent workload.

A separate check reused the exact baseline brief, site evidence and 175
candidates for the main scoring stage:

| Fixed evidence, same 941 questions | Before packing | Final packing |
| --- | ---: | ---: |
| Planned requests, excluding recovery | 52 | 46 |
| Compact JSON request bytes, summed | 4,924,709 | 3,617,316 |
| Observed main-stage elapsed | 28.84 s | 21.58 s |

All question instructions and criteria were checked identical. Each request stayed
within 100 KB. The new live run needed one targeted retry, so executed 47 calls.
Request data fell 26.5%; these byte counts are not token or billing reductions.

## Quality checks and limits

Tests verify exact question/choice preservation, full referenced descriptions,
dependencies and actual fields, complete alternatives for competing choices, no
duplicate/missing questions, separate work areas and request budgets. Existing
ranking, uncertainty, rejection, requirement-composition, access and compact MCP
checks remain in the suite. Cache integration tests cover reuse, changed queries
and source configuration, different accounts, expiry, invalidation and recovery
from source errors. All **70 tests / 5,107 assertions** and Drupal/DrupalPractice
coding standards passed.

The fixed-evidence live comparison returned all 941 judgments. Nine of ten
starting-point selections were unchanged; the changed activity-stream choice was
uncertain in both runs. Forty-seven categorical answers differed overall, mostly
uncertain contribution judgments. Of changed contribution/relevance judgments,
one previously confident calendar-recipe contribution became uncertain. Confidence
and review flags remain visible. This is not a labeled quality evaluation and
does not prove identical recommendation quality.

An earlier, faster packing experiment mixed several work areas in one candidate's
scoring context. It incorrectly strengthened search as a notifications component.
That experiment was rejected. The final implementation separates these contexts;
the fixed-evidence check again classified Content search as unrelated to both
notifications and media, with 91% and 94% confidence respectively. This finding is
why merely retaining question text is insufficient evidence of quality.

[TypeSafe's batching guidance](https://docs.typesafe.ai/cookbooks/parallel_questions)
covers independent questions over the same state. Changing which evidence shares
a state needs its own checks; it is not the same guarantee.

The normal Drupal form was exercised with the built-in community example.
The plan rendered without an assessment error, and stage timings appeared in its
evidence. No content, configuration or packages were installed by the assessment.

## Repeating the checks

From the DDEV site root:

~~~sh
ddev exec env SIMPLETEST_DB=mysql://db:db@db/db \
  vendor/bin/phpunit -c web/modules/custom/site_architect/phpunit.xml.dist
~~~

For live inspection, use `/admin/structure/site-architect`, submit the full brief
above and expand **Time by stage (milliseconds)** and **Requests and usage within
each stage**. Repeat within five minutes to inspect catalogue reuse. These runs
call the configured provider; no credentials are printed or included in the plan.
Source reports distinguish cached pages from fresh adapter fetches.

Full MCP results include the same timings. Compact MCP output stays compact.
The largest remaining cost is sequential scoring and requirement verification.
Bounded parallel execution through Drupal AI would need provider/adapter support
and checks for rate limits, failure handling and accounting. Another candidate is
exact-input decision caching scoped to the account, model, rubric and evidence;
neither is part of this performance pass.
