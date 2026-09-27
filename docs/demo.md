# Try it

[Back to README](../README.md)

## Recurring workshops

Apply the optional example recipe from a Drupal project:

```sh
vendor/bin/drush recipe modules/contrib/site_architect/recipes/workshop
```

Adjust `contrib` to `custom` if needed. The recipe creates a Workshop content type
with date, location, capacity and description fields, but no content records.
Inspect `/node/add/advisor_workshop`, then select **Recurring workshops** in
Site Architect. Review how the plan connects records, stored fields, a filtered
listing and presentation. Field presence does not prove recurrence is configured.

## A community site

Try **A community site**, or paste [the detailed brief](community-planning-brief.txt).
Inspect the work areas, search terms and source results. Expand candidate scores,
check how multiple components could serve one requirement, and review open gaps.
Results vary with the site, catalogs and provider; no particular winner is guaranteed.

Use **Copy plan for an agent** to hand off the reviewed draft, or ask an
authenticated MCP agent to assess the same brief before building anything.

For a shareable walkthrough, open the [visual story](architecture/story.html).
The [recorded Jev comparison](jev-comparison/README.md) shows the same evidence
with and without scored judgments, including unresolved choices.
