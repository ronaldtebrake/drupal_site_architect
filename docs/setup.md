# Setup notes

[Back to README](../README.md)

Use the installation commands in the README. Composer downloads the complete
planning stack; applying Agent Access installs the connection modules; enabling
Site Architect registers its services and tools. No demo content is installed.

## Composer and recipe paths

The project needs Drupal's Composer repository (`https://packages.drupal.org/8`),
as configured by `drupal/recommended-project`. Site Architect is currently
distributed through GitHub, so its VCS repository must be added before requiring
`drupal/site_architect`.

The AI Decision and TypeSafe VCS repositories work around stale Drupal package
metadata. They fetch the standalone modules without duplicate legacy submodules.
See the [upstream packaging note](https://git.drupalcode.org/project/ai_provider_typesafeai/-/blob/1.0.x/README.md).

Some dependencies are prereleases. The README's `minimum-stability` and
`prefer-stable` commands affect the whole Composer project. Sites that require a
stable minimum can instead allow those prereleases explicitly in root requirements.
Commit the site's `composer.json` and `composer.lock` for repeatable installs.

On a standard project, Agent Access is downloaded to `recipes/agent_access`
beside `composer.json`. Drush resolves recipe paths from the Drupal document
root, hence `../recipes/agent_access`. Adjust this if your installer paths differ.

## Planning configuration

- Store the TypeSafe key at `/admin/config/system/keys`, select it at
  `/admin/config/ai/providers/typesafeai`, then select TypeSafe AI and Jev
  (`jev-latest`) for the **Decision** operation at `/admin/config/ai/settings`.
  A chat model does not configure the Decision operation. Keep secrets out of Git.
- At `/admin/config/development/project_browser`, enable **Drupal.org**
  (`drupalorg_jsonapi`) and **Packagist Drupal Recipes**
  (`api_browser_project:packagist_recipes`). Discovery respects enabled sources;
  Project Browser's package-installation UI is not required.
- Grant `access site architect` to planning users. Grant `administer site architect`
  only to users who should change its settings. Review policy, content-type scope
  and extra local recipe directories at `/admin/config/ai/site-architect`.

The site needs outbound HTTPS access to the provider and selected catalogs.
Core recipes and locally installed recipe manifests are discovered automatically.

## Connect an agent

1. Follow [Agent Access's key setup](https://git.drupalcode.org/project/agent_access/-/blob/1.0.x/README.md#connect-and-try-it).
   Generate OAuth keys outside the web root. Verify that
   `/.well-known/oauth-authorization-server` advertises the correct HTTPS
   registration endpoint, not localhost.
2. Use a dedicated non-administrator account with `grant simple_oauth codes`,
   `access mcp server` and `access site architect`. The module grants no roles or
   permissions automatically.
3. Connect an OAuth-capable agent to `https://your-site.example/mcp` and request
   `drupal:mcp:connect drupal:site-architect:plan`. Sign in and approve access.
   Add `drupal:content:read` and the account's `access content` permission when
   using Agent Access's starter content tools.

For manual Consumers, PKCE, dynamic registration and disconnecting, follow
[Agent Access's client guide](https://git.drupalcode.org/project/agent_access/-/blob/1.0.x/CONNECTING.md).
The agent must be able to reach the endpoint; local development hostnames work
only for local clients. The Jev key stays in Drupal and is not an MCP credential.

Both the approved token scope and the account permission must allow planning.
Tool listing is not permission-filtered by the tested stack; execution is checked.
Verify the mappings at `/admin/config/services/mcp-server/tools`, then call
`tool_api__site_architect_discover` with `{"query":"workflow"}`.

Agent Access is experimental. Its [upstream security limits](https://git.drupalcode.org/project/agent_access/-/blob/1.0.x/SECURITY.md)
apply, including client registration, revocation and starter-tool access limitations.
