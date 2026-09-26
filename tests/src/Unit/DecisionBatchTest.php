<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_decision\Value\ChoiceQuestion;
use Drupal\ai_site_advisor\Assessment\DecisionBatch;
use Drupal\ai_site_advisor\Assessment\ChoiceValidator;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Validates failure and accounting boundaries across multiple provider calls.
 */
#[Group('ai_site_advisor')]
final class DecisionBatchTest extends UnitTestCase {

  /**
   * Builds two requests with distinct questions over shared evidence.
   */
  private function inputs(): array {
    $question = new ChoiceQuestion('Is the evidence enough?', ['yes' => 'Enough', 'no' => 'Missing']);
    return DecisionBatch::split(new DecisionInput('evidence', ['first' => $question, 'last' => $question]), 1);
  }

  /**
   * A late malformed response rejects the entire combined result.
   */
  public function testLaterMissingAnswerRejectsResult(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->exactly(2))->method('decide')->willReturnOnConsecutiveCalls(
      new DecisionResponse(['first' => new ChoiceAnswer('yes', ['yes' => 1.0, 'no' => 0.0], 1.0)]),
      new DecisionResponse([]),
    );
    $this->expectException(\UnexpectedValueException::class);
    DecisionBatch::run($client, $this->inputs());
  }

  /**
   * Missing usage in any request makes the corresponding total unknown.
   */
  public function testUnknownUsageIsNotZero(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $answer = new ChoiceAnswer('yes', ['yes' => 1.0, 'no' => 0.0], 1.0);
    $client->method('decide')->willReturnOnConsecutiveCalls(
      new DecisionResponse(['first' => $answer], 'fixture', new TokenUsageDto(10, 2, 12)),
      new DecisionResponse(['last' => $answer], 'fixture'),
    );
    $result = DecisionBatch::run($client, $this->inputs());
    $this->assertNull($result['response']->toArray()['usage']['input']);
    $this->assertSame(10, $result['requests'][0]['usage']['input']);
    $this->assertNull($result['requests'][1]['usage']['input']);
  }

  /**
   * Provider rounding at exactly one percent is not a malformed distribution.
   */
  public function testProbabilityRoundingBoundary(): void {
    $question = new ChoiceQuestion('Pick the supported option.', ['yes' => 'Enough', 'no' => 'Missing']);
    ChoiceValidator::validate(new ChoiceAnswer('yes', ['yes' => 0.7, 'no' => 0.29], 0.5), $question);
    ChoiceValidator::validate(new ChoiceAnswer('yes', ['yes' => 0.7, 'no' => 0.31], 0.5), $question);
    $this->expectException(\UnexpectedValueException::class);
    ChoiceValidator::validate(new ChoiceAnswer('yes', ['yes' => 0.7, 'no' => 0.28], 0.5), $question);
  }

}
