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
use Drupal\ai_site_advisor\Assessment\InvalidDecisionResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
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
    $client->expects($this->exactly(3))->method('decide')->willReturnOnConsecutiveCalls(
      new DecisionResponse(['first' => new ChoiceAnswer('yes', ['yes' => 1.0, 'no' => 0.0], 1.0)]),
      new DecisionResponse([]),
      new DecisionResponse([]),
    );
    $this->expectException(InvalidDecisionResponseException::class);
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

  /**
   * Covers actual winner mismatch and other rejected normalized responses.
   */
  public static function invalidAnswers(): array {
    return [
      'observed winner mismatch' => [
        new ChoiceAnswer('complement', [
          'unknown' => 0.01,
          'complement' => 0.48,
          'unrelated' => 0.49,
          'foundation' => 0.02,
        ], 0.31),
        'choice_not_highest',
      ],
      'missing option' => [new ChoiceAnswer('complement', ['complement' => 1.0], 1.0), 'option_mismatch'],
      'invented option' => [new ChoiceAnswer('invented', ['invented' => 1.0], 1.0), 'option_mismatch'],
      'invalid sum' => [
        new ChoiceAnswer('complement', [
          'unknown' => 0.0,
          'complement' => 0.98,
          'unrelated' => 0.0,
          'foundation' => 0.0,
        ], 1.0),
        'distribution_sum',
      ],
      'missing answer' => [NULL, 'missing_or_wrong_type'],
    ];
  }

  /**
   * Recovery keeps valid uncertain answers and counts both provider attempts.
   */
  #[DataProvider('invalidAnswers')]
  public function testTargetedRecovery(?ChoiceAnswer $invalid, string $reason): void {
    $question = new ChoiceQuestion('What contribution could this candidate make?', [
      'foundation' => 'Main capability',
      'complement' => 'Supporting capability',
      'unrelated' => 'Does not help',
      'unknown' => 'Insufficient evidence',
    ]);
    $state = ['brief' => 'Read-only fixture', 'recipes' => ['candidate' => ['description' => 'Source evidence']]];
    $input = new DecisionInput($state, ['broken' => $question, 'uncertain' => $question]);
    $uncertain = new ChoiceAnswer('unknown', [
      'foundation' => 0.25,
      'complement' => 0.25,
      'unrelated' => 0.25,
      'unknown' => 0.25,
    ], 0.0);
    $replacement = new ChoiceAnswer('unrelated', [
      'unknown' => 0.01,
      'complement' => 0.48,
      'unrelated' => 0.49,
      'foundation' => 0.02,
    ], 0.31);
    $initial = ['uncertain' => $uncertain];
    if ($invalid) {
      $initial['broken'] = $invalid;
    }
    $client = $this->createMock(DecisionClientInterface::class);
    $calls = 0;
    $client->expects($this->exactly(2))->method('decide')->willReturnCallback(function (DecisionInput $request) use (&$calls, $input, $initial, $replacement): DecisionResponse {
      $calls++;
      $this->assertSame($input->getState(), $request->getState());
      if ($calls === 1) {
        $this->assertSame($input, $request);
        return new DecisionResponse($initial, 'fixture', new TokenUsageDto(10, 2, 12));
      }
      $this->assertSame(['broken'], array_keys($request->getQuestions()));
      $this->assertSame($input->getQuestions()['broken'], $request->getQuestions()['broken']);
      return new DecisionResponse(['broken' => $replacement], 'fixture', new TokenUsageDto(4, 1, 5));
    });
    $result = DecisionBatch::run($client, [$input]);
    $this->assertSame(['broken', 'uncertain'], array_keys($result['response']->getAnswers()));
    $this->assertSame($uncertain, $result['response']->getChoice('uncertain'));
    $this->assertSame($replacement, $result['response']->getChoice('broken'));
    $this->assertSame(['input' => 14, 'output' => 3, 'total' => 17], $result['response']->toArray()['usage']);
    $this->assertSame(['broken' => $reason], $result['requests'][0]['rejected_answers']);
    $this->assertSame([], $result['requests'][1]['rejected_answers']);
    $this->assertSame(2, $result['requests'][1]['attempt']);
    $this->assertGreaterThanOrEqual(0, $result['requests'][0]['elapsed_ms']);
    $this->assertGreaterThanOrEqual(0, $result['requests'][1]['elapsed_ms']);
  }

  /**
   * A repeated bad result fails with reason counts and never leaks raw data.
   */
  public function testPersistentFailureStopsBeforeLaterBatches(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $invalid = new ChoiceAnswer('no', ['yes' => 0.6, 'no' => 0.4], 0.2, ['secret' => 'raw provider detail']);
    $client->expects($this->exactly(2))->method('decide')->willReturn(new DecisionResponse(['first' => $invalid]));
    try {
      DecisionBatch::run($client, $this->inputs());
      $this->fail('A persistent contract violation must reject the plan.');
    }
    catch (InvalidDecisionResponseException $e) {
      $this->assertSame(['choice_not_highest' => 1], $e->violations);
      $this->assertStringNotContainsString('raw provider detail', $e->getMessage());
    }
  }

  /**
   * Unknown usage from a rejected attempt remains unknown after recovery.
   */
  public function testRecoveryPreservesUnknownUsage(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->exactly(2))->method('decide')->willReturnOnConsecutiveCalls(
      new DecisionResponse([]),
      new DecisionResponse(['first' => new ChoiceAnswer('yes', ['yes' => 1.0, 'no' => 0.0], 1.0)], 'fixture', new TokenUsageDto(4, 1, 5)),
    );
    $result = DecisionBatch::run($client, [$this->inputs()[0]]);
    $this->assertSame(['input' => NULL, 'output' => NULL, 'total' => NULL], $result['response']->toArray()['usage']);
  }

  /**
   * Provider execution errors are not response contract recovery candidates.
   */
  public function testProviderFailureIsNotRetried(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->once())->method('decide')->willThrowException(new \RuntimeException('Provider unavailable.'));
    $this->expectException(\RuntimeException::class);
    DecisionBatch::run($client, $this->inputs());
  }

}
