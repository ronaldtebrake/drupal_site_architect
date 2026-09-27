<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Assessment\BuilderHandoff;
use PHPUnit\Framework\Attributes\Group;

/**
 * Actionable guidance is composed from evidence rather than package names.
 */
#[Group('site_architect')]
final class BuilderHandoffTest extends UnitTestCase {

  /**
   * A bundled recipe complements configuration and retains actual targets.
   */
  public function testRecipeAndConfigurationWorkTogether(): void {
    $links = [['label' => 'Edit record model', 'url' => '/known-route']];
    $site = [
      'configuration_areas' => [
        'fixture_type' => [
          'label' => 'Record models',
          'item_label' => 'Record model',
          'config_prefix' => 'fixture.type',
          'records' => ['one' => ['label' => 'Existing model', 'config_name' => 'fixture.type.one', 'links' => $links]],
        ],
      ],
    ];
    $options = [[
      'id' => 'configure',
      'kind' => 'configuration',
      'selected' => TRUE,
    ], [
      'id' => 'local',
      'kind' => 'recipe',
      'selected' => FALSE,
      'label' => 'Useful configuration',
      'package' => 'fixture/bundle',
      'availability' => 'local_code',
      'contribution' => ['choice' => 'foundation', 'needs_review' => FALSE],
      'configuration' => [
        ['name' => 'fixture.type.one', 'active_exists' => TRUE, 'operation' => 'provided'],
        ['name' => 'fixture.type.two', 'active_exists' => FALSE, 'operation' => 'provided'],
        ['name' => 'fixture.type.*', 'active_exists' => NULL, 'operation' => 'action'],
      ],
    ],
    ];
    $settings = ['choice' => 'fixture_type', 'needs_review' => FALSE];
    $guide = BuilderHandoff::build($site, $options, ['needs_review' => TRUE], $settings);
    $this->assertStringContainsString('Start by inspecting Record models', $guide['intro']);
    $this->assertStringContainsString('No specific implementation', $guide['intro']);
    $resource = $guide['resources'][0];
    $this->assertStringContainsString('local recipe', $resource['instruction']);
    $this->assertSame('Existing model', $resource['configuration'][0]['label']);
    $this->assertSame($links, $resource['configuration'][0]['links']);
    $this->assertSame($links, $resource['existing_models']['fixture.type.one']['links']);
    $this->assertSame(['existing' => 1, 'missing' => 1, 'unresolved' => 1], $resource['configuration_counts']);
    $this->assertStringContainsString('Already exists', $resource['configuration'][0]['instruction']);
    $this->assertStringContainsString('Not present', $resource['configuration'][1]['instruction']);
    $this->assertStringContainsString('prerequisites', $resource['configuration'][2]['instruction']);
    $question = BuilderHandoff::questions('custom_requirement', $site)['settings__custom_requirement'];
    $this->assertSame(['none', 'fixture_type'], $question->getOptionKeys());
    $this->assertStringContainsString('requirements.custom_requirement', $question->getInstructions());
    // A recipe whose explicit configuration names already exist is a reference,
    // not a reason to claim installation or application is still necessary.
    $options[0]['selected'] = FALSE;
    $options[1]['selected'] = TRUE;
    $options[1]['configuration'] = [$options[1]['configuration'][0]];
    $guide = BuilderHandoff::build($site, $options, ['needs_review' => FALSE], $settings);
    $this->assertStringContainsString('Start with the existing Existing model configuration', $guide['intro']);
    $this->assertStringContainsString('can serve as a reference', $guide['intro']);
  }

  /**
   * No evidence is an explicit gap, never an invented configuration route.
   */
  public function testMissingEvidenceDoesNotInventInstructions(): void {
    $guide = BuilderHandoff::build([], [], ['needs_review' => TRUE], NULL);
    $this->assertNull($guide['configuration_area']);
    $this->assertEmpty($guide['resources']);
    $this->assertStringContainsString('does not yet identify', $guide['intro']);
    $this->assertSame([], BuilderHandoff::questions('unknown', []));
  }

}
