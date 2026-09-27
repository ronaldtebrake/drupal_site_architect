<?php

declare(strict_types=1);

namespace Drupal\site_architect\Integration;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Installs the module-owned planning scope without changing existing policy.
 */
final class PlanningScopeInstaller {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Adds a missing scope once both modules are installed.
   */
  public function ensureScope(): void {
    if (!$this->moduleHandler->moduleExists('simple_oauth')) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage('oauth2_scope');
    if ($storage->load('drupal_site_architect_plan')) {
      // Preserve administrator changes, including deliberately disabled scopes.
      return;
    }
    $source = new FileStorage(dirname(__DIR__, 2) . '/config/optional');
    $data = $source->read('simple_oauth.oauth2_scope.drupal_site_architect_plan');
    if ($data === FALSE) {
      throw new \RuntimeException('The Site Architect planning scope definition is missing.');
    }
    $storage->create($data)->save();
  }

}
