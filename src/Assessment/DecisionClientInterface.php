<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;

/**
 * Isolates inference from evidence collection and recommendation policy.
 */
interface DecisionClientInterface {

  /**
   * Executes a batch through the configured Drupal AI Decision provider.
   */
  public function decide(DecisionInput $input): DecisionResponse;

}
