<?php

declare(strict_types=1);

namespace Drupal\site_architect\Assessment;

use Symfony\Component\String\Inflector\EnglishInflector;

/**
 * Supplies bounded source phrases, without a catalog of Drupal solutions.
 */
final class BriefCapabilities {

  public const MAX_BRIEF_LENGTH = 20000;

  public const MAX_SEGMENTS = 200;

  /**
   * Splits prose into clauses and supplies adjacent source-word candidates.
   */
  public static function clauses(string $brief): array {
    $text = preg_replace('~(?:https?://|www\.)\S+|\S*[@/\\\\\d]\S*~iu', ' ', $brief);
    $clauses = [];
    foreach (preg_split('/\R\s*\R/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $paragraph) {
      // A named section expresses a work area plus its constraints. Keeping it
      // together avoids searching separately for every detail in that area.
      if (preg_match('/^\s*[\pL][\pL\h-]{1,70}:\h+\S/u', $paragraph)) {
        $clauses[] = $paragraph;
      }
      else {
        $clauses = array_merge($clauses, preg_split('/[,;.\n]+|\b(?:and|with|plus)\b/iu', $paragraph, -1, PREG_SPLIT_NO_EMPTY));
      }
    }
    $result = [];
    foreach ($clauses as $clause) {
      $words = preg_split('/[^\pL]+/u', mb_strtolower(trim($clause)), -1, PREG_SPLIT_NO_EMPTY);
      // Overlap by one word so adjacent phrases survive a window boundary.
      // At most 192 options including none, below Choice's 255-option limit.
      for ($offset = 0; $offset < count($words); $offset += 95) {
        $window = array_slice($words, $offset, 96);
        $terms = [];
        foreach ($window as $index => $word) {
          if (mb_strlen($word) < 3 || mb_strlen($word) > 40) {
            continue;
          }
          $terms[] = $word;
          if (isset($window[$index + 1]) && mb_strlen($window[$index + 1]) >= 3 && mb_strlen($window[$index + 1]) <= 40) {
            $terms[] = $word . ' ' . $window[$index + 1];
          }
        }
        if ($terms) {
          $result[] = [
            'text' => implode(' ', $window),
            'source_text' => trim($clause),
            'terms' => array_values(array_unique($terms)),
            'truncated' => FALSE,
          ];
        }
        if ($offset + 96 >= count($words)) {
          break;
        }
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
    // Preserve collective source nouns such as media and data. Mechanical
    // conversion to medium or datum changes the intended catalog search.
    $words[] = str_ends_with($last, 's') ? ((new EnglishInflector())->singularize($last)[0] ?? $last) : $last;
    return implode(' ', $words);
  }

}
