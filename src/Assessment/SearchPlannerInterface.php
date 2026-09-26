<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

/**
 * Selects whether and what to search from a brief and inspected site evidence.
 */
interface SearchPlannerInterface {

  /**
   * Returns a bounded plan without querying any ecosystem source.
   */
  public function plan(string $brief, array $site): array;

}
