<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ai_site_advisor\Presentation\PlanHighlights;
use Drupal\ai_site_advisor\Presentation\ImplementationStory;

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
      'ai_site_advisor_result' => ['variables' => ['assessment' => [], 'agent_handoff' => NULL]],
    ];
  }

  /**
   * Adds UI highlights while preserving the complete service response.
   */
  #[Hook('preprocess_ai_site_advisor_result')]
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
