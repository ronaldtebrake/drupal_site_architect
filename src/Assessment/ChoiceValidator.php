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
    $probabilities = $answer->getProbabilities();
    $expected = $question->getOptionKeys();
    if (array_diff(array_keys($probabilities), $expected) || array_diff($expected, array_keys($probabilities)) || abs(array_sum($probabilities) - 1.0) > 0.01 || $answer->getProbability($answer->getChoice()) < max($probabilities)) {
      throw new \UnexpectedValueException('The Decision provider returned an invalid assessment distribution.');
    }
  }

}
