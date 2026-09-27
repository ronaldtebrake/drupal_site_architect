# Brief → words → evidence → scores → plan

[story.html](story.html) is a 22-second, silent story. It preserves both earlier
versions: [the original walkthrough](index.html) and [the moving diagram](visual.html).

The same three requirements stay in the same lanes throughout. Words move out of
the brief once; candidate cards then appear directly underneath them. The site
and ecosystem are visibly separate. Jev scores appear on those cards, and the
final frame adds next steps and unresolved work.

## Recorded scores, not invented numbers

The three partial-fit judgments come from the sandbox's recorded
`artifacts/ai-site-advisor-parts/focused-assessment.json`. That development run
preceded later architect refinements; this is a narrative replay, not a fresh
assessment or a benchmark. The brief and candidate labels are shortened for
display. The full brief, original question IDs, source hash and distributions
are retained in [story-scores.json](story-scores.json).

| Requirement part | Candidate | P(partial fit) | Confidence |
| --- | --- | --- | --- |
| Opening discussion post | Article (WebMCP demo) | 71% | 61% |
| Replies to posts | Comment | 79% | 72% |
| Subscriptions and email on replies | Notification System | 85% | 79% |

These are **separate per-requirement judgments**, not scores to rank against one
another. They are not percentages of a requirement covered. Every selected
example needs review. Article and Notification System also had uncertain
candidate-selection judgments in that run. The final frame therefore asks the
builder to inspect, configure or review; it does not certify a complete solution.
Club access and subscription rules remain explicit open work.

Source extraction and later requirement decomposition are combined into a short
narrative. The production service selects source phrases and performs separate
requirement checks; it does not infer a verified architecture from these three
phrases alone. Module/recipe discovery can use local sources and Project Browser.
The animation shows only three selected examples to keep the story legible.

## Playback and export

Open the self-contained HTML directly or at:

`https://webmcp-integration.ddev.site/modules/custom/site_architect/docs/architecture/story.html`

The page has Play/Pause, Restart, a scrubber and a clean `?record=1` view.
Reduced-motion preferences disable autoplay. The page makes no network or model
calls and contains no credentials.

```sh
ddev exec node web/modules/custom/site_architect/docs/architecture/render-story.mjs \
  --output /var/www/html/artifacts/site-architect-story
```

The renderer requires Node 22+, Chromium and FFmpeg; no npm packages are needed.
Use `--check` to verify chapters and controls without encoding.

- MP4: `site-architect-story.mp4`, 1280 × 900, 30 fps, 22 seconds, no audio.
- GIF: `site-architect-story.gif`, 960 × 675, 15 fps, infinite loop.
- Five chapter stills, source hash, layout/score-overlap checks, deterministic
  seeking, playback controls, reduced-motion checks and encoded video metadata.

All timings are editorial. No latency or cost comparison is claimed.
