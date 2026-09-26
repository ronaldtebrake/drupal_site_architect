<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Drupal\Core\Session\AccountInterface;

/**
 * Collects an allowlisted, current snapshot for a permitted caller.
 */
interface SiteContextCollectorInterface {

  /**
   * Returns facts, never credentials, content values or arbitrary config.
   */
  public function collect(AccountInterface $account): array;

}
