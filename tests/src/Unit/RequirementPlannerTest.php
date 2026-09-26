<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\DecisionBatch;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\RequirementPlanner;
use Drupal\ai_site_advisor\Assessment\AgentPlan;
use PHPUnit\Framework\Attributes\Group;

/**
 * Combinations preserve source needs, distinct components and coverage gaps.
 */
#[Group('ai_site_advisor')]
final class RequirementPlannerTest extends UnitTestCase {

  /**
   * One work area uses records plus replies without claiming full integration.
   */
  public function testIndependentPartsAndVerification(): void {
    $text = 'Store a title and opening body. Members can reply. Notify subscribers and allow opt-out. Access must follow membership. This is a planning exercise. Build a new capability.';
    $options = [
      ['id' => 'bundle__record', 'bundle_id' => 'record', 'kind' => 'content_type', 'label' => 'Existing record'],
      [
        'id' => 'reply',
        'package' => 'fixture/reply',
        'kind' => 'module',
        'label' => 'Replies',
        'description' => 'Replies to records.',
      ],
      [
        'id' => 'delivery',
        'package' => 'fixture/delivery',
        'kind' => 'module',
        'label' => 'Delivery',
        'description' => 'Sends messages.',
      ],
      ['id' => 'configure', 'kind' => 'configuration', 'label' => 'Configuration to design'],
      ['id' => 'unresolved', 'kind' => 'unresolved', 'label' => 'Unresolved'],
    ];
    $site = ['bundles' => ['record' => ['fields' => ['body' => ['label' => 'Body', 'type' => 'text_long']]]]];
    $plan = ['areas' => ['discussion' => ['source_text' => $text, 'options' => $options]]];
    $client = $this->createMock(DecisionClientInterface::class);
    $calls = 0;
    $client->expects($this->exactly(2))->method('decide')->willReturnCallback(function (DecisionInput $input) use (&$calls): DecisionResponse {
      $calls++;
      $this->assertLessThanOrEqual(100000, DecisionBatch::bytes($input));
      $answers = [];
      if ($calls === 1) {
        $this->assertSame('text_long', $input->getState()['options']['bundle__record']['fields']['body']['type']);
        $this->assertCount(12, $input->getQuestions());
      }
      else {
        $this->assertCount(3, $input->getQuestions(), 'Conditions, context and configuration gaps are not asserted as component coverage.');
        $this->assertSame('fixture/reply', $input->getState()['matches']['p1']['option']['package']);
        $this->assertSame('text_long', $input->getState()['matches']['p0']['option']['fields']['body']['type']);
      }
      foreach ($input->getQuestions() as $id => $question) {
        $part = substr($id, strrpos($id, '__') + 2);
        $kind = [
          'p0' => 'record',
          'p1' => 'capability',
          'p2' => 'capability',
          'p3' => 'constraint',
          'p4' => 'context',
          'p5' => 'capability',
        ];
        $choices = [
          'p0' => 'bundle__record',
          'p1' => 'reply',
          'p2' => 'delivery',
          'p3' => 'unresolved',
          'p4' => 'unresolved',
          'p5' => 'configure',
        ];
        $choice = str_starts_with($id, 'part_kind__') ? $kind[$part] : (str_starts_with($id, 'part_option__') ? $choices[$part] : ($part === 'p2' ? 'partial' : 'direct'));
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, 1.0);
      }
      return new DecisionResponse($answers, 'fixture', new TokenUsageDto(10, 2, 12));
    });
    $result = (new RequirementPlanner($client))->plan($text, $site, $plan);
    $parts = $result['areas']['discussion']['parts'];
    $this->assertSame(['supported', 'supported', 'partial', 'check', 'context', 'open'], array_column($parts, 'status'));
    $this->assertSame(['bundle__record', 'reply', 'delivery', NULL, NULL, NULL], array_column($parts, 'option_id'));
    $this->assertSame(array_values(RequirementPlanner::parts($text)), array_column($parts, 'text'));
    $this->assertTrue($parts[2]['needs_review']);
    $this->assertFalse($result['areas']['discussion']['integration_verified']);
    $this->assertSame(['input' => 20, 'output' => 4, 'total' => 24], $result['usage']);
    $this->assertCount(15, $result['answers']);

    // MCP retains each part, even a component outside its general shortlist.
    $assessment = [
      'status' => 'needs_clarification',
      'site' => $site,
      'discovery' => [],
      'search_plan' => [],
      'plan' => [
        'areas' => [
          'discussion' => [
            'label' => 'Discussions',
            'status' => 'needs_review',
            'selection' => ['id' => 'reply', 'needs_review' => TRUE],
            'options' => $options,
            'requirements' => $result['areas']['discussion'],
            'handoff' => ['configuration_area' => NULL, 'resources' => []],
            'check' => 'Verify the record/reply relationship.',
          ],
        ],
      ],
    ];
    $compact = AgentPlan::compact($assessment);
    $this->assertSame('agent-plan-v2', $compact['schema_version']);
    $area = $compact['work_areas'][0];
    $this->assertSame('undecided', $area['starting_point']['kind']);
    $this->assertCount(5, $area['parts']);
    $this->assertSame('record', $area['parts'][0]['content_type']);
    $this->assertSame('reply', $area['parts'][1]['candidate']);
    $this->assertSame('partial', $area['parts'][2]['status']);
    $this->assertArrayHasKey('delivery', $compact['candidates']);
    $this->assertFalse($area['integration_verified']);
    $this->assertSame(0, $area['other_package_options']);
  }

  /**
   * A confident initial selection cannot override contrary coverage evidence.
   */
  public function testUnsupportedAndUncertainMatchesStayHonest(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(static function (DecisionInput $input): DecisionResponse {
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $choice = str_starts_with($id, 'part_kind__') ? 'capability' : (str_starts_with($id, 'part_option__') ? 'fixture' : 'unsupported');
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        if ($id === 'part_fit__area__p1') {
          $choice = 'direct';
          $distribution = ['direct' => 0.6, 'partial' => 0.2, 'unsupported' => 0.1, 'unknown' => 0.1];
        }
        $answers[$id] = new ChoiceAnswer($choice, $distribution, $choice === 'direct' ? 0.4 : 1.0);
      }
      return new DecisionResponse($answers);
    });
    $area = [
      'source_text' => 'Deliver messages. Store reply threads.',
      'options' => [
        [
          'id' => 'fixture',
          'label' => 'Adjacent capability',
          'package' => 'fixture/adjacent',
        ],
      ],
    ];
    $result = (new RequirementPlanner($client))->plan($area['source_text'], ['bundles' => []], ['areas' => ['area' => $area]]);
    $this->assertSame(['open', 'partial'], array_column($result['areas']['area']['parts'], 'status'));
    $this->assertTrue($result['areas']['area']['parts'][1]['needs_review']);
    $this->assertSame(['unsupported', 'direct'], array_column(array_column($result['areas']['area']['parts'], 'coverage'), 'choice'));
    $this->assertNull($result['usage']['input']);
  }

  /**
   * Planning instructions do not promote a settings UI into a product feature.
   */
  public function testUncertainContextRemainsVisible(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->once())->method('decide')->willReturnCallback(static function (DecisionInput $input): DecisionResponse {
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        if (str_starts_with($id, 'part_kind__')) {
          $answers[$id] = new ChoiceAnswer('context', [
            'record' => 0.1,
            'capability' => 0.0,
            'constraint' => 0.3,
            'context' => 0.6,
            'unknown' => 0.0,
          ], 0.4);
        }
        else {
          $answers[$id] = new ChoiceAnswer('settings', ['settings' => 1.0], 1.0);
        }
      }
      return new DecisionResponse($answers);
    });
    $area = [
      'source_text' => 'Inspect existing fields first.',
      'options' => [['id' => 'settings', 'label' => 'Settings UI', 'package' => 'fixture/settings']],
    ];
    $result = (new RequirementPlanner($client))->plan($area['source_text'], [], ['areas' => ['fixture' => $area]]);
    $part = $result['areas']['fixture']['parts'][0];
    $this->assertSame('check', $part['status']);
    $this->assertNull($part['option_id']);
    $this->assertTrue($part['needs_review']);
  }

}
