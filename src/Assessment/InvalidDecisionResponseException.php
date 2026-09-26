<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

/**
 * Reports exhausted contract recovery without retaining provider responses.
 */
final class InvalidDecisionResponseException extends \UnexpectedValueException {

  /**
   * Constructs the failure with reason counts, never state or answer text.
   */
  public function __construct(public readonly array $violations) {
    parent::__construct('The Decision provider returned an incomplete or inconsistent assessment after a targeted retry. No partial advice was produced.');
  }

}
