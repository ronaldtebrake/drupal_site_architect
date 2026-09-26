<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_site_advisor\Assessment\AgentPlan;
use PHPUnit\Framework\Attributes\Group;

/**
 * Compact handoffs retain useful choices, uncertainty and acquisition context.
 */
#[Group('ai_site_advisor')]
final class AgentPlanTest extends UnitTestCase {

  /**
   * Contribution is independent of starting-point probability.
   */
  public function testShortlistAndConfigurationPointers(): void {
    $preferred = $this->option('preferred', 'foundation', 0.8, TRUE);
    $preferred['selected'] = TRUE;
    $addition = $this->option('addition', 'complement', 0.97);
    $addition['probability'] = 0.01;
    $options = [
      $preferred,
      $addition,
      $this->option('second', 'complement', 0.91),
      $this->option('third', 'complement', 0.88),
      $this->option('unrelated', 'unrelated', 0.99),
    ];
    $links = [['label' => 'Manage fields', 'url' => '/actual/fields']];
    $area = [
      'label' => 'Requested capability',
      'status' => 'open',
      'selection' => ['id' => 'preferred', 'needs_review' => TRUE],
      'options' => $options,
      'handoff' => [
        'configuration_area' => ['label' => 'Record models', 'links' => $links],
        'configuration_needs_review' => TRUE,
        'resources' => [[
          'option_id' => 'preferred',
          'existing_models' => ['fixture.type.one' => ['label' => 'Existing record', 'links' => $links]],
        ],
        ],
      ],
      'check' => 'Confirm the relationship with existing records.',
    ];
    $assessment = [
      'status' => 'needs_clarification',
      'site' => ['fingerprint' => 'site-hash', 'fields' => ['full' => 'field evidence']],
      'plan' => ['areas' => ['first' => $area, 'second' => $area]],
      'discovery' => ['searched_ecosystem' => TRUE, 'truncated' => TRUE, 'warnings' => ['Source unavailable.']],
      'search_plan' => ['coverage' => ['segments_processed' => 14, 'segments_total' => 14]],
      'questions' => ['verbose' => str_repeat('Evidence repeated across questions. ', 1000)],
    ];
    $compact = AgentPlan::compact($assessment);
    $this->assertTrue($compact['needs_review']);
    $this->assertCount(2, $compact['work_areas']);
    $this->assertCount(3, $compact['candidates']);
    $this->assertArrayHasKey('addition', $compact['candidates']);
    $this->assertArrayNotHasKey('third', $compact['candidates']);
    $this->assertArrayNotHasKey('unrelated', $compact['candidates']);
    $first = $compact['work_areas'][0];
    $this->assertSame(['kind' => 'undecided', 'needs_review' => TRUE], $first['starting_point']);
    $this->assertSame('addition', $first['consider'][0]['candidate']);
    $this->assertFalse($first['consider'][0]['needs_review']);
    $this->assertSame(2, $first['other_package_options']);
    $this->assertEmpty($first['existing_configuration']);
    $this->assertSame($links, $first['configure']['links']);
    $this->assertTrue($first['configure']['needs_review']);
    $this->assertSame($area['check'], $first['resolve_before_building']);
    $this->assertTrue($first['check_needs_review']);
    $this->assertTrue($compact['discovery']['truncated']);
    $this->assertSame(['Source unavailable.'], $compact['discovery']['warnings']);
    $this->assertSame($assessment['search_plan']['coverage'], $compact['discovery']['coverage']);
    $this->assertArrayNotHasKey('site', $compact);
    $this->assertArrayNotHasKey('questions', $compact);
    $this->assertArrayNotHasKey('description', $compact['candidates']['preferred']);
    $this->assertLessThan(strlen(json_encode($assessment)) / 4, strlen(json_encode($compact)));

    // An undecided work area must not disappear because no package was chosen.
    $assessment['plan']['areas']['first']['options'] = [];
    $assessment['plan']['areas']['first']['selection']['id'] = 'missing';
    $first = AgentPlan::compact($assessment)['work_areas'][0];
    $this->assertSame('undecided', $first['starting_point']['kind']);
    $this->assertEmpty($first['consider']);

    // Reused content types retain their actual field/configuration routes.
    $assessment['site']['configuration_areas']['node_type']['records']['fixture'] = [
      'config_name' => 'node.type.fixture',
      'label' => 'Fixture',
      'links' => $links,
    ];
    $assessment['plan']['areas']['first']['options'] = [[
      'id' => 'reuse',
      'bundle_id' => 'fixture',
      'label' => 'Fixture',
      'contribution' => ['choice' => 'foundation', 'needs_review' => FALSE],
    ],
    ];
    $assessment['plan']['areas']['first']['selection']['id'] = 'reuse';
    $assessment['plan']['areas']['first']['selection']['needs_review'] = FALSE;
    $first = AgentPlan::compact($assessment)['work_areas'][0];
    $this->assertSame('existing_content_type', $first['starting_point']['kind']);
    $this->assertSame('node.type.fixture', $first['existing_configuration'][0]['config']);
    $this->assertSame($links, $first['existing_configuration'][0]['links']);
  }

  /**
   * Composer applies to external candidates, not already available code.
   */
  public function testAcquisitionAndDiscovery(): void {
    $option = $this->option('sample', 'foundation', 0.9);
    foreach (['module', 'recipe'] as $kind) {
      $option['kind'] = $kind;
      $candidate = AgentPlan::candidate($option);
      $this->assertSame(['composer', 'require', 'fixture/sample'], $candidate['if_selected']['acquire']['argv']);
      foreach (['local_code', 'enabled_module'] as $availability) {
        $local = AgentPlan::candidate(array_replace($option, ['availability' => $availability]));
        $this->assertSame(['action' => 'code_available'], $local['if_selected']['acquire']);
      }
    }
    $option['availability'] = 'local_code';
    $option['package'] = 'drupal/core';
    $option['source'] = 'core/recipes/fixture/recipe.yml';
    $option['configuration'] = [['active_exists' => TRUE], ['active_exists' => FALSE], ['active_exists' => NULL]];
    $local = AgentPlan::candidate($option);
    $this->assertSame('core/recipes/fixture/recipe.yml', $local['manifest_reference']);
    $this->assertSame(['existing' => 1, 'missing' => 1, 'unresolved' => 1], $local['explicit_configuration']);
    $this->assertStringContainsString('do not prove the recipe was applied', $local['if_selected']['then']);
    foreach (['fixture/package;command', '--option', 'fixture/package extra'] as $invalid) {
      $unknown = AgentPlan::candidate(array_replace($option, ['availability' => 'catalog_only', 'package' => $invalid]));
      $this->assertSame(['action' => 'inspect_acquisition'], $unknown['if_selected']['acquire']);
    }
    $discovery = AgentPlan::discovery([
      'query' => 'fixture',
      'items' => ['sample' => $option],
      'truncated' => TRUE,
      'warnings' => ['Partial search.'],
    ]);
    $this->assertSame(['sample' => $local], $discovery['items']);
    $this->assertSame(['Partial search.'], $discovery['warnings']);
    $this->assertTrue($discovery['truncated']);
  }

  /**
   * A source candidate with deliberately verbose evidence.
   */
  private function option(string $id, string $role, float $score, bool $review = FALSE): array {
    return [
      'id' => $id,
      'label' => ucfirst($id),
      'kind' => 'module',
      'package' => 'fixture/' . $id,
      'availability' => 'catalog_only',
      'url' => 'https://example.com/' . $id,
      'selected' => FALSE,
      'description' => str_repeat('Catalog description. ', 100),
      'contribution' => [
        'choice' => $role,
        'needs_review' => $review,
        'probabilities' => [$role => $score],
      ],
    ];
  }

}
