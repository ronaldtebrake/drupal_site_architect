# Visual explainer

[Back to README](../../README.md)

The [22-second story](story.html) follows **brief → phrases → site and ecosystem
candidates → Jev scores → draft plan**. Its [GIF](site-architect-story.gif) is
embedded in the project README. Earlier [visual](visual.html) and
[detailed](index.html) versions are also available.

The story replays three recorded partial-fit judgments. The full source brief,
question IDs, capture hash and distributions are in [story-scores.json](story-scores.json).
These are separate judgments about discussion records, replies and notifications,
not comparable overall quality scores. Every example needs review; uncertain
selection, club access and subscription rules remain unresolved.

The animation compresses several production stages for readability. The earlier
versions are illustrative rather than recorded assessments. All timings are
editorial, with no claim about inference speed, cost or verified integrations.

## Playback and export

Open the HTML directly. It works offline, with Play/Pause, Restart, a scrubber and
a clean `?record=1` view. Reduced-motion preferences disable autoplay.

From the module directory, with Node 22+, Chromium and FFmpeg installed:

```sh
node docs/architecture/render-story.mjs --output /tmp/site-architect-story
```

This exports a silent MP4, looping GIF, chapter stills and verification results.
Add `--check` to verify playback and layout without encoding. Use
`render-visual.mjs` or `render.mjs` for the earlier versions. Generated output
belongs outside the repository; rendering makes no provider calls.
