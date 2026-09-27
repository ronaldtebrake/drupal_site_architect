# Attribution and license

[Back to README](../README.md)

Original integration code, GPL-2.0-or-later. It consumes Drupal core, Drupal AI,
AI Decision, the TypeSafe provider,
[Agent Access](https://www.drupal.org/project/agent_access),
[Tool API](https://www.drupal.org/project/tool),
[Project Browser](https://www.drupal.org/project/project_browser),
[API Browser](https://www.drupal.org/project/api_browser),
[MCP Server](https://www.drupal.org/project/mcp_server) and
[MCP Server Tool Bridge](https://www.drupal.org/project/mcp_server_tool_bridge)
through their APIs and configuration.

No upstream module was forked or vendored for this work. Recipe manifests are
read from the host installation; API Browser supplies the Packagist source
configuration. Symfony String supplies the English inflector. This repository
also supplies the optional Workshop example recipe.
Please retain these upstream attributions when contributing or adapting it.

The OAuth connection setup is provided by Agent Access, maintained by Scott
Falconer and contributors. The Site Architect planning scope follows its
permission-based scope pattern, with a separate permission and scope owned by
this module. Upstream connection instructions remain the source for OAuth setup.
