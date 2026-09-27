<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\site_architect\Assessment\BriefCapabilities;
use Drupal\site_architect\Assessment\DecisionClientInterface;
use Drupal\site_architect\Assessment\SearchPlanner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests source coverage, normalization and routing without live inference.
 */
#[Group('site_architect')]
final class SearchPlannerTest extends UnitTestCase {

  /**
   * Builds complete fixture answers from supplied source phrases.
   */
  private function response(DecisionInput $input, string $route, array $selected = []): DecisionResponse {
    $answers = [];
    foreach ($input->getQuestions() as $id => $question) {
      $choice = $id === 'ecosystem_search' ? $route : 'none';
      if (str_starts_with($id, 'scope_')) {
        $choice = 'work_area';
      }
      if (str_starts_with($id, 'group_')) {
        $choice = 'separate';
        foreach ($selected as $phrase) {
          if (str_contains(mb_strtolower($question->getInstructions()['passage']), $phrase) && ($key = array_search($phrase, $question->getCriteria(), TRUE)) !== FALSE) {
            $choice = $key;
            break;
          }
        }
      }
      foreach ($selected as $phrase) {
        if (str_starts_with($id, 'capability_') && ($key = array_search($phrase, $question->getCriteria(), TRUE)) !== FALSE) {
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
      if (isset($input->getState()['site'])) {
        $this->assertSame(['bundles' => []], $input->getState()['site']);
      }
      return $this->response($input, 'search', ['events', 'topics', 'groups', 'activity stream', 'notifications']);
    });
    $result = (new SearchPlanner($client))->plan('We want a Community site, with events and topics, placed in groups, with an activity stream and notifications.', ['bundles' => []]);
    $this->assertSame(['event', 'topic', 'group', 'activity stream', 'notification'], $result['queries']);
    $this->assertCount(5, $result['capabilities']);
    $this->assertSame('search', $result['action']);
    $this->assertFalse($result['terms_truncated']);
  }

  /**
   * Product details are grouped before they can become catalogue queries.
   */
  public function testDetailsDoNotBecomeIndependentSearches(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(static function (DecisionInput $input): DecisionResponse {
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $choice = 'search';
        if (str_starts_with($id, 'capability_')) {
          $phrase = $id === 'capability_0' ? 'equipment' : 'serial number';
          $choice = array_search($phrase, $question->getCriteria(), TRUE) ?: 'none';
        }
        elseif (str_starts_with($id, 'scope_')) {
          $choice = $id === 'scope_0' ? 'work_area' : 'detail';
        }
        elseif (str_starts_with($id, 'group_')) {
          $choice = array_search('equipment', $question->getCriteria(), TRUE);
          self::assertSame([$choice, 'separate'], $question->getOptionKeys());
        }
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, 1.0);
      }
      return new DecisionResponse($answers);
    });
    $brief = 'Manage equipment. Store a serial number, filter by serial number.';
    $result = (new SearchPlanner($client))->plan($brief, []);
    $this->assertSame(['equipment'], $result['queries']);
    $this->assertCount(1, $result['capabilities']);
    $this->assertSame(['Manage equipment', 'Store a serial number', 'filter by serial number'], reset($result['capabilities'])['source_texts']);
    $this->assertSame([], $result['unmapped_clauses']);
  }

  /**
   * Every segment, including late requirements, survives multiple requests.
   */
  public function testLongBriefCoverageAndUsage(): void {
    $features = [
      'events', 'topics', 'groups', 'notifications', 'search', 'translation',
      'media', 'moderation', 'profiles', 'registration', 'calendar', 'surveys',
      'bookmarks', 'payments',
    ];
    $brief = implode('. ', $features) . '. ' . str_repeat('background ', 400) . 'notifications';
    $client = $this->createMock(DecisionClientInterface::class);
    $calls = 0;
    $client->method('decide')->willReturnCallback(function (DecisionInput $input) use ($features, $brief, &$calls): DecisionResponse {
      $calls++;
      $this->assertSame($brief, $input->getState()['brief']);
      $this->assertLessThanOrEqual(12, count($input->getQuestions()));
      return new DecisionResponse($this->response($input, 'search', $features)->getAnswers(), 'test-model', new TokenUsageDto(10, 2, 12));
    });
    $result = (new SearchPlanner($client))->plan($brief, []);
    $this->assertGreaterThan(4000, mb_strlen($brief));
    $this->assertGreaterThan(1, $calls);
    $this->assertCount(14, $result['capabilities']);
    $this->assertContains('payment', $result['queries']);
    $this->assertSame($result['coverage']['segments_total'], $result['coverage']['segments_processed']);
    $this->assertSame($calls * 10, $result['usage']['input']);
    $this->assertCount($calls, $result['requests']);
    $this->assertFalse($result['terms_truncated']);
    $notifications = array_values(array_filter($result['capabilities'], static fn ($capability) => $capability['query'] === 'notification'))[0];
    $this->assertCount(2, $notifications['source_texts']);
  }

  /**
   * Windows retain late words and compound phrases across their boundary.
   */
  public function testWordWindowsRetainAllSourcePhrases(): void {
    $clauses = BriefCapabilities::clauses(str_repeat('context ', 94) . 'activity stream ' . str_repeat('context ', 40) . 'notifications');
    $terms = array_merge(...array_column($clauses, 'terms'));
    $this->assertContains('activity stream', $terms);
    $this->assertContains('notifications', $terms);
    foreach ($clauses as $clause) {
      $this->assertLessThan(255, count($clause['terms']));
      $this->assertFalse($clause['truncated']);
    }
  }

  /**
   * Named work areas retain their details without becoming separate searches.
   */
  public function testNamedSectionsPreserveContext(): void {
    $brief = "Groups: Members need private spaces. Access must follow membership.\n\nNotifications: Send replies by email, with subscription controls.";
    $clauses = BriefCapabilities::clauses($brief);
    $this->assertCount(2, $clauses);
    $this->assertStringContainsString('Access must follow membership.', $clauses[0]['source_text']);
    $this->assertContains('subscription controls', $clauses[1]['terms']);
    $this->assertSame('media', BriefCapabilities::query('media'));
    $this->assertSame('data', BriefCapabilities::query('data'));
    $this->assertSame('event', BriefCapabilities::query('events'));
  }

  /**
   * Very fragmented inputs fail before costs rather than clipping requirements.
   */
  public function testOversizedScopeFailsBeforeInference(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->never())->method('decide');
    $this->expectException(\LengthException::class);
    (new SearchPlanner($client))->plan(str_repeat('events;', 201), []);
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
