# Keyword matches and Jev judgments

[Back to README](../../README.md) · [Interactive comparison](index.html)

![Workshop comparison](workshops.png)

Both versions find useful building blocks. Jev adds proposed relationships,
component roles and uncertainty that the application uses to compose a plan.
It does not make every suggestion correct.

## Method

Three recorded briefs use the same inspected site and catalog evidence. Fixed
searches (`workshop`, `translation`, `notification`) bypass automatic search
planning on both sides. Their candidate pools contain 115, 125 and 125 items.

| | Without Jev | With Jev |
| --- | --- | --- |
| Retrieval evidence | Same site snapshot, descriptions, fields and catalog results | Same evidence |
| Candidate handling | BM25 ranking, top three positive matches per source sentence | Production semantic screening, assessment and requirement planning |
| Additional judgments | None | Component role, target record, field usage, coverage and uncertainty |
| Output | Inspection checklist | Scored draft plan |

BM25 uses `k1=1.2`, `b=0.75`, lowercasing, general English stop words and simple
suffix normalization. It has no project-specific weights or Jev-selected input.
The provider reported `jev-1.13.0`, using profile `content-planning-v8`.
The prose and visual cards are editorial summaries; original judgments and source
sentences remain in the evidence table.

This compares one lexical baseline with Jev, not another LLM or every possible
approach without Jev. It is not an accuracy eval or a cost/speed benchmark.
Discovery was bounded, and no proposed combination was installed or validated.

## What the cases show

| Case | Useful added connection | Remaining gap |
| --- | --- | --- |
| [Workshops](workshops.png) | Views → existing Workshop records → inspected location field as a filter. | Recurrence was overestimated; the date field does not prove recurring scheduling. Layout selection remained uncertain. |
| [Translation](translation.png) | Core Content Translation for existing article fields and records. | The weak Select translation suggestion does not establish a reader-facing language switcher. |
| [Discussions](discussions.png) | Separate the opening record, Comment replies and a possible notification component. | Notification System had high partial-contribution evidence but weak selection (46%, confidence 44%); it was not a confirmed recommendation. |

Component usefulness and choosing the best component are different judgments.
Probability of partial coverage is not the percentage of a requirement completed.
These weak results are retained in the recorded evidence, not removed from the
comparison. A builder still needs to inspect and validate the proposed connections.

## Inspect or reproduce

- [cases.json](cases.json): all briefs and fixed queries, declared before inference.
- [results.json](results.json): public source evidence, lexical rankings, captured
  judgments, site fingerprints and capture hashes. Credentials and content records
  are excluded.
- [capture.php](capture.php): calls the existing services and checks that the
  compared site fingerprints and candidate pools match.
- [build.mjs](build.mjs): deterministic baseline and allowlisted export.
- [render.mjs](render.mjs): desktop/mobile checks and PNG export using Node 22+
  and Chromium. Rendering makes no model calls.

From a Drupal project with a configured Decision provider:

```sh
mkdir -p /tmp/site-architect-comparison
ARCHITECT_COMPARISON_OUTPUT=/tmp/site-architect-comparison \
  vendor/bin/drush php:script web/modules/contrib/site_architect/docs/jev-comparison/capture.php
node web/modules/contrib/site_architect/docs/jev-comparison/build.mjs \
  /tmp/site-architect-comparison
node web/modules/contrib/site_architect/docs/jev-comparison/render.mjs
```

Adjust `contrib` to `custom` if needed. Capturing makes real provider calls and can
populate normal caches/logs; use a new output directory outside the web root and
review captured metadata before publishing. Rebuilding from captures is offline.
