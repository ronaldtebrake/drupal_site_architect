<?php

declare(strict_types=1);

namespace Drupal\site_architect\Assessment;

/**
 * Maps source requirements to separately checked building blocks.
 */
interface RequirementPlannerInterface {

  /**
   * Returns per-area parts and complete inference diagnostics, without writes.
   */
  public function plan(string $brief, array $site, array $plan): array;

}
