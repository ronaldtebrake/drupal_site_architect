<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * Supplies bounded source phrases, without a catalog of Drupal solutions.
 */
final class BriefCapabilities {

  /**
   * Splits prose into clauses and supplies adjacent source-word candidates.
   */
  public static function clauses(string $brief): array {
    $text = preg_replace('~(?:https?://|www\.)\S+|\S*[@/\\\\\d]\S*~iu', ' ', $brief);
    $clauses = preg_split('/[,;.\n]+|\b(?:and|with|plus)\b/iu', $text, -1, PREG_SPLIT_NO_EMPTY);
    $result = [];
    foreach ($clauses as $clause) {
      $words = preg_split('/[^\pL]+/u', mb_strtolower(trim($clause)), -1, PREG_SPLIT_NO_EMPTY);
      $terms = [];
      foreach (array_slice($words, 0, 32) as $index => $word) {
        if (mb_strlen($word) < 3 || mb_strlen($word) > 40) {
          continue;
        }
        $terms[] = $word;
        if (isset($words[$index + 1]) && mb_strlen($words[$index + 1]) >= 3 && mb_strlen($words[$index + 1]) <= 40) {
          $terms[] = $word . ' ' . $words[$index + 1];
        }
      }
      if ($terms) {
        $result[] = [
          'text' => trim($clause),
          'terms' => array_values(array_unique($terms)),
          'truncated' => count($words) > 32,
        ];
      }
    }
    return $result;
  }

  /**
   * Normalizes the final English noun without mapping capabilities to modules.
   */
  public static function query(string $phrase): string {
    $words = explode(' ', $phrase);
    $last = array_pop($words);
    $words[] = (new EnglishInflector())->singularize($last)[0] ?? $last;
    return implode(' ', $words);
  }

}
