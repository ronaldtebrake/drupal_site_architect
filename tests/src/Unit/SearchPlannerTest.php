<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\site_architect\Assessment\BriefCapabilities;
use Drupal\site_architect\Assessment\CapabilityExpansion;
use Drupal\site_architect\Assessment\CapabilityOptions;
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
  private function response(DecisionInput $input, string $route, array $selected = [], string $permission = 'allowed'): DecisionResponse {
    $answers = [];
    foreach ($input->getQuestions() as $id => $question) {
      if (str_starts_with($id, 'label_support_')) {
        $answers[$id] = new ChoiceAnswer('label', ['label' => 1.0, 'fragment' => 0.0], 1.0);
        continue;
      }
      if (str_starts_with($id, 'support_')) {
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution['omit'] = 1.0;
        $answers[$id] = new ChoiceAnswer('omit', $distribution, 1.0);
        continue;
      }
      $choice = $id === 'ecosystem_search' ? $route : 'none';
      if ($id === 'public_discovery') {
        $choice = $permission;
      }
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
   * Several capabilities in one clause survive through discovery and scoring.
   */
  public function testSupportingCapabilitiesStayWithTheirWorkArea(): void {
    $brief = 'In a group members create discussion posts using translation';
    $client = $this->createMock(DecisionClientInterface::class);
    $calls = 0;
    $client->method('decide')->willReturnCallback(function ($input) use (&$calls) {
      $calls++;
      $answers = $this->response($input, 'search', ['discussion posts'])->getAnswers();
      foreach ($input->getQuestions() as $id => $question) {
        if (!str_starts_with($id, 'support_')) {
          continue;
        }
        $choice = in_array($question->getInstructions()['phrase'], ['group', 'translation'], TRUE) ? 'capability' : 'omit';
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, 1.0);
      }
      return new DecisionResponse($answers, 'fixture', new TokenUsageDto(10, 2, 12));
    });
    $plan = (new SearchPlanner($client))->plan($brief, []);
    $this->assertSame(['discussion posts', 'group', 'translation'], $plan['queries']);
    $this->assertCount(1, $plan['capabilities'], 'Supporting needs do not create disconnected work areas.');
    $area = reset($plan['capabilities']);
    $this->assertSame(['group', 'translation'], array_column($area['supporting_capabilities'], 'label'));
    $this->assertSame($brief, $area['source_text']);
    $this->assertSame([$brief, $brief], array_column($area['supporting_capabilities'], 'source_text'));
    $this->assertSame($calls * 10, $plan['usage']['input']);
    $this->assertSame($calls, count($plan['requests']));
    $this->assertFalse($area['extraction_needs_review']);
    $this->assertTrue(CapabilityOptions::matches(['matched_queries' => ['group']], $area));
    $this->assertTrue(CapabilityOptions::matches(['matched_queries' => ['translation']], $area));
    $this->assertFalse(CapabilityOptions::matches(['matched_queries' => ['unrelated']], $area));
  }

  /**
   * A doubtful extra capability remains visible and cannot bypass local-only.
   */
  public function testUncertainSupportingCapabilityAndRestrictedSearch(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'search', ['discussion posts'], 'restricted')->getAnswers();
      foreach ($input->getQuestions() as $id => $question) {
        if (str_starts_with($id, 'support_') && $question->getInstructions()['phrase'] === 'group') {
          $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
          $distribution['capability'] = 0.6;
          $distribution['omit'] = 0.4;
          $answers[$id] = new ChoiceAnswer('capability', $distribution, 0.2);
        }
      }
      return new DecisionResponse($answers);
    });
    $plan = (new SearchPlanner($client))->plan('In a group create discussion posts. Do not search external catalogs.', []);
    $area = reset($plan['capabilities']);
    $this->assertSame([], $plan['queries']);
    $this->assertTrue($plan['needs_review']);
    $this->assertTrue($area['extraction_needs_review']);
    $this->assertSame('group', $area['supporting_capabilities'][0]['label']);
    $this->assertTrue($area['supporting_capabilities'][0]['needs_review']);
  }

  /**
   * Fields and fragments stay in their source; complete phrases are deduped.
   */
  public function testSupportingLabelsNeedIndependentGrammarAndRoleEvidence(): void {
    $brief = 'Store a serial number and use a shared visual layout. Do not add translation.';
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(static function ($input) {
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $term = $question->getInstructions()['phrase'];
        $confidence = 1.0;
        if (str_starts_with($id, 'label_')) {
          $choice = $term === 'store' ? 'fragment' : 'label';
          // A doubtful modifier must not become a search despite a high role
          // score; grammar and role are independent evidence.
          $confidence = $term === 'shared' ? 0.5 : 1.0;
        }
        else {
          $choice = match ($term) {
            'serial number' => 'attribute',
            'translation' => 'omit',
            default => 'capability',
          };
        }
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, $confidence);
      }
      return new DecisionResponse($answers);
    });
    $areas = ['inventory' => ['label' => 'inventory', 'source_texts' => [$brief]]];
    $clauses = [
      [
        'source_text' => $brief,
        'terms' => ['store', 'serial number', 'shared', 'visual layout', 'layout', 'translation'],
      ],
    ];
    $result = CapabilityExpansion::expand($client, $brief, $clauses, $areas);
    $this->assertSame(['visual layout'], array_column($result['areas']['inventory']['supporting_capabilities'], 'label'));
    $this->assertSame([$brief], $result['areas']['inventory']['source_texts'], 'The original requirements remain intact.');
    $this->assertCount(12, $result['answers'], 'Both judgments remain inspectable for every phrase.');
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
    $this->assertSame(['events', 'topics', 'groups', 'activity stream', 'notifications'], $result['queries']);
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
        if (str_starts_with($id, 'label_support_')) {
          $answers[$id] = new ChoiceAnswer('label', ['label' => 1.0, 'fragment' => 0.0], 1.0);
          continue;
        }
        if (str_starts_with($id, 'support_')) {
          $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
          $distribution['attribute'] = 1.0;
          $answers[$id] = new ChoiceAnswer('attribute', $distribution, 1.0);
          continue;
        }
        $choice = $id === 'public_discovery' ? 'allowed' : 'search';
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
    $this->assertContains('payments', $result['queries']);
    $this->assertSame($result['coverage']['segments_total'], $result['coverage']['segments_processed']);
    $this->assertSame($calls * 10, $result['usage']['input']);
    $this->assertCount($calls, $result['requests']);
    $this->assertFalse($result['terms_truncated']);
    $notifications = array_values(array_filter($result['capabilities'], static fn ($capability) => $capability['query'] === 'notifications'))[0];
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
    $this->assertSame('events', BriefCapabilities::query('events'));
    $this->assertSame('email updates', BriefCapabilities::query("  Email \t updates  "));
    $this->assertSame('news', BriefCapabilities::query('news'));
    $this->assertSame('access', BriefCapabilities::query('access'));
    $this->assertSame('series', BriefCapabilities::query('series'));
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
   * A selected read-only search gathers evidence while retaining uncertainty.
   */
  public function testUncertainSearchRetainsReviewFlag(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'search', ['events'])->getAnswers();
      if (isset($answers['ecosystem_search'])) {
        $answers['ecosystem_search'] = new ChoiceAnswer('search', ['search' => 0.79, 'local' => 0.15, 'clarify' => 0.06], 0.69);
      }
      return new DecisionResponse($answers);
    });
    $plan = (new SearchPlanner($client))->plan('Maybe events.', []);
    $this->assertSame('search', $plan['action']);
    $this->assertSame(['events'], $plan['queries']);
    $this->assertTrue($plan['needs_review']);
    $this->assertStringContainsString('Jev preferred an ecosystem search', $plan['reason']);
    $this->assertStringContainsString('searched to gather evidence', $plan['reason']);
  }

  /**
   * A review flag must not conceal the model's actual local-route choice.
   */
  public function testUncertainLocalRouteExplainsSkippedSearch(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'local', ['workshops'])->getAnswers();
      if (isset($answers['ecosystem_search'])) {
        $answers['ecosystem_search'] = new ChoiceAnswer('local', ['local' => 0.79, 'search' => 0.21, 'clarify' => 0.0], 0.67);
      }
      return new DecisionResponse($answers);
    });
    $plan = (new SearchPlanner($client))->plan('Reuse our workshops.', []);
    $this->assertSame('local', $plan['action']);
    $this->assertSame([], $plan['queries']);
    $this->assertSame('local', $plan['answers']['ecosystem_search']['choice']);
    $this->assertStringContainsString('Jev preferred inspecting local site/core configuration', $plan['reason']);
    $this->assertStringContainsString('External catalogs were not queried', $plan['reason']);
  }

  /**
   * Recognised relationship subjects survive uncertain detail ownership.
   */
  public function testUnassignedFeatureRetainsProvisionalSearch(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'search', ['events', 'groups'])->getAnswers();
      if (isset($answers['scope_1'])) {
        $answers['scope_1'] = new ChoiceAnswer('detail', ['work_area' => 0.02, 'detail' => 0.98, 'context' => 0.0], 0.98);
      }
      if (isset($answers['group_1'])) {
        $options = $input->getQuestions()['group_1']->getOptionKeys();
        $this->assertCount(2, $options, 'Only the proposed parent and separate are grouping options.');
        $answers['group_1'] = new ChoiceAnswer('separate', [$options[0] => 0.4, 'separate' => 0.6], 0.2);
      }
      return new DecisionResponse($answers);
    });
    $plan = (new SearchPlanner($client))->plan('Manage events, placed in groups.', []);
    $this->assertSame(['events', 'groups'], $plan['queries']);
    $this->assertSame([], $plan['unmapped_clauses']);
    $groups = array_values($plan['capabilities'])[1];
    $this->assertSame('placed in groups', $groups['source_text']);
    $this->assertTrue($groups['grouping_needs_review']);
  }

  /**
   * Restricted briefs never query catalogs, including conflicting route votes.
   */
  public function testClarifyAndLocalRoutesArePreserved(): void {
    foreach (['search', 'clarify', 'local'] as $route) {
      $client = $this->createMock(DecisionClientInterface::class);
      $client->method('decide')->willReturnCallback(fn ($input) => $this->response($input, $route, ['events'], 'restricted'));
      $plan = (new SearchPlanner($client))->plan('Plan events using only the current site. Do not search external catalogs.', []);
      $this->assertSame($route === 'search' ? 'clarify' : $route, $plan['action']);
      $this->assertSame([], $plan['queries']);
      $this->assertFalse($plan['gather_evidence']);
    }
  }

  /**
   * Public evidence can support clarification without choosing an approach.
   */
  public function testClarificationGathersBoundedPublicEvidence(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $features = ['equipment', 'booking', 'notification', 'translation'];
    $client->method('decide')->willReturnCallback(fn ($input) => $this->response($input, 'clarify', $features));
    $plan = (new SearchPlanner($client))->plan('Equipment. Booking. Notification. Translation.', []);
    $this->assertSame('clarify', $plan['action']);
    $this->assertTrue($plan['needs_review']);
    $this->assertTrue($plan['gather_evidence']);
    $this->assertSame(['equipment', 'booking', 'notification'], $plan['queries']);
    $this->assertTrue($plan['exploratory_queries_truncated']);
    $this->assertCount(4, $plan['capabilities'], 'Unsearched requirements must remain in the plan.');
    $this->assertSame('clarify', $plan['answers']['ecosystem_search']['choice']);
  }

  /**
   * Uncertain disclosure is not authority to send terms to a public catalog.
   */
  public function testUncertainDisclosureStopsSearch(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(function ($input) {
      $answers = $this->response($input, 'clarify', ['equipment'])->getAnswers();
      if (isset($answers['public_discovery'])) {
        $distribution = ['allowed' => 0.6, 'restricted' => 0.2, 'unclear' => 0.2];
        $answers['public_discovery'] = new ChoiceAnswer('allowed', $distribution, 0.4);
      }
      return new DecisionResponse($answers);
    });
    $plan = (new SearchPlanner($client))->plan('Manage equipment for a confidential project.', []);
    $this->assertFalse($plan['gather_evidence']);
    $this->assertSame([], $plan['queries']);
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
