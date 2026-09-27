<?php

declare(strict_types=1);

namespace Drupal\site_architect\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\site_architect\Presentation\PlanHighlights;
use Drupal\site_architect\Presentation\ImplementationStory;

/**
 * Registers templates without a procedural .module file.
 */
final class ThemeHooks {

  /**
   * Declares the assessment template.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'site_architect_result' => ['variables' => ['assessment' => [], 'agent_handoff' => NULL]],
    ];
  }

  /**
   * Adds UI highlights while preserving the complete service response.
   */
  #[Hook('preprocess_site_architect_result')]
  public function preprocessResult(array &$variables): void {
    foreach ($variables['assessment']['plan']['areas'] as &$area) {
      $area['highlights'] = PlanHighlights::build($area);
      $area['implementation_steps'] = ImplementationStory::build($area['requirements'] ?? []);
      $area['has_part_matches'] = (bool) array_filter($area['requirements']['parts'] ?? [], static function ($part) {
        $supported = in_array($part['status'], ['supported', 'partial'], TRUE);
        return !empty($part['fields']) || ($supported && !$part['selection']['needs_review']);
      });
    }
  }

}
