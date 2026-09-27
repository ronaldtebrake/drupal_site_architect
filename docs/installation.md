# Installation and configuration

[Back to README](../README.md)

Site Architect is **one module with the complete planning stack included**.
Requiring `drupal/site_architect` downloads Agent Access, Drupal AI, AI Decision, the TypeSafe
AI provider for Jev, Project Browser, API Browser, Tool API, MCP Server and its
Tool API bridge. Composer resolves their dependencies too, including Key.
You do not need separate `composer require` commands for those modules.

You need an existing **Drupal 11 site, version 11.4+**, on **PHP 8.3+**, managed with Composer,
and a TypeSafe API key to run Jev assessments. The site needs outbound HTTPS
access to the provider and the catalog sources you enable. Canvas, ECA and
WebMCP are not required. No demo content type is installed automatically.

### 1. Download the module and its dependencies

Run these commands from the directory containing your site's `composer.json`.
The standard Drupal Composer repository, `https://packages.drupal.org/8`, must
already be configured, as it is in `drupal/recommended-project`.

```sh
composer config repositories.site_architect vcs https://github.com/ronaldtebrake/drupal_site_architect.git
composer config repositories.ai_decision vcs https://git.drupalcode.org/project/ai_decision.git
composer config repositories.ai_provider_typesafeai vcs https://git.drupalcode.org/project/ai_provider_typesafeai.git
composer config minimum-stability dev
composer config prefer-stable true
composer require drupal/site_architect:dev-main --with-all-dependencies
```

The GitHub repository setting is needed because this project is currently
distributed directly from GitHub. Its Composer package name is
`drupal/site_architect`, even though the repository is named
`drupal_site_architect`. A bare `composer require drupal/site_architect` cannot
discover an unregistered GitHub package on its own.

The AI Decision and TypeSafe repository settings are temporary upstream packaging workarounds:
Drupal's package index currently maps `drupal/ai_decision` to an old metapackage
which does not provide the standalone module files. The override fetches the
actual AI Decision module. The matching provider override prevents its stale
package metadata from pulling in a second copy under the old submodule package
name. These overrides can be removed once both index entries are corrected.
See the [upstream packaging note](https://git.drupalcode.org/project/ai_provider_typesafeai/-/blob/1.0.x/README.md).

Some required dependencies currently have only development or prerelease
versions. `minimum-stability` and `prefer-stable` are **site-wide Composer
settings**: they allow those versions while preferring stable releases where
available. Skip those two commands if your project already uses these settings.
If your project must keep a stable minimum, explicitly allow the required
prerelease packages in its root `composer.json` instead. A module cannot set
that policy for its host project; see [Composer's stability rules](https://getcomposer.org/doc/04-schema.md#minimum-stability).
Commit the resulting site `composer.json` and `composer.lock` for repeatable installs.

Agent Access supplies the MCP/OAuth connection dependencies. The tested base is
Agent Access `1.0.0-alpha2`, MCP Server `2.0.0-beta5` and Tool Bridge
`1.0.0-beta3`. The previous exact beta2/beta1 pins have been removed. Site Architect
still declares direct dependencies on the Tool API, bridge, AI and catalog APIs
its code/configuration uses; a recipe does not replace those API contracts.

### 2. Apply Agent Access and enable Site Architect

```sh
vendor/bin/drush --uri=https://your-site.example recipe ../recipes/agent_access
vendor/bin/drush en site_architect -y
vendor/bin/drush cr
```

Replace the example URL with the site's reachable HTTPS address. On a standard
`drupal/recommended-project` layout, the recipe is installed at
`recipes/agent_access` beside the site's `composer.json`. Drush resolves the
recipe argument relative to Drupal's document root, hence `../recipes/agent_access`.
Adjust the path if the site's Composer installer paths differ. Composer downloads
the recipe; it does not apply it or configure OAuth credentials automatically.

Agent Access installs its connection modules and two starter tools. It does not
create accounts, grant role permissions or generate OAuth keys. The module can
still be enabled for the Drupal form before configuring an external connection;
finish [the OAuth setup](agent-access.md) for agent access.

Alternatively, open **Extend** (`/admin/modules`), select **Drupal Site Architect**
and install it with its required dependencies. Drupal enables the required
modules together. Composer downloads code; enabling the module installs Drupal
configuration and registers the services and tools.

There are no integration submodules to select. Site Architect registers both
Tool API plugins and these two additional enabled MCP mappings:

- `tool_api__site_architect_assess`
- `tool_api__site_architect_discover`

### 3. Configure Jev

1. Get your API key from [TypeSafe](https://typesafe.ai).
2. At **Configuration → System → Keys** (`/admin/config/system/keys`), create a
   Key holding that credential. Use the site's normal secret-storage approach;
   do not put the secret in version-controlled configuration.
3. Open `/admin/config/ai/providers/typesafeai`, select that Key under
   **TypeSafe API Key**, and save. The provider verifies the connection.
4. Open `/admin/config/ai/settings`. For the **Decision** operation, select
   **TypeSafe AI** and **Jev** (`jev-latest`), then save.

The planning service uses Drupal AI's configured default Decision provider.
The package includes TypeSafe for the Jev setup above; no key or provider
selection is shipped with the module. A chat/completion model selected elsewhere
in Drupal AI does not configure the Decision operation.

### 4. Enable ecosystem catalogs

Open `/admin/config/development/project_browser`:

- Enable the **Drupal.org** module source (`drupalorg_jsonapi`). It is enabled
  by default on a fresh Project Browser installation.
- Enable **Packagist Drupal Recipes** (`api_browser_project:packagist_recipes`).
  API Browser supplies this catalog configuration automatically, but the source
  must be selected in Project Browser before it is searched.
- Keep any other sources your site uses enabled, then save.

Site Architect respects this source selection. It does not overwrite an existing
site's catalog settings. The local **Recipes** source is not a substitute for
the Packagist ecosystem catalog: local recipe files are already inspected
directly by Site Architect. Project Browser's package-installation UI can remain
disabled; discovery does not require installation permissions.

### 5. Set access and site policy

At `/admin/people/permissions`, grant **Use Drupal Site Architect**
(`access site architect`) to the roles that should inspect site structure,
query catalogs and run assessments. Grant **Administer Drupal Site Architect**
(`administer site architect`) only to roles that should change planning settings.

At `/admin/config/ai/site-architect`, review the planning policy, content-type
scope and any additional local recipe directories. Empty content-type scope
includes all supported content types. Core recipes and installed Composer recipe
packages are discovered automatically.

### 6. Create a plan

Open **Structure → Drupal Site Architect** (`/admin/structure/site-architect`).
Describe what you want to build or extend, then select **Propose a plan**.
Review the inspected site structures, scored options and remaining questions.
**Copy plan for an agent** copies the current draft and its evidence pointers.

The assessment performs discovery and planning only. It does not install
recommended packages, apply recipes or change content or configuration.

### 7. Connect an agent over MCP

Use your site's MCP endpoint, normally `https://your-site.example/mcp`.
The agent's Drupal account needs both `access mcp server` and
`access site architect`. Check the two Site Architect mappings at
`/admin/config/services/mcp-server/tools`.

Agent Access supplies OAuth discovery and the authenticated connection. Finish
[the connection setup](agent-access.md), including keys, account permissions and
the additional `drupal:site-architect:plan` scope. The Jev API key remains on the
Drupal site; it is not an MCP login credential.

Once authenticated, `tools/list` should include both mappings. Call
`tool_api__site_architect_discover` with `{"query":"workflow"}` to verify
discovery without inference. Call `tool_api__site_architect_assess` with
`{"brief":"We need an editorial review workflow for our existing news content."}`
to get a compact scored plan. [Tool contracts and examples](tools.md) describe
the response and the full-evidence option.

### Optional Workshop example

To try a known content structure on a development site, apply the bundled recipe:

```sh
vendor/bin/drush recipe modules/contrib/site_architect/recipes/workshop
```

The recipe path is relative to Drupal's document root. It creates a normal
**Workshop** content type with date, location, capacity and
description fields; it creates no content records. The existing
`advisor_workshop` bundle ID is retained for compatibility with earlier demos.
You can inspect its normal form at `/node/add/advisor_workshop` and then try the
**Recurring workshops** brief. This recipe is independent of installing the
planning product, and its configuration survives uninstalling Site Architect.

### Existing prototype installations

Fresh installs use only `site_architect`. Older installations using
`ai_site_advisor` or the former `site_architect_*` integration modules need their
module registrations and configuration dependencies migrated before replacing
the code. Uninstalling the old demo or MCP modules can remove their owned
configuration. The local prototype was migrated with content and mapping settings
preserved; see [VALIDATION.md](../VALIDATION.md).

For an existing consolidated `site_architect` installation, update with Composer
and run `vendor/bin/drush updatedb -y` and `vendor/bin/drush cr`. The post-update
adds the planning scope if Simple OAuth is already enabled, preserving any
existing scope configuration. If OAuth is installed later normally, Drupal
imports the optional planning scope. If Agent Access installs it through a
recipe, Site Architect registers its missing scope after the recipe completes.
This also fixes upgrades where an earlier update ran before OAuth was enabled.
Updating code does not apply Agent Access or grant account permissions.
