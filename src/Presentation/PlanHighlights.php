<?php

declare(strict_types=1);

namespace Drupal\site_architect\Presentation;

use Drupal\site_architect\Assessment\OptionRanking;

/**
 * Highlights the model's choice without changing the assessment or its scores.
 */
final class PlanHighlights {

  /**
   * Keeps at most three distinct options above the complete comparison.
   */
  public static function build(array $area): array {
    $options = OptionRanking::forArea($area);
    $primary = OptionRanking::primary($area);
    $state = $primary ? 'recommended' : 'open';
    $ranked = array_flip(array_keys($options));
    $resources = [];
    foreach ($area['handoff']['resources'] as $resource) {
      $option = $options[$resource['option_id']];
      $role = $option['contribution'];
      $resources[] = $resource + [
        'selected' => $option['id'] === ($primary['id'] ?? NULL),
        'role_probability' => $role['probabilities'][$role['choice']],
        'role_confidence' => $role['confidence'],
        'parts' => array_values(array_filter($area['requirements']['parts'] ?? [], static function ($part) use ($option) {
          return $part['option_id'] === $option['id'] && in_array($part['status'], ['supported', 'partial'], TRUE);
        })),
      ];
    }
    usort($resources, static fn ($a, $b) => $ranked[$a['option_id']] <=> $ranked[$b['option_id']]);
    // A content type (or a conflicting choice absent from resources) already
    // occupies one of the three places in the recommendation banner.
    $primary_in_resources = (bool) array_filter($resources, static fn ($resource) => $resource['selected']);
    $limit = $primary && !$primary_in_resources ? 2 : 3;
    return [
      'state' => $state,
      'primary' => $primary,
      'resources' => array_slice($resources, 0, $limit),
      'more_resources' => max(0, count($resources) - $limit),
    ];
  }

}
