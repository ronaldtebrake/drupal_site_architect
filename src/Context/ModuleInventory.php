<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Discovers core capabilities and enabled extensions from their own metadata.
 */
final class ModuleInventory {

  public function __construct(
    private readonly ModuleExtensionList $extensions,
    private readonly ModuleHandlerInterface $modules,
  ) {}

  /**
   * Reads visible shipped core modules and enabled contributed/custom modules.
   */
  public function collect(AccountInterface $account, array $areas): array {
    $items = [];
    foreach ($this->extensions->getList() as $name => $extension) {
      $info = $extension->info;
      $core = str_starts_with($extension->getPath(), 'core/modules/') && !str_contains($extension->getPath(), '/tests/');
      $enabled = $this->modules->moduleExists($name);
      if (!empty($info['hidden']) || (!$core && !$enabled) || ($info['package'] ?? '') === 'Testing') {
        continue;
      }
      $links = [];
      if ($enabled && !empty($info['configure'])) {
        try {
          $url = Url::fromRoute($info['configure'], $info['configure_parameters'] ?? []);
          if ($url->access($account)) {
            $links[$url->toString()] = ['label' => 'Configure ' . $info['name'], 'url' => $url->toString()];
          }
        }
        catch (\Exception) {
          // A declared route can depend on optional UI modules.
        }
      }
      foreach ($areas as $area) {
        if ($area['provider'] === $name) {
          foreach ($area['links'] as $link) {
            $links[$link['url']] = $link;
          }
        }
      }
      $items['module__' . $name] = [
        'id' => 'module__' . $name,
        'machine_name' => $name,
        'module_name' => $name,
        'label' => (string) $info['name'],
        'description' => strip_tags((string) ($info['description'] ?? '')),
        'kind' => 'module',
        'core' => $core,
        'package' => $core ? 'drupal/core' : (!empty($info['project']) ? 'drupal/' . $info['project'] : 'local/' . $name),
        'availability' => $enabled ? 'enabled_module' : 'local_code',
        'dependencies' => array_values($info['dependencies'] ?? []),
        'links' => array_values($links),
        'source' => $extension->getPathname(),
        'scope' => 'Local module metadata. Code availability and enabled state are known; configuration coverage and integrations still need inspection.',
      ];
    }
    ksort($items);
    return $items;
  }

  /**
   * Compact semantic evidence; routes and dependencies stay in the result.
   */
  public static function descriptions(array $modules): array {
    return array_map(static fn ($module) => array_intersect_key($module, array_flip([
      'label', 'description', 'module_name', 'core', 'availability',
    ])), $modules);
  }

}
