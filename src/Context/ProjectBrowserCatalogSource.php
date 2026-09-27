<?php

declare(strict_types=1);

namespace Drupal\site_architect\Context;

use Composer\InstalledVersions;
use Drupal\Core\Extension\ModuleHandlerInterface;
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
    private readonly CatalogPageCache $pages,
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
      $report_index = count($reports);
      try {
        $fetch_limit = max(24, $limit);
        $cached = $this->pages->query($source, [
          'search' => $query,
          'page' => 0,
          'limit' => $fetch_limit,
          'categories' => '',
        ]);
        $page = $cached['page'];
        $reports[] = [
          'id' => $id,
          'label' => $page->pluginLabel,
          'matches' => $page->totalResults,
          'truncated' => $page->totalResults > min($limit, count($page->list)),
          'error' => $page->error ? 'The source reported an error.' : NULL,
          'freshness' => 'Catalogue pages are reused for up to five minutes. Upstream sources may also cache results; their fetch age may be unknown. Local installation/enabled state is inspected again.',
          'cache' => [
            'hit' => $cached['hit'],
            'stored_at' => gmdate(DATE_ATOM, $cached['stored_at']),
            'max_age' => $cached['max_age'],
          ],
        ];
        if ($page->error) {
          $warnings[] = 'Project Browser source ' . $id . ' reported an error; results may be incomplete.';
        }
        // Prefer matches in names to incidental mentions in long descriptions.
        // No package-specific weights: this works for every configured source.
        $projects = array_slice($page->list, 0, $fetch_limit);
        $rank = static function ($project) use ($query): int {
          $name = mb_strtolower(str_replace(['_', '-'], ' ', $project->machineName));
          $title = mb_strtolower((string) $project->title);
          $term = mb_strtolower($query);
          $score = $name === $term || $title === $term ? 100 : 0;
          foreach (explode(' ', $term) as $word) {
            $score += str_contains($name . ' ' . $title, $word) ? 10 : 0;
          }
          return $score;
        };
        usort($projects, static fn ($a, $b) => $rank($b) <=> $rank($a));
        foreach (array_slice($projects, 0, $limit) as $project) {
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
        $reports[$report_index] = array_replace($reports[$report_index] ?? [
          'id' => $id,
          'label' => (string) ($source->getPluginDefinition()['label'] ?? $id),
          'matches' => NULL,
          'truncated' => FALSE,
        ], ['error' => 'The source could not be queried or its results could not be read.']);
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
