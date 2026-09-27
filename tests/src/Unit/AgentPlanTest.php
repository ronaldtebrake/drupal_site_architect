<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Assessment\AgentPlan;
use PHPUnit\Framework\Attributes\Group;

/**
 * Compact handoffs retain useful choices, uncertainty and acquisition context.
 */
#[Group('site_architect')]
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
      'selection' => ['id' => 'preferred', 'probability' => 0.6, 'confidence' => 0.4, 'needs_review' => TRUE],
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
      'search_plan' => [
        'coverage' => ['segments_processed' => 14, 'segments_total' => 14],
        'action' => 'search',
        'reason' => 'Compare available options.',
        'needs_review' => FALSE,
        'answers' => [
          'ecosystem_search' => [
            'choice' => 'search',
            'probabilities' => ['search' => 0.9, 'local' => 0.1, 'clarify' => 0.0],
            'confidence' => 0.85,
          ],
        ],
      ],
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
    $this->assertSame(0.97, $first['consider'][0]['evidence']['probability']);
    $this->assertNull($first['consider'][0]['evidence']['confidence'], 'Missing confidence is not inferred from probability.');
    $this->assertSame(0.6, $first['assessed_preference']['probability']);
    $this->assertTrue($first['assessed_preference']['needs_review']);
    $this->assertArrayNotHasKey('probabilities', $first['consider'][0]['evidence']);
    $this->assertSame(2, $first['other_package_options']);
    $this->assertEmpty($first['existing_configuration']);
    $this->assertSame($links, $first['configure']['links']);
    $this->assertTrue($first['configure']['needs_review']);
    $this->assertSame($area['check'], $first['resolve_before_building']);
    $this->assertTrue($first['check_needs_review']);
    $this->assertTrue($compact['discovery']['truncated']);
    $this->assertSame(['Source unavailable.'], $compact['discovery']['warnings']);
    $this->assertSame($assessment['search_plan']['coverage'], $compact['discovery']['coverage']);
    $this->assertSame('search', $compact['discovery']['action']);
    $this->assertSame('Compare available options.', $compact['discovery']['reason']);
    $this->assertSame(['choice' => 'search', 'probability' => 0.9, 'confidence' => 0.85, 'needs_review' => FALSE], $compact['discovery']['search_choice']);
    $this->assertArrayNotHasKey('site', $compact);
    $this->assertArrayNotHasKey('questions', $compact);
    $this->assertArrayNotHasKey('description', $compact['candidates']['preferred']);
    $this->assertSame(480, mb_strlen($compact['candidates']['preferred']['source_excerpt']));
    $this->assertTrue($compact['candidates']['preferred']['excerpt_truncated']);
    $this->assertSame('continue_planning', $compact['continuation']['stage']);
    $this->assertStringContainsString('Requested capability', $compact['continuation']['decisions'][0]['question']);
    $this->assertSame(['preferred', 'addition', 'second'], $compact['continuation']['decisions'][0]['candidate_refs']);
    $this->assertSame(['present_plan', 'ask_user', 'reassess_after_answer'], array_column($compact['continuation']['next_actions'], 'action'));
    $this->assertLessThan(strlen(json_encode($assessment)) / 4, strlen(json_encode($compact)));

    // A tentative vote can fall outside contribution-based shortlists. Its
    // evidence must still be available, without silently recommending it.
    $tentative = $assessment;
    $tentative['plan']['areas']['first']['selection']['id'] = 'third';
    $compact_tentative = AgentPlan::compact($tentative);
    $this->assertArrayHasKey('third', $compact_tentative['candidates']);
    $this->assertSame('undecided', $compact_tentative['work_areas'][0]['starting_point']['kind']);
    $this->assertSame('third', $compact_tentative['continuation']['decisions'][0]['candidate_refs'][0]);
    $this->assertSame(1, $compact_tentative['work_areas'][0]['other_package_options']);

    // An uncertain check must not make the question assume a record model.
    $uncertain_check = $assessment;
    $uncertain_check['plan']['areas']['first']['check_kind'] = 'content';
    $uncertain_check['plan']['areas']['first']['check_needs_review'] = TRUE;
    $question = AgentPlan::compact($uncertain_check)['continuation']['decisions'][0]['question'];
    $this->assertStringContainsString('What should someone be able to do', $question);
    $uncertain_check['plan']['areas']['first']['check_kind'] = 'access';
    $uncertain_check['plan']['areas']['first']['check_needs_review'] = FALSE;
    $question = AgentPlan::compact($uncertain_check)['continuation']['decisions'][0]['question'];
    $this->assertStringContainsString('Who should be able to view', $question);

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
    unset($assessment['plan']['areas']['second']);
    $assessment['status'] = 'assessed';
    $confirmed = AgentPlan::compact($assessment)['continuation'];
    $this->assertSame('review_plan', $confirmed['stage']);
    $this->assertSame([], $confirmed['decisions']);
    $this->assertSame(['present_plan', 'inspect_before_building'], array_column($confirmed['next_actions'], 'action'));
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
    $this->assertSame('compare_candidates', $discovery['continuation']['stage']);
    $this->assertSame('fixture', $discovery['continuation']['next_actions'][1]['catalog_query_if_applicable']);
  }

  /**
   * Excerpts stay bounded without manufacturing missing evidence.
   */
  public function testSourceExcerptsArePlainEvidence(): void {
    $option = $this->option('sample', 'foundation', 0.9);
    $option['description'] = '<p>Groups &amp; membership.</p>  <p>Separate access.</p>';
    $candidate = AgentPlan::candidate($option);
    $this->assertSame('Groups & membership. Separate access.', $candidate['source_excerpt']);
    $this->assertFalse($candidate['excerpt_truncated']);
    unset($option['description']);
    $this->assertArrayNotHasKey('source_excerpt', AgentPlan::candidate($option));
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
