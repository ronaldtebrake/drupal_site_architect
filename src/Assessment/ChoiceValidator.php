<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_decision\Value\ChoiceQuestion;

/**
 * Enforces the same response contract for routing and final assessments.
 */
final class ChoiceValidator {

  /**
   * Rejects incomplete distributions and inconsistent selected options.
   */
  public static function validate(ChoiceAnswer $answer, ChoiceQuestion $question): void {
    if (self::violation($answer, $question) !== NULL) {
      throw new \UnexpectedValueException('The Decision provider returned an invalid assessment distribution.');
    }
  }

  /**
   * Returns a diagnostic code without provider content, or NULL when valid.
   */
  public static function violation(ChoiceAnswer $answer, ChoiceQuestion $question): ?string {
    $probabilities = $answer->getProbabilities();
    $expected = $question->getOptionKeys();
    // Preserve the 1% rounding tolerance at its floating-point boundary.
    $sum_error = round(abs(array_sum($probabilities) - 1.0), 8);
    if (array_diff(array_keys($probabilities), $expected) || array_diff($expected, array_keys($probabilities))) {
      return 'option_mismatch';
    }
    if ($sum_error > 0.01) {
      return 'distribution_sum';
    }
    if ($answer->getProbability($answer->getChoice()) < max($probabilities)) {
      return 'choice_not_highest';
    }
    return NULL;
  }

}
