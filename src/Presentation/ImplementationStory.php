<?php

declare(strict_types=1);

namespace Drupal\site_architect\Presentation;

/**
 * Explains judged connections without inventing configuration or prose claims.
 */
final class ImplementationStory {

  /**
   * Groups implementation steps by purpose and inspected target record type.
   */
  public static function build(array $requirements): array {
    $steps = [];
    foreach ($requirements['parts'] ?? [] as $part) {
      if ($part['status'] === 'context') {
        continue;
      }
      $kind = match ($part['kind']) {
        'record', 'field' => 'storage',
        'listing' => 'listing',
        'presentation' => 'presentation',
        'capability' => 'capability',
        default => 'check',
      };
      if ($part['kind_judgment']['needs_review'] ?? TRUE) {
        $kind = 'check';
      }
      $target = $part['target'] ?? NULL;
      $key = $kind . ':' . ($target['bundle'] ?? 'unresolved');
      $steps[$key] ??= ['kind' => $kind, 'target' => $target, 'parts' => []];
      $steps[$key]['parts'][] = $part;
    }
    $order = ['storage' => 0, 'listing' => 1, 'presentation' => 2, 'capability' => 3, 'check' => 4];
    uasort($steps, static fn ($a, $b) => $order[$a['kind']] <=> $order[$b['kind']]);
    return array_values($steps);
  }

}
