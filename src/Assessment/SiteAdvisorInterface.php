<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Drupal\Core\Session\AccountInterface;

/**
 * Public read-only assessment API shared by forms and tools.
 */
interface SiteAdvisorInterface {

  /**
   * Inspects the current site and assesses the brief; never builds content.
   */
  public function assess(string $brief, AccountInterface $account): array;

}
