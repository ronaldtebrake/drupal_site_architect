<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Symfony\Component\Yaml\Yaml;

/**
 * A bounded catalog read from real recipes shipped with Drupal core.
 */
class RecipeCatalog {

  /**
   * Constructs the catalog.
   */
  public function __construct(private readonly string $appRoot) {}

  /**
   * Returns evidence for four known recipes, without applying any recipe.
   */
  public function collect(): array {
    $recipes = [];
    foreach (['article_content_type', 'page_content_type', 'editorial_workflow', 'content_search'] as $id) {
      $relative = 'core/recipes/' . $id . '/recipe.yml';
      $file = $this->appRoot . '/' . $relative;
      if (!is_file($file)) {
        continue;
      }
      $data = Yaml::parseFile($file);
      $recipes[$id] = [
        'label' => strip_tags((string) ($data['name'] ?? $id)),
        'description' => strip_tags((string) ($data['description'] ?? '')),
        'installs' => $data['install'] ?? [],
        'source' => $relative,
        'source_hash' => hash_file('sha256', $file),
        'scope' => 'Suitability from recipe description and module declarations only; application compatibility and configuration effects are not validated.',
      ];
    }
    return $recipes;
  }

}
