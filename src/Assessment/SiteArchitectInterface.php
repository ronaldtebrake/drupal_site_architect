<?php

declare(strict_types=1);

namespace Drupal\site_architect\Assessment;

use Drupal\Core\Session\AccountInterface;

/**
 * Public read-only assessment API shared by forms and tools.
 */
interface SiteArchitectInterface {

  /**
   * Inspects the site, plans discovery and assesses the brief; never builds.
   *
   * An optional catalog query requests search instead of planning it.
   */
  public function assess(string $brief, AccountInterface $account, string $catalog_query = ''): array;

}
