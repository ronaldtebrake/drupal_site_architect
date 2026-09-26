<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Hook;

use Drupal\Core\Hook\Attribute\Hook;

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

}
