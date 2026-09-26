<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Presentation;

/**
 * Highlights the model's choice without changing the assessment or its scores.
 */
final class PlanHighlights {

  /**
   * Keeps at most three distinct options above the complete comparison.
   */
  public static function build(array $area): array {
    $options = array_column($area['options'], NULL, 'id');
    $primary = $options[$area['selection']['id']] ?? NULL;
    $specific = isset($primary['package']) || isset($primary['bundle_id']);
    $state = 'open';
    if ($specific) {
      $supported = in_array($primary['contribution']['choice'] ?? '', ['foundation', 'complement'], TRUE);
      $review = $area['selection']['needs_review'] || ($primary['contribution']['needs_review'] ?? TRUE);
      $state = !$supported ? 'conflicting' : ($review ? 'provisional' : 'recommended');
    }
    $resources = [];
    foreach ($area['handoff']['resources'] as $resource) {
      $option = $options[$resource['option_id']];
      $role = $option['contribution'];
      $resources[] = $resource + [
        'selected' => $option['id'] === ($primary['id'] ?? NULL),
        'role_probability' => $role['probabilities'][$role['choice']],
        'role_confidence' => $role['confidence'],
      ];
    }
    // The chosen starting point leads. Rank remaining resources by their
    // independent contribution, not their chance of being the starting point.
    usort($resources, static fn ($a, $b) => ($b['selected'] <=> $a['selected'])
      ?: ($a['needs_review'] <=> $b['needs_review'])
      ?: ($b['role_probability'] <=> $a['role_probability'])
      ?: ($b['role_confidence'] <=> $a['role_confidence'])
      ?: strcmp($a['option_id'], $b['option_id']));
    // A content type (or a conflicting choice absent from resources) already
    // occupies one of the three places in the recommendation banner.
    $primary_in_resources = (bool) array_filter($resources, static fn ($resource) => $resource['selected']);
    $limit = $specific && !$primary_in_resources ? 2 : 3;
    return [
      'state' => $state,
      'primary' => $specific ? $primary : NULL,
      'resources' => array_slice($resources, 0, $limit),
      'more_resources' => max(0, count($resources) - $limit),
    ];
  }

}
