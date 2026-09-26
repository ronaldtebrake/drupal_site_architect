<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor_project_browser;

use Composer\InstalledVersions;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\ai_site_advisor\Context\CatalogSourceInterface;
use Drupal\project_browser\Plugin\ProjectBrowserSourceManager;
use Drupal\project_browser\ProjectType;

/**
 * Uses Project Browser's public plugin API, including API Browser sources.
 */
final class ProjectBrowserCatalogSource implements CatalogSourceInterface {

  /**
   * Constructs the adapter.
   */
  public function __construct(
    private readonly ProjectBrowserSourceManager $manager,
    private readonly ModuleHandlerInterface $modules,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isRemote(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function search(string $query, int $limit): array {
    $groups = [];
    $reports = [];
    $warnings = [];
    foreach ($this->manager->getAllEnabledSources() as $id => $source) {
      // Main-module discovery already reads the full local manifests.
      if ($id === 'recipes') {
        continue;
      }
      try {
        $page = $source->getProjects(['search' => $query, 'page' => 0, 'limit' => $limit, 'categories' => '']);
        $reports[] = [
          'id' => $id,
          'label' => $page->pluginLabel,
          'matches' => $page->totalResults,
          'truncated' => $page->totalResults > min($limit, count($page->list)),
          'error' => $page->error ? 'The source reported an error.' : NULL,
          'freshness' => 'Source-managed caching; upstream fetch age may be unknown.',
        ];
        if ($page->error) {
          $warnings[] = 'Project Browser source ' . $id . ' reported an error; results may be incomplete.';
        }
        foreach (array_slice($page->list, 0, $limit) as $project) {
          if (!in_array($project->type, [ProjectType::Recipe, ProjectType::Module], TRUE)) {
            continue;
          }
          $data = $project->toArray();
          $body = (string) ($data['body']['value'] ?? $data['body']['summary'] ?? '');
          $present = InstalledVersions::isInstalled($project->packageName);
          $enabled = $project->type === ProjectType::Module && $this->modules->moduleExists($project->machineName);
          $url = $data['url'];
          if (!in_array(parse_url($url ?? '', PHP_URL_SCHEME), ['http', 'https'], TRUE)) {
            $url = NULL;
          }
          $groups[$id][] = [
            'kind' => $project->type->value,
            'label' => (string) $project->title,
            'description' => mb_substr(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 2500),
            'machine_name' => $project->machineName,
            'package' => $project->packageName,
            'source' => 'project_browser:' . $id,
            'source_project_id' => $project->id,
            'url' => $url,
            'availability' => $enabled ? 'enabled_module' : ($present ? 'local_code' : 'catalog_only'),
            'application_state' => 'unknown',
            'compatibility_verified' => FALSE,
            'source_claims' => [
              'compatible' => $project->isCompatible,
              'maintained' => $project->isMaintained,
              'security_coverage' => $project->isCovered,
            ],
            'scope' => 'Project Browser catalog metadata. Package dependencies, recipe actions, conflicts and application history have not been inspected. Source claims may be fixed mapping values.',
          ];
        }
      }
      catch (\Throwable) {
        $warnings[] = 'Project Browser source ' . $id . ' could not be queried. Its absence is not evidence that no solution exists.';
      }
    }
    // Alternate source results so contributed modules do not hide recipes.
    $items = [];
    for ($index = 0; $index < $limit; $index++) {
      foreach ($groups as $group) {
        if (isset($group[$index])) {
          $items[] = $group[$index];
        }
      }
    }
    if (!$reports) {
      $warnings[] = 'No additional Project Browser sources are enabled. Enable a recipe ecosystem source in Project Browser settings.';
    }
    return ['items' => $items, 'sources' => $reports, 'warnings' => $warnings];
  }

}
