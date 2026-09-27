<?php

declare(strict_types=1);

namespace Drupal\site_architect\Assessment;

/**
 * Keeps weak preferences from overriding independent contribution evidence.
 */
final class OptionRanking {

  /**
   * Confirms a starting point only when both judgments support it.
   */
  public static function primary(array $area): ?array {
    $options = array_column($area['options'], NULL, 'id');
    $option = $options[$area['selection']['id']] ?? NULL;
    if (!$option || $area['selection']['needs_review'] || ($option['contribution']['needs_review'] ?? TRUE)
      || !in_array($option['contribution']['choice'] ?? '', ['foundation', 'complement'], TRUE)) {
      return NULL;
    }
    return isset($option['package']) || isset($option['bundle_id']) ? $option : NULL;
  }

  /**
   * Gives checked requirement matches space ahead of generic useful options.
   */
  public static function forArea(array $area): array {
    $options = array_column($area['options'], NULL, 'id');
    foreach ($options as &$option) {
      $option['requirement_support'] = 0.0;
    }
    unset($option);
    foreach ($area['requirements']['parts'] ?? [] as $part) {
      if (isset($options[$part['option_id'] ?? '']) && in_array($part['status'], ['supported', 'partial'], TRUE)) {
        $probabilities = $part['coverage']['probabilities'];
        $option = &$options[$part['option_id']];
        $option['requirement_support'] = max($option['requirement_support'], $probabilities['direct'] + $probabilities['partial']);
        unset($option);
      }
    }
    return self::sort($options, self::primary($area)['id'] ?? NULL);
  }

  /**
   * Orders useful options by their contribution, pinning only a sound choice.
   */
  public static function sort(array $options, ?string $primary_id = NULL): array {
    uasort($options, static function (array $a, array $b) use ($primary_id): int {
      $a_role = $a['contribution'] ?? [];
      $b_role = $b['contribution'] ?? [];
      $a_useful = in_array($a_role['choice'] ?? '', ['foundation', 'complement'], TRUE);
      $b_useful = in_array($b_role['choice'] ?? '', ['foundation', 'complement'], TRUE);
      return (($b['id'] === $primary_id) <=> ($a['id'] === $primary_id))
        ?: ((($b['requirement_support'] ?? 0) > 0) <=> (($a['requirement_support'] ?? 0) > 0))
        ?: ($b_useful <=> $a_useful)
        ?: (($a_role['needs_review'] ?? TRUE) <=> ($b_role['needs_review'] ?? TRUE))
        ?: (($b_role['probabilities'][$b_role['choice'] ?? ''] ?? 0) <=> ($a_role['probabilities'][$a_role['choice'] ?? ''] ?? 0))
        ?: (($b_role['confidence'] ?? 0) <=> ($a_role['confidence'] ?? 0))
        ?: strcmp($a['id'], $b['id']);
    });
    return $options;
  }

}
