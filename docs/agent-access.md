# Agent Access with Site Architect

[Back to README](../README.md)

Agent Access owns the connection setup: MCP Server, Tool Bridge, OAuth, its two
starter content tools and connection/content scopes. Site Architect contributes
the planning service, its Tool API plugins, two tool mappings and a planning scope.
There is no separate authentication implementation or fork in this module.

## Install the base and the planning tools

Follow [installation](installation.md) to download the dependencies, apply
`agent_access` and enable `site_architect`. Composer downloads the recipe and
modules; applying the recipe installs its connection configuration. These are
separate steps. Existing Agent Access installations can keep their settings and
enable Site Architect on top of the compatible stack.

Site Architect requires Drupal 11.4+, matching Agent Access. Its Composer file
requires Agent Access and the APIs it uses directly. The recipe supplies the
MCP/OAuth dependency stack, replacing our previous exact MCP version pins.
Drupal AI, AI Decision, TypeSafe, Project Browser and API Browser remain planning
dependencies. We do not rely on unrelated transitive dependencies to provide
classes that our code imports.

## Finish the OAuth connection

1. Follow [Agent Access's setup](https://git.drupalcode.org/project/agent_access/-/blob/1.0.x/README.md#connect-and-try-it)
   to generate keys outside the web root and verify the advertised registration
   endpoint uses the site's reachable HTTPS address. Keep credentials out of Git.
2. Use a dedicated non-administrator test account/role with `grant simple_oauth codes`,
   `access mcp server` and **`access site architect`**. Add `access content` for
   the starter content tools. No role permissions are granted by Site Architect.
3. Connect an OAuth-capable agent to `https://your-site.example/mcp`. Request:

   ```text
   drupal:mcp:connect drupal:site-architect:plan
   ```

   Include `drupal:content:read` when using the starter content tools as well.
   For a manually created Consumer, select these scopes in its authorization-code
   settings and request them from the client. The two upstream default scopes do
   not include Site Architect planning permission.
4. Sign in to Drupal and approve the requested scopes. Follow
   [Agent Access's client guide](https://git.drupalcode.org/project/agent_access/-/blob/1.0.x/CONNECTING.md)
   for dynamic registration, manual Consumers, PKCE and disconnecting.

The planning scope maps to the existing `access site architect` permission. For
ordinary user accounts, the permission and the approved token scope must both
allow the operation. Do not use an administrator account to test that boundary.
Scope configuration is installed when
Simple OAuth and Site Architect are enabled; it is owned by Site Architect and
removed if this module is uninstalled. UI/session callers continue to use Drupal's
normal permission check.

## What the agent gets

On a clean installation with the relevant permissions/scopes, the starter catalog
is extended with two planning tools:

| Owner | MCP name | Purpose |
| --- | --- | --- |
| Agent Access / Tool Belt | `tool_api__entity_list` | List accessible entities. |
| Agent Access / Tool Belt | `tool_api__entity_metadata` | Inspect entity metadata. |
| Site Architect | `tool_api__site_architect_discover` | Search local/ecosystem candidates without inference. |
| Site Architect | `tool_api__site_architect_assess` | Return a scored plan for a feature brief. |

The catalog currently lists these tools even when a token cannot execute them.
Access is checked when the tool is called; discovery is not an access grant.

For example:

> Before building our workshops feature, use Site Architect to assess what this
> site can reuse. We need date, location, capacity, a location-filtered listing
> and a shared layout. Inspect the suggested configuration and explain the
> remaining decisions. Do not change the site yet.

The agent can combine ordinary Drupal inspection with the planning result, then
use separately selected build tools after the user agrees to implementation.
No write tools are introduced by this integration.

## Current scope

Agent Access is an experimental alpha recipe. Its
[upstream limits](https://git.drupalcode.org/project/agent_access/-/blob/1.0.x/SECURITY.md)
still apply, including client-registration, revocation and starter-tool access
limitations. Adding planning tools does not certify the entire transport or
every other tool. Site Architect returns structural site evidence and can make
provider calls, so grant its permission to the intended planning users.

See [the validation record](../VALIDATION.md) for the tested dependency versions
and exact verification scope. Tool schemas and examples are in [tools.md](tools.md).
