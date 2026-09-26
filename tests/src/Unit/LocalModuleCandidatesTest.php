<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\AgentPlan;
use Drupal\ai_site_advisor\Assessment\CapabilityOptions;
use Drupal\ai_site_advisor\Assessment\ContentPlanningProfile;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\DecisionBatch;
use Drupal\ai_site_advisor\Assessment\LocalModuleCandidates;
use PHPUnit\Framework\Attributes\Group;

/**
 * Semantic screening preserves useful and uncertain local options.
 */
#[Group('ai_site_advisor')]
final class LocalModuleCandidatesTest extends UnitTestCase {

  /**
   * Modules do not depend on keyword overlap or Project Browser discovery.
   */
  public function testLocalOptionsReachEveryWorkArea(): void {
    $modules = [];
    foreach (['views', 'content_translation', 'locale', 'uncertain'] as $id) {
      $modules['module__' . $id] = [
        'id' => 'module__' . $id,
        'module_name' => $id,
        'label' => $id,
        'description' => 'Module metadata, not a search result.',
        'kind' => 'module',
        'package' => 'drupal/core',
        'core' => TRUE,
        'availability' => $id === 'views' ? 'enabled_module' : 'local_code',
        'links' => [['label' => 'Configure', 'url' => '/actual/configuration']],
      ];
    }
    $decision = $this->createMock(DecisionClientInterface::class);
    $decision->expects($this->once())->method('decide')->willReturnCallback(function ($input): DecisionResponse {
      $this->assertCount(4, $input->getQuestions());
      $this->assertArrayHasKey('module__content_translation', $input->getState()['modules']);
      $this->assertArrayNotHasKey('links', $input->getState()['modules']['module__content_translation']);
      return new DecisionResponse([
        'local__module__views' => new ChoiceAnswer('relevant', ['relevant' => 1.0, 'unrelated' => 0.0, 'unknown' => 0.0], 1.0),
        'local__module__content_translation' => new ChoiceAnswer('relevant', [
          'relevant' => 0.6,
          'unrelated' => 0.3,
          'unknown' => 0.1,
        ], 0.5),
        'local__module__locale' => new ChoiceAnswer('unrelated', [
          'relevant' => 0.0,
          'unrelated' => 1.0,
          'unknown' => 0.0,
        ], 1.0),
        'local__module__uncertain' => new ChoiceAnswer('unrelated', [
          'relevant' => 0.3,
          'unrelated' => 0.6,
          'unknown' => 0.1,
        ], 0.5),
      ], 'fixture', new TokenUsageDto(10, 4, 14));
    });
    $brief = 'A filtered overview and independently translated event records.';
    $result = LocalModuleCandidates::discover($brief, $modules, $decision);
    $this->assertCount(3, $result['items']);
    $this->assertCount(4, $result['answers']);
    $this->assertFalse($result['answers']['module__locale']['retained']);
    $this->assertSame(['input' => 10, 'output' => 4, 'total' => 14], $result['usage']);
    $site = ['bundles' => [], 'available_modules' => $modules];
    $capabilities = ['overview' => ['query' => 'overview'], 'translations' => ['query' => 'translated records']];
    $input = (new ContentPlanningProfile())->buildInput($brief, $site, $result['items'], $capabilities);
    foreach ($capabilities as $id => $capability) {
      $this->assertContains('module__views', $input->getQuestions()['plan__' . $id]->getOptionKeys());
      $this->assertContains('module__content_translation', $input->getQuestions()['plan__' . $id]->getOptionKeys());
      $this->assertArrayHasKey('role__' . $id . '__module__views', $input->getQuestions());
    }
    $sources = CapabilityOptions::sources($site, $result['items'], $capabilities['overview']);
    $this->assertSame($modules['module__views']['links'], $sources['module__views']['links']);
    foreach ($result['items'] as $module) {
      $compact = AgentPlan::candidate($module);
      $this->assertSame(['action' => 'code_available'], $compact['if_selected']['acquire']);
      $this->assertSame($module['module_name'], $compact['module_name']);
      $this->assertTrue($compact['core']);
      $this->assertSame($module['links'], $compact['configure']);
    }
  }

  /**
   * Repacking keeps every choice without copying large output-only inventories.
   */
  public function testLargeLocalInventoryFitsScoringBatches(): void {
    $modules = [];
    for ($i = 0; $i < 80; $i++) {
      $modules['module__fixture_' . $i] = [
        'id' => 'module__fixture_' . $i,
        'module_name' => 'fixture_' . $i,
        'label' => 'Fixture ' . $i,
        'description' => str_repeat('Useful module evidence. ', 8),
        'kind' => 'module',
        'core' => TRUE,
        'package' => 'drupal/core',
        'availability' => 'local_code',
        'links' => [['label' => 'Configure', 'url' => str_repeat('/route', 1000)]],
        'source' => str_repeat('source-path/', 1000),
      ];
    }
    $site = ['bundles' => [], 'available_modules' => $modules, 'enabled_modules' => $modules];
    $capabilities = ['one' => ['query' => 'overview'], 'two' => ['query' => 'another capability']];
    $inputs = (new ContentPlanningProfile())->buildInputs('A complete brief with several requirements.', $site, $modules, $capabilities);
    $ids = [];
    foreach ($inputs as $input) {
      $this->assertLessThanOrEqual(100000, DecisionBatch::bytes($input));
      $this->assertArrayNotHasKey('available_modules', $input->getState()['site']);
      $this->assertArrayNotHasKey('enabled_modules', $input->getState()['site']);
      foreach ($input->getQuestions() as $id => $question) {
        $this->assertNotContains($id, $ids);
        $ids[] = $id;
        if (str_starts_with($id, 'plan__')) {
          $this->assertCount(82, $question->getOptionKeys());
        }
      }
      foreach ($input->getState()['recipes'] as $module) {
        $this->assertArrayNotHasKey('links', $module);
        $this->assertArrayHasKey('description', $module);
      }
    }
    $this->assertCount(160, array_filter($ids, static fn ($id) => str_starts_with($id, 'role__')));
    $this->assertCount(80, $site['available_modules']);
    $this->assertArrayHasKey('links', $modules['module__fixture_0']);
  }

}
