<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\site_architect\Assessment\AgentPlan;
use Drupal\site_architect\Assessment\DecisionClientInterface;
use Drupal\site_architect\Assessment\RequirementPlanner;
use Drupal\site_architect\Presentation\ImplementationStory;
use PHPUnit\Framework\Attributes\Group;

/**
 * Connections use actual records and fields, including uncertainty safeguards.
 */
#[Group('site_architect')]
final class RequirementConnectionsTest extends UnitTestCase {

  /**
   * Generic equipment data exercises storage, listing and display together.
   */
  public function testFieldsAndListingsShareAnInspectedTarget(): void {
    $texts = [
      'Manage equipment',
      'Store a serial number',
      'Filter the listing by serial number',
      'Use a shared display',
      'Store a service date',
    ];
    $fields = [
      'field_serial' => ['label' => 'Serial number', 'type' => 'string'],
      'field_notes' => ['label' => 'Service notes', 'type' => 'text_long'],
    ];
    $site = [
      'bundles' => ['asset' => ['label' => 'Asset', 'description' => 'Equipment records.', 'fields' => $fields]],
      'configuration_areas' => [
        'node_type' => [
          'records' => [
            'asset' => [
              'links' => [['label' => 'Manage fields', 'url' => '/admin/structure/types/manage/asset/fields']],
            ],
          ],
        ],
      ],
    ];
    $area = [
      'label' => 'Equipment',
      'source_text' => implode('; ', $texts),
      'source_texts' => $texts,
      'options' => [
        ['id' => 'bundle__asset', 'label' => 'Asset', 'bundle_id' => 'asset', 'kind' => 'content_type'],
        [
          'id' => 'listing',
          'label' => 'Collection renderer',
          'package' => 'fixture/listing',
          'links' => [['label' => 'Configure listing', 'url' => '/admin/structure/fixture-listing']],
        ],
        ['id' => 'display', 'label' => 'Shared renderer', 'package' => 'fixture/display'],
      ],
      'selection' => ['id' => 'bundle__asset', 'needs_review' => TRUE],
      'status' => 'needs_review',
      'handoff' => ['configuration_area' => NULL, 'resources' => []],
      'check' => 'Verify the combined configuration.',
    ];
    $client = $this->createMock(DecisionClientInterface::class);
    $client->expects($this->exactly(2))->method('decide')->willReturnCallback(function (DecisionInput $input): DecisionResponse {
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $segments = explode('__', $id);
        $part = $segments[2];
        $choice = match ($segments[0]) {
          'part_kind' => ['p0' => 'record', 'p1' => 'field', 'p2' => 'listing', 'p3' => 'presentation', 'p4' => 'field'][$part],
          'part_option' => [
            'p0' => 'bundle__asset',
            'p1' => 'bundle__asset',
            'p2' => 'listing',
            'p3' => 'display',
            'p4' => 'bundle__asset',
          ][$part],
          'part_target' => 'asset',
          'part_fit' => 'direct',
          'part_field' => $segments[3] === 'field_serial' && $part !== 'p4' ? 'relevant' : 'unrelated',
        };
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $confidence = 1.0;
        if ($segments[0] === 'part_field') {
          $this->assertSame('string', $input->getState()['matches'][$part]['record']['fields']['field_serial']['type']);
          // A text field with an adjacent label is not a verified date field.
          if ($part === 'p4' && $segments[3] === 'field_notes') {
            $choice = 'relevant';
            $distribution = ['relevant' => 0.6, 'unrelated' => 0.1, 'unknown' => 0.3];
            $confidence = 0.4;
          }
        }
        $answers[$id] = new ChoiceAnswer($choice, $distribution, $confidence);
      }
      return new DecisionResponse($answers);
    });
    $result = (new RequirementPlanner($client))->plan(implode('. ', $texts), $site, ['areas' => ['equipment' => $area]]);
    $parts = $result['areas']['equipment']['parts'];
    $this->assertSame($texts, array_column($parts, 'text'));
    $this->assertSame('asset', $parts[1]['target']['bundle']);
    $this->assertSame('field_serial', $parts[1]['fields'][0]['name']);
    $this->assertSame('store', $parts[1]['fields'][0]['purpose']);
    $this->assertSame('asset', $parts[2]['target']['bundle']);
    $this->assertSame('filter_or_sort', $parts[2]['fields'][0]['purpose']);
    $this->assertSame('/admin/structure/fixture-listing', $parts[2]['option_links'][0]['url']);
    $this->assertSame([], $parts[4]['fields']);
    $this->assertSame('partial', $parts[4]['status']);
    $this->assertTrue($parts[4]['needs_review']);
    $steps = ImplementationStory::build($result['areas']['equipment']);
    $this->assertSame(['storage', 'listing', 'presentation'], array_column($steps, 'kind'));
    $this->assertCount(3, $steps[0]['parts']);
    $this->assertSame('asset', $steps[1]['target']['bundle']);

    // UI and MCP use the same connections; scores remain in full diagnostics.
    $area['requirements'] = $result['areas']['equipment'];
    $compact = AgentPlan::compact([
      'plan' => ['areas' => ['equipment' => $area]],
      'site' => $site,
      'status' => 'needs_clarification',
    ]);
    $item = $compact['work_areas'][0]['parts'][2];
    $this->assertSame('asset', $item['target']['bundle']);
    $this->assertSame('field_serial', $item['fields'][0]['name']);
    $this->assertArrayNotHasKey('judgment', $item['fields'][0]);
    $this->assertSame(1.0, $item['fields'][0]['evidence']['probability']);
    $this->assertSame(1.0, $item['target']['evidence']['confidence']);
    $this->assertSame('direct', $item['evidence']['coverage']['choice']);
    $this->assertSame('listing', $item['evidence']['component']['choice']);
    $this->assertFalse($compact['work_areas'][0]['integration_verified']);

    $parts[1]['kind_judgment']['needs_review'] = TRUE;
    $steps = ImplementationStory::build(['parts' => $parts]);
    $this->assertSame('check', end($steps)['kind']);
  }

  /**
   * Conflicting record selections cannot become confident field connections.
   */
  public function testConflictingRecordTargetsStayOpen(): void {
    $client = $this->createMock(DecisionClientInterface::class);
    $client->method('decide')->willReturnCallback(static function (DecisionInput $input): DecisionResponse {
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $choice = match (explode('__', $id)[0]) {
          'part_kind' => 'field',
          'part_option' => 'first',
          'part_target' => 'second',
          'part_fit' => 'direct',
        };
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, 1.0);
      }
      return new DecisionResponse($answers);
    });
    $site = ['bundles' => ['first' => ['fields' => []], 'second' => ['fields' => []]]];
    $area = [
      'source_text' => 'Store a value.',
      'options' => [['id' => 'first', 'label' => 'First', 'bundle_id' => 'first']],
    ];
    $result = (new RequirementPlanner($client))->plan('Store a value.', $site, ['areas' => ['area' => $area]]);
    $part = $result['areas']['area']['parts'][0];
    $this->assertNull($part['target']);
    $this->assertSame([], $part['fields']);
    $this->assertTrue($part['needs_review']);
  }

}
