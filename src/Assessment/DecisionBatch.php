<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;

/**
 * Sizes requests without dropping questions and combines validated results.
 */
final class DecisionBatch {

  public const MAX_REQUEST_BYTES = 100000;

  /**
   * Packs every question, retaining its complete evidence in each request.
   */
  public static function split(DecisionInput $input, int $questions_per_request = 48): array {
    $inputs = [];
    $questions = [];
    foreach ($input->getQuestions() as $id => $question) {
      $candidate = new DecisionInput($input->getState(), $questions + [$id => $question]);
      if ($questions && (count($questions) >= $questions_per_request || self::bytes($candidate) > self::MAX_REQUEST_BYTES)) {
        $inputs[] = new DecisionInput($input->getState(), $questions);
        $questions = [];
      }
      $questions[$id] = $question;
      if (self::bytes(new DecisionInput($input->getState(), $questions)) > self::MAX_REQUEST_BYTES) {
        throw new \LengthException('A single decision still exceeds the 100 KB evidence limit after batching. Narrow the selected site evidence in AI Site Advisor settings. No partial plan was produced.');
      }
    }
    if ($questions) {
      $inputs[] = new DecisionInput($input->getState(), $questions);
    }
    return $inputs;
  }

  /**
   * Counts compact UTF-8 request bytes, not pretty-printing or escaped Unicode.
   */
  public static function bytes(DecisionInput $input): int {
    return strlen(json_encode($input->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Checks batches before inference and never returns partial results.
   */
  public static function run(DecisionClientInterface $client, array $inputs): array {
    $questions = [];
    foreach ($inputs as $input) {
      if (self::bytes($input) > self::MAX_REQUEST_BYTES || array_intersect_key($questions, $input->getQuestions())) {
        throw new \LogicException('Decision batches must fit the request budget and have unique question IDs.');
      }
      $questions += $input->getQuestions();
    }
    $answers = $requests = [];
    $usage = ['input' => 0, 'output' => 0, 'total' => 0];
    $model = '';
    foreach ($inputs as $input) {
      // Keep successful answers. Retry only malformed/missing answers once,
      // with exactly the same evidence and criteria, never invented scores.
      for ($attempt = 1; $attempt <= 2; $attempt++) {
        $response = $client->decide($input);
        $rejected = [];
        foreach ($input->getQuestions() as $id => $question) {
          try {
            $answer = $response->getChoice($id);
          }
          catch (\UnexpectedValueException) {
            $rejected[$id] = 'missing_or_wrong_type';
            continue;
          }
          if ($violation = ChoiceValidator::violation($answer, $question)) {
            $rejected[$id] = $violation;
            continue;
          }
          $answers[$id] = $answer;
        }
        $model = $response->getModel();
        $request_usage = $response->toArray()['usage'];
        foreach ($usage as $key => $value) {
          $usage[$key] = $value !== NULL && $request_usage[$key] !== NULL ? $value + $request_usage[$key] : NULL;
        }
        $state = $input->getState();
        $requests[] = [
          'model' => $model,
          'question_ids' => array_keys($input->getQuestions()),
          'candidate_ids' => is_array($state) ? array_keys($state['recipes'] ?? []) : [],
          'requirement_ids' => is_array($state) ? array_keys($state['requirements'] ?? []) : [],
          'bytes' => self::bytes($input),
          'usage' => $request_usage,
          'attempt' => $attempt,
          'rejected_answers' => $rejected,
        ];
        if (!$rejected) {
          break;
        }
        if ($attempt === 2) {
          throw new InvalidDecisionResponseException(array_count_values($rejected));
        }
        $input = new DecisionInput($state, array_intersect_key($input->getQuestions(), $rejected));
      }
    }
    $ordered_answers = [];
    foreach ($questions as $id => $question) {
      $ordered_answers[$id] = $answers[$id];
    }
    return [
      'response' => new DecisionResponse($ordered_answers, $model, new TokenUsageDto($usage['input'], $usage['output'], $usage['total'])),
      'questions' => $questions,
      'requests' => $requests,
    ];
  }

}
