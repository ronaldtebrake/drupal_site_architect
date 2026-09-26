<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ai_site_advisor\Presentation\PlanHighlights;

/**
 * Registers templates without a procedural .module file.
 */
final class ThemeHooks {

  /**
   * Declares the evidence and assessment templates.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ai_site_advisor_context' => ['variables' => ['snapshot' => [], 'recipes' => []]],
      'ai_site_advisor_result' => ['variables' => ['assessment' => []]],
    ];
  }

  /**
   * Adds UI highlights while preserving the complete service response.
   */
  #[Hook('preprocess_ai_site_advisor_result')]
  public function preprocessResult(array &$variables): void {
    foreach ($variables['assessment']['plan']['areas'] as &$area) {
      $area['highlights'] = PlanHighlights::build($area);
    }
  }

}
