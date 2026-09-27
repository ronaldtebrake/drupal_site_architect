# Drupal Site Architect architecture animation

Start with the [22-second story](story.html): a brief becomes phrases, candidates
from the site and ecosystem, visible Jev scores and a draft plan. Its
[score provenance and export instructions](STORY.md) explain the recorded judgments.

There is also a [25-second visual version](visual.html) with moving phrases,
candidate connections and assembling plan cards. See [its export instructions](VISUAL.md).
The original walkthrough below remains available unchanged.

A self-contained, silent 43-second walkthrough of the current architect. Open
[index.html](index.html) directly in a browser; it needs no Drupal runtime,
provider credentials, network requests, fonts or JavaScript packages.

In the local DDEV site:

`https://webmcp-integration.ddev.site/modules/custom/site_architect/docs/architecture/index.html`

The page loops and offers Play/Pause, Restart and a scrubber. Reduced-motion
preferences disable autoplay. The clean recording link uses `?record=1` to hide
controls and fill the viewport. Use a **1280 × 900** viewport for exact framing.
`?record=1&paused=1&t=32` opens a paused frame at 32 seconds.

This is an **architecture explanation with illustrative content**, not a
recording of an assessment. It contains no measured scores or performance
claims. Its fixed sample labels and module names illustrate a path through the
architecture; they are not a feature mapping used by the architect. The current
automatic planning path uses the included Project Browser integration and the
catalog sources configured on the site. The service also supports local-only
assessments when that matches the brief.

## Story and implementation references

| Time | Stage | Current implementation |
| --- | --- | --- |
| 0–4 s | Receive a brief from the Drupal form or an agent | `SiteArchitect::assess()`; `AssessContentBrief` Tool API plugin and MCP Server adapter |
| 4–9 s | Read actual site structure | `SiteContextCollector`, `ConfigurationInspector`, `ModuleInventory` |
| 9–14 s | Split source text and construct possible labels | `BriefCapabilities::clauses()` |
| 14–19 s | Select labels and the search route, then normalise terms | Jev via `SearchPlanner`; `BriefCapabilities::query()` |
| 19–25 s | Discover candidate building blocks | `CandidateCatalog`, `RecipeCatalog`, `LocalModuleCandidates`, `ProjectBrowserCatalogSource` |
| 25–31 s | Assess the fit using typed decisions | `ContentPlanningProfile`, `DecisionBatch`, `ChoiceValidator` |
| 31–37 s | Match individual requirement parts and check support | `RequirementPlanner` |
| 37–43 s | Rank and present the draft plan | `OptionRanking`, `PlanHighlights`, `AgentPlan`, Drupal template |

The site evidence is collected first and reused in later judgments. Every Jev
decision goes through Drupal AI's Decision operation and the configured TypeSafe
AI provider. The UI calls the service directly; it does not need Tool API or MCP.
Project Browser provides catalogue evidence; it does not score, install or
configure projects for this assessment. The separate `discover_candidates`
tool exposes discovery without requiring an assessment.

Per-part support is a second model judgment, not an integration test. The result
retains open decisions, review flags and `integration_verified: false`. Catalogue
searches and source-phrase extraction are bounded. No combination of proposed
modules is presented as a tested solution.

Visual direction references the supplied Faultline Jev/TIA animation. No
Faultline source code, logos or media assets were copied.

## Export and verify

The optional renderer requires **Node.js 22+, Chromium and FFmpeg**. It has no npm
dependencies. It opens only this local HTML file in a fresh, temporary Chromium
profile, verifies every stage, captures deterministic frames and removes its
temporary profile afterward. It does not use the editor's browser session.

From the sandbox project root:

```sh
ddev exec node web/modules/custom/site_architect/docs/architecture/render.mjs \
  --output /var/www/html/artifacts/site-architect-architecture
```

Add `--check` to inspect the chapters and playback without encoding the video.
Set `CHROMIUM` if the Chromium executable has another name.

Outputs:

- `site-architect-architecture.mp4`: H.264, 1280 × 900, 24 fps, no audio.
- `site-architect-architecture.gif`: 960 × 675, 12 fps, infinite loop.
- `chapter-01.png` through `chapter-08.png`: review frames.
- `checks.json`: source hash, chapter layout, runtime, playback and reduced-motion checks.
- `video-metadata.json`: encoded stream and duration verification.

Generated media belongs in the chosen output directory, outside the module's Git
history. The source HTML and renderer are the editable, reproducible deliverables.
The animation timings explain the architecture; they do not measure inference
latency.
