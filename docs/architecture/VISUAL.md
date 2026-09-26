# Visual architecture walkthrough

Open [visual.html](visual.html) for the **25-second visual version**.
The original [43-second walkthrough](index.html) and its exported files are
unchanged.

The one-pager uses moving phrases and connecting paths instead of explanatory
panels:

1. Read the example brief and inspect site metadata.
2. Move words and adjacent phrases out of the brief.
3. Select source labels with Jev; normalise search terms in code.
4. Add discovery through local recipes and Project Browser.
5. Check proposed matches between requirement parts and candidate building blocks.
6. Assemble a draft plan for the Drupal UI and Tool API/MCP handoff.

The examples are illustrative, not a replay of measured Jev responses. The
existing content type, Comment, Views and notification options illustrate roles
in a possible plan. They are **not** hardcoded feature-to-module mappings in the
adviser. Green connections indicate a proposed match; they are not compatibility
or integration verification. The notification choice is left unresolved.

The visual groups extraction and later requirement decomposition for readability.
The actual implementation keeps named source sections, selects one/two-word
source phrases through typed Choice questions, and performs sentence/list-item
requirement matching after candidate assessment. It does not use a generative
word cloud. Site evidence is collected before these decisions. Discovery is
shown before candidate scoring, and normalisation remains a code operation.

## Playback

No dependencies, live provider calls or network resources are required. Open
the HTML directly, or use the local demo URL:

`https://webmcp-integration.ddev.site/modules/custom/ai_site_advisor/docs/architecture/visual.html`

Play/Pause, Restart and the scrubber are below the animation. The clean recording
link adds `?record=1`. A paused frame can be selected with
`?record=1&paused=1&t=18`. Reduced-motion preferences disable autoplay and show
settled chapter states. A link returns to the original version.

## Export

Node.js 22+, Chromium and FFmpeg are required by the optional renderer. From the
DDEV project root:

```sh
ddev exec node web/modules/custom/ai_site_advisor/docs/architecture/render-visual.mjs \
  --output /var/www/html/artifacts/ai-site-advisor-visual
```

Add `--check` for chapter stills and verification without video encoding.
The renderer uses a fresh temporary browser profile and deletes it afterward.

Outputs are separate from the original animation:

- `ai-site-advisor-visual.mp4`: 1280 × 900, 30 fps, 25 seconds, no audio.
- `ai-site-advisor-visual.gif`: 960 × 675, 15 fps, infinite loop.
- `chapter-01.png` through `chapter-06.png`.
- `checks.json`: source hash, stage bounds, resource text bounds, reproducible
  seeking, controls, reduced-motion and JavaScript checks.
- `video-metadata.json`: encoded stream and duration validation.

Keep generated media outside the module repository. The original renderer and
HTML are retained as a separate deliverable. Animation timing is editorial,
not a measure of inference speed.
