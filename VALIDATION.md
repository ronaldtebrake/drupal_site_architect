# Phase-one validation

Verified on 26 September 2026. These are implementation checks and live smoke
observations, not an evaluation dataset or a model-quality benchmark.

## Environment

- Drupal 11.4.6, PHP 8.3.19.
- Drupal AI 1.5.0-rc4.
- AI Decision `dev-1.0.x`; TypeSafe provider `dev-1.0.x`.
- Default Decision selection: `typesafeai` / `jev-latest`.
- Model reported by live responses: `jev-1.13.0`.
- Optional Tool API 1.0.0-beta8; Canvas 1.11.0 on the demo site.
- No credentials or provider configuration exported with this module.

## Automated verification

- PHPUnit: **11 tests, 68 assertions**, passing.
- Fresh SQLite kernel installation brings in the declared dependencies and the
  optional Workshop configuration without Canvas, Canvas Tools, Tool API,
  WebMCP Integration or the TypeSafe provider enabled.
- The collector reads the actual fields, excludes node ownership metadata, and
  changes its evidence fingerprint when a real field is added in the test site.
- The normal Drupal form survives serialization/restoration, exercising the
  cached-form behavior required for subsequent AJAX submissions.
- Optional Tool API integration installs, discovers the plugin and uninstalls
  while preserving the main module.
- Unit checks cover access before collection/inference, independent presentation
  advice, clarification, contradictory judgments, unavailable Canvas, missing
  answers and malformed probability distributions.
- Drupal and DrupalPractice PHP coding standards passed.
- Composer metadata validation and JavaScript syntax checks passed.

Run instructions are in [README.md](README.md#validation). All inference is mocked
in these tests; no API key is needed. The kernel test uses an isolated database,
not the demo site's active database.

## Live observations

| Brief | Interface | Observed result |
| --- | --- | --- |
| Recurring workshops: date, location, capacity, description, shared layout | Drupal form | Workshop is a reuse candidate; records are appropriate. Canvas template is the leading presentation choice, but uncertainty remains and the UI asks the user to choose presentation. |
| Workshops with an additional stored/filterable ticket price | Tool API | Workshop needs extension; the existing type has no price field. |
| One-off campaign with freely arranged sections | Drupal form | Standalone page with Canvas presentation. An uncertain match to an existing Article type remains marked for review. |
| News with title, body, image and editorial review | Tool API | Article and Editorial workflow recipes are relevant. The existing Article type is an uncertain extension candidate, rather than confidently reusable. |
| “We need something better for our website” | Drupal form | Content model and presentation both require clarification. |

The final workshop form run reported 4,010 input / 439 output tokens and 0.36 s
server-side assessment time. The ticket-price tool call reported 4,009 / 440 tokens
and 0.438 s. These numbers only demonstrate that actual provider usage is surfaced.
They do not establish savings, comparative latency or accuracy. Cached-input
breakdown and billing data are not supplied by this response contract; elapsed
time excludes browser interaction and the surrounding agent workflow.

## Browser and access checks

- Anonymous navigation to the adviser is denied.
- Tool API execution as anonymous (`--uid=0`) is denied.
- Authenticated UI renders the real metadata and inspectable questions/results.
- Successive AJAX submissions work on the same page, including unclear →
  recurring-workshop briefs. An issue with private injected properties during
  form restoration was fixed and covered by the kernel regression check.
- Changing the brief hides the previous assessment until it is assessed again.
- The regular `/node/add/advisor_workshop` form includes the real rich-text
  description, date/time, location and numeric capacity widgets.
- The standalone settings page renders site policy and content-type scope.
- The desktop layout was visually inspected in the browser.

## Limits of this verification

No end-to-end MCP Server, WebMCP or ECA adapter was configured for this module.
The verified integration boundary is the Tool API plugin. No recipes were applied,
no layouts were generated, and no content was created by the adviser. A wider
recipe catalog, calibrated thresholds and comparative evals remain later work.
