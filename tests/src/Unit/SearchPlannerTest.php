<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\SearchPlanner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests typed routing, source-word selection and unused-branch handling.
 */
#[Group('ai_site_advisor')]
final class SearchPlannerTest extends UnitTestCase {

  /**
   * Creates a complete answer over an input's actual options.
   */
  private function answer(DecisionInput $input, string $id, string $choice): ChoiceAnswer {
    $probabilities = array_fill_keys($input->getQuestions()[$id]->getOptionKeys(), 0.0);
    $probabilities[$choice] = 1.0;
    return new ChoiceAnswer($choice, $probabilities, 1.0);
  }

  /**
   * Both questions share inspected context; a source word becomes the query.
   */
  public function testSelectsKeywordWithoutForwardingBrief(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->once())->method('decide')->willReturnCallback(function (DecisionInput $input): DecisionResponse {
      $this->assertSame(['workflow' => 'existing'], $input->getState()['site']);
      $criteria = $input->getQuestions()['search_term']->getCriteria();
      $this->assertNotContains('owner', $criteria);
      $this->assertNotContains('example', $criteria);
      $this->assertNotContains('private', $criteria);
      $this->assertNotContains('abc', $criteria);
      $term = array_search('workflow', $criteria, TRUE);
      $this->assertIsString($term);
      return new DecisionResponse([
        'ecosystem_search' => $this->answer($input, 'ecosystem_search', 'search'),
        'search_term' => $this->answer($input, 'search_term', $term),
      ], 'test-model');
    });
    $plan = (new SearchPlanner($client))->plan('A workflow: for Acme. Email owner@example.test; https://private.example/path?token=123; key sk_test_abc123xyz.', ['workflow' => 'existing']);
    $this->assertSame('search', $plan['action']);
    $this->assertSame('workflow', $plan['query']);
    $this->assertSame('test-model', $plan['model']);
  }

  /**
   * A local branch does not need a valid or confident speculative search term.
   */
  public function testLocalBranchIgnoresUnusedTerm(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(fn ($input) => new DecisionResponse([
      'ecosystem_search' => $this->answer($input, 'ecosystem_search', 'local'),
    ]));
    $plan = (new SearchPlanner($client))->plan('Reuse our existing workshop fields.', []);
    $this->assertSame('local', $plan['action']);
    $this->assertNull($plan['query']);
    $this->assertFalse($plan['needs_review']);
  }

  /**
   * An uncertain route must not result in an external search.
   */
  public function testUncertainRouteRequiresClarification(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturn(new DecisionResponse([
      'ecosystem_search' => new ChoiceAnswer('search', ['search' => 0.6, 'local' => 0.4, 'clarify' => 0.0], 0.2),
    ]));
    $plan = (new SearchPlanner($client))->plan('Something better for this site.', []);
    $this->assertSame('clarify', $plan['action']);
    $this->assertNull($plan['query']);
    $this->assertTrue($plan['needs_review']);
  }

  /**
   * No suitable term is a supported result, never a fabricated query.
   */
  public function testNoTermRequiresClarification(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(fn ($input) => new DecisionResponse([
      'ecosystem_search' => $this->answer($input, 'ecosystem_search', 'search'),
      'search_term' => $this->answer($input, 'search_term', 'none'),
    ]));
    $plan = (new SearchPlanner($client))->plan('Implement the thing we discussed.', []);
    $this->assertSame('clarify', $plan['action']);
    $this->assertNull($plan['query']);
  }

  /**
   * A malformed or invented option can never become a search query.
   */
  public function testInventedKeywordIsRejected(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(fn ($input) => new DecisionResponse([
      'ecosystem_search' => $this->answer($input, 'ecosystem_search', 'search'),
      'search_term' => new ChoiceAnswer('invented', ['invented' => 1.0], 1.0),
    ]));
    $this->expectException(\UnexpectedValueException::class);
    (new SearchPlanner($client))->plan('Find workflow options.', []);
  }

}
