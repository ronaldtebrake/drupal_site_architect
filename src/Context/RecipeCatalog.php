<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Composer\InstalledVersions;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Discovers recipe manifests in core, Composer packages and configured roots.
 */
class RecipeCatalog implements CatalogSourceInterface {

  /**
   * Constructs the local discovery source.
   */
  public function __construct(
    private readonly string $appRoot,
    private readonly ConfigFactoryInterface $config,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isRemote(): bool {
    return FALSE;
  }

  /**
   * Lists real manifests, without instantiating or applying recipes.
   */
  public function collect(): array {
    $recipes = [];
    foreach ($this->manifestPaths() as $path) {
      try {
        $data = Yaml::parseFile($path);
        if (!is_array($data) || empty($data['name'])) {
          throw new \UnexpectedValueException('Missing recipe name.');
        }
        $directory = dirname($path);
        $composer = is_file($directory . '/composer.json') ? json_decode(file_get_contents($directory . '/composer.json'), TRUE, flags: JSON_THROW_ON_ERROR) : [];
        $source = str_starts_with($path, $this->appRoot . '/') ? substr($path, strlen($this->appRoot) + 1) : 'recipe-package/' . basename($directory) . '/recipe.yml';
        $package = $composer['name'] ?? (str_starts_with($path, $this->appRoot . '/core/recipes/') ? 'drupal/core' : 'local/' . basename($directory));
        $recipes[hash('sha256', $path)] = [
          'kind' => 'recipe',
          'label' => strip_tags((string) $data['name']),
          'description' => strip_tags((string) ($data['description'] ?? '')),
          'machine_name' => basename($directory),
          'package' => $package,
          'installs' => $data['install'] ?? [],
          'includes_recipes' => $data['recipes'] ?? [],
          'config_action_targets' => array_keys($data['config']['actions'] ?? []),
          'config_imports' => $data['config']['import'] ?? [],
          'configuration' => $this->configuration($directory, $data),
          'source' => $source,
          'source_hash' => hash_file('sha256', $path),
          'availability' => 'local_code',
          'application_state' => 'unknown',
          'compatibility_verified' => FALSE,
          'url' => NULL,
          'scope' => 'Manifest evidence only. Local files do not prove a recipe was applied or that applying it is compatible with the active configuration.',
        ];
      }
      catch (\Throwable) {
        $recipes[hash('sha256', $path)] = ['invalid_manifest' => basename(dirname($path))];
      }
    }
    return $recipes;
  }

  /**
   * Reads declared config identities and structural metadata, never values.
   */
  private function configuration(string $directory, array $manifest): array {
    $items = [];
    foreach (glob($directory . '/config/*.yml') ?: [] as $path) {
      $name = basename($path, '.yml');
      $data = Yaml::parseFile($path);
      if (!is_array($data)) {
        continue;
      }
      // Exclude defaults, credentials, provider settings and action arguments,
      // or arbitrary config. These keys describe structure in supplied files.
      $metadata = array_intersect_key($data, array_flip([
        'label', 'entity_type', 'bundle', 'field_name', 'field_type', 'required',
      ]));
      $metadata = array_filter($metadata, static fn ($value) => is_scalar($value));
      $items[$name] = $metadata + [
        'name' => $name,
        'operation' => 'provided',
        'source_hash' => hash_file('sha256', $path),
        'active_exists' => !$this->config->get($name)->isNew(),
      ];
    }
    foreach ($manifest['config']['import'] ?? [] as $module => $names) {
      if (!is_array($names)) {
        continue;
      }
      foreach ($names as $name) {
        if (is_string($name)) {
          $items[$name] ??= [
            'name' => $name,
            'operation' => 'import',
            'module' => $module,
            'active_exists' => !$this->config->get($name)->isNew(),
          ];
        }
      }
    }
    foreach (array_keys($manifest['config']['actions'] ?? []) as $name) {
      $items[$name] ??= [
        'name' => $name,
        'operation' => 'action',
        'active_exists' => str_contains($name, '*') ? NULL : !$this->config->get($name)->isNew(),
      ];
    }
    ksort($items);
    return array_values($items);
  }

  /**
   * {@inheritdoc}
   */
  public function search(string $query, int $limit): array {
    $all = $this->collect();
    $warnings = [];
    $items = [];
    $tokens = preg_split('/[^\pL\pN]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
    $tokens = array_values(array_unique(array_filter($tokens, static fn ($token) => mb_strlen($token) >= 3)));
    foreach ($all as $id => $item) {
      if (isset($item['invalid_manifest'])) {
        $warnings[] = 'Could not read recipe manifest: ' . $item['invalid_manifest'];
        continue;
      }
      $title = mb_strtolower($item['label'] . ' ' . $item['machine_name']);
      $text = $title . ' ' . mb_strtolower($item['description'] . ' ' . implode(' ', $item['installs']));
      $score = 0;
      foreach ($tokens as $token) {
        $score += str_contains($title, $token) ? 3 : (str_contains($text, $token) ? 1 : 0);
      }
      if ($score > 0) {
        $item['retrieval_score'] = $score;
        $items[$id] = $item;
      }
    }
    uasort($items, static fn ($a, $b) => $b['retrieval_score'] <=> $a['retrieval_score'] ?: strcmp($a['label'], $b['label']));
    return [
      'items' => array_values(array_slice($items, 0, $limit)),
      'sources' => [[
        'id' => 'local_recipes',
        'label' => 'Local recipe manifests',
        'discovered' => count($all),
        'matches' => count($items),
        'truncated' => count($items) > $limit,
      ],
      ],
      'warnings' => $warnings,
    ];
  }

  /**
   * Finds files without requiring a fixed list of recipe names.
   */
  private function manifestPaths(): array {
    $root = InstalledVersions::getRootPackage()['install_path'];
    $roots = [$this->appRoot . '/core/recipes', $root . '/recipes', $this->appRoot . '/recipes'];
    foreach ($this->config->get('ai_site_advisor.settings')->get('recipe_directories') ?? [] as $directory) {
      $roots[] = str_starts_with($directory, '/') ? $directory : $root . '/' . $directory;
    }
    $paths = [];
    foreach (array_unique($roots) as $directory) {
      if (!is_dir($directory)) {
        continue;
      }
      foreach (Finder::create()->files()->in($directory)->depth('< 3')->followLinks()->name('recipe.yml') as $file) {
        $paths[] = $file->getRealPath();
      }
    }
    foreach (InstalledVersions::getInstalledPackagesByType('drupal-recipe') as $package) {
      $path = InstalledVersions::getInstallPath($package) . '/recipe.yml';
      if (is_file($path)) {
        $paths[] = realpath($path);
      }
    }
    $paths = array_values(array_unique(array_filter($paths)));
    sort($paths);
    return $paths;
  }

}
