<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\BriefCapabilities;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\SearchPlanner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests source coverage, normalization and routing without live inference.
 */
#[Group('ai_site_advisor')]
final class SearchPlannerTest extends UnitTestCase {

  /**
   * Builds complete fixture answers from supplied source phrases.
   */
  private function response(DecisionInput $input, string $route, array $selected = []): DecisionResponse {
    $answers = [];
    foreach ($input->getQuestions() as $id => $question) {
      $choice = $id === 'ecosystem_search' ? $route : 'none';
      foreach ($selected as $phrase) {
        if ($id !== 'ecosystem_search' && ($key = array_search($phrase, $question->getCriteria(), TRUE)) !== FALSE) {
          $choice = $key;
          break;
        }
      }
      $probabilities = array_fill_keys($question->getOptionKeys(), 0.0);
      $probabilities[$choice] = 1.0;
      $answers[$id] = new ChoiceAnswer($choice, $probabilities, 1.0);
    }
    return new DecisionResponse($answers, 'test-model');
  }

  /**
   * A compound request must supply candidates for every meaningful clause.
   */
  public function testCompoundBriefCoverage(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function (DecisionInput $input): DecisionResponse {
      $this->assertSame(['bundles' => []], $input->getState()['site']);
      return $this->response($input, 'search', ['events', 'topics', 'groups', 'activity stream', 'notifications']);
    });
    $result = (new SearchPlanner($client))->plan('We want a Community site, with events and topics, placed in groups, with an activity stream and notifications.', ['bundles' => []]);
    $this->assertSame(['event', 'topic', 'group', 'activity stream', 'notification'], $result['queries']);
    $this->assertCount(5, $result['capabilities']);
    $this->assertSame('search', $result['action']);
    $this->assertFalse($result['terms_truncated']);
  }

  /**
   * Token-like fragments and network addresses do not become search options.
   */
  public function testSourcePrivacyAndPunctuation(): void {
    $clauses = BriefCapabilities::clauses('A workflow: for Acme. owner@example.test; https://private.example/path?token=123; sk_test_abc123xyz.');
    $terms = array_merge(...array_column($clauses, 'terms'));
    $this->assertContains('workflow', $terms);
    $this->assertNotContains('owner', $terms);
    $this->assertNotContains('private', $terms);
    $this->assertNotContains('abc', $terms);
  }

  /**
   * Local plans retain capabilities but never request ecosystem queries.
   */
  public function testLocalBranchRetainsPlanScope(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(fn ($input) => $this->response($input, 'local', ['workshops']));
    $result = (new SearchPlanner($client))->plan('Reuse our workshops.', []);
    $this->assertSame('local', $result['action']);
    $this->assertSame([], $result['queries']);
    $this->assertCount(1, $result['capabilities']);
  }

  /**
   * Uncertain routing cannot trigger an external query.
   */
  public function testUncertainRouteRequiresClarification(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'search', ['events'])->getAnswers();
      $answers['ecosystem_search'] = new ChoiceAnswer('search', ['search' => 0.6, 'local' => 0.4, 'clarify' => 0.0], 0.2);
      return new DecisionResponse($answers);
    });
    $plan = (new SearchPlanner($client))->plan('Maybe events.', []);
    $this->assertSame('clarify', $plan['action']);
    $this->assertSame([], $plan['queries']);
    $this->assertTrue($plan['needs_review']);
  }

  /**
   * No suitable term is supported, never a fabricated query.
   */
  public function testNoTermRequiresClarification(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(fn ($input) => $this->response($input, 'search'));
    $plan = (new SearchPlanner($client))->plan('Implement the thing we discussed.', []);
    $this->assertSame('clarify', $plan['action']);
    $this->assertNull($plan['query']);
  }

  /**
   * Invented options cannot become queries or plan requirements.
   */
  public function testInventedKeywordIsRejected(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'search')->getAnswers();
      $answers['capability_0'] = new ChoiceAnswer('invented', ['invented' => 1.0], 1.0);
      return new DecisionResponse($answers);
    });
    $this->expectException(\UnexpectedValueException::class);
    (new SearchPlanner($client))->plan('Find workflow options.', []);
  }

}
