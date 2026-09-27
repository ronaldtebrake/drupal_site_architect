<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\ContentPlanningProfile;
use Drupal\ai_site_advisor\Assessment\DecisionBatch;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\SearchPlanner;
use Drupal\ai_site_advisor\Assessment\SiteAdvisor;
use Drupal\ai_site_advisor\Assessment\RequirementPlanner;
use Drupal\ai_site_advisor\Context\CandidateCatalog;
use Drupal\ai_site_advisor\Context\CatalogSourceInterface;
use Drupal\ai_site_advisor\Context\SiteContextCollectorInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises extraction, retrieval and assessment together without live calls.
 */
#[Group('ai_site_advisor')]
final class LongPlanTest extends UnitTestCase {

  /**
   * Batching preserves later requirements, alternatives and aggregate usage.
   */
  public function testLongPlanAcrossAllStages(): void {
    $features = [
      'events', 'topics', 'groups', 'notifications', 'search', 'translation',
      'media', 'moderation', 'profiles', 'registration', 'calendar', 'surveys',
      'bookmarks', 'payments',
    ];
    $brief = implode('. ', $features) . '. ' . str_repeat('background ', 600);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->method('collect')->willReturn([
      'bundles' => [],
      'configuration_areas' => [
        'fixture' => [
          'label' => 'Fixture settings',
          'item_label' => 'Fixture',
          'config_prefix' => 'fixture.type',
          'records' => [],
        ],
      ],
    ]);
    $source = $this->createMock(CatalogSourceInterface::class);
    $source->method('isRemote')->willReturn(TRUE);
    $searched = [];
    $source->method('search')->willReturnCallback(static function ($query) use (&$searched): array {
      $searched[] = $query;
      $items = [];
      for ($i = 0; $i < 12; $i++) {
        $items[] = [
          'kind' => 'module',
          'package' => 'fixture/' . $query . '_' . $i,
          'label' => $query,
          'machine_name' => $query . '_' . $i,
          'description' => str_repeat('Source evidence. ', 150),
          'source' => 'fixture',
          'availability' => 'catalog_only',
          'url' => NULL,
        ];
      }
      return ['items' => $items, 'sources' => [], 'warnings' => []];
    });
    $catalog = new CandidateCatalog();
    $catalog->addSource($source);
    $decision = $this->createMock(DecisionClientInterface::class);
    $calls = 0;
    $asked = [];
    $decision->method('decide')->willReturnCallback(function (DecisionInput $input) use ($brief, $features, &$calls, &$asked): DecisionResponse {
      $calls++;
      $this->assertSame(trim($brief), $input->getState()['brief']);
      $this->assertLessThanOrEqual(100000, DecisionBatch::bytes($input));
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $this->assertNotContains($id, $asked, 'Questions must not be reassessed in later batches.');
        $asked[] = $id;
        $choice = match (TRUE) {
          $id === 'ecosystem_search' => 'search',
          str_starts_with($id, 'scope_') => 'work_area',
          str_starts_with($id, 'group_') => 'separate',
          $id === 'content_model' => 'records',
          $id === 'presentation' => 'drupal_display',
          str_starts_with($id, 'recipe__') => 'relevant',
          str_starts_with($id, 'check__') => 'integration',
          str_starts_with($id, 'role__') => 'foundation',
          str_starts_with($id, 'settings__') => 'fixture',
          str_starts_with($id, 'part_kind__') => 'capability',
          str_starts_with($id, 'part_fit__') => 'direct',
          str_starts_with($id, 'plan__') => array_keys($input->getState()['recipes'])[0],
          default => 'none',
        };
        if (str_starts_with($id, 'plan__') || str_starts_with($id, 'part_option__')) {
          $choices = array_filter($question->getOptionKeys(), static fn ($key) => str_starts_with($key, 'c_'));
          $choice = reset($choices);
          $this->assertCount(12, $choices, 'Every work area retains its full candidate shortlist.');
        }
        if (str_starts_with($id, 'capability_')) {
          foreach ($features as $feature) {
            if (($key = array_search($feature, $question->getCriteria(), TRUE)) !== FALSE) {
              $choice = $key;
              break;
            }
          }
        }
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, 1.0);
      }
      return new DecisionResponse($answers, 'fixture', new TokenUsageDto(10, 2, 12));
    });
    $advisor = new SiteAdvisor($context, $catalog, new ContentPlanningProfile(), $decision, new SearchPlanner($decision), new RequirementPlanner($decision));
    $result = $advisor->assess($brief, $account);
    $this->assertCount(14, $searched);
    $this->assertContains('payment', $searched);
    $this->assertCount(168, $result['candidates']);
    $this->assertCount(14, $result['plan']['areas']);
    $this->assertSame('payments', end($result['plan']['areas'])['label']);
    $this->assertSame('Fixture settings', end($result['plan']['areas'])['handoff']['configuration_area']['label']);
    $this->assertCount(14, array_filter($asked, static fn ($id) => str_starts_with($id, 'settings__')));
    $this->assertSame('fixture/payment_0', end($result['plan']['areas'])['package']);
    $this->assertGreaterThan(1, count($result['requests_by_stage']['assessment']));
    $this->assertSame($calls * 10, $result['usage']['input']);
    $this->assertSame($calls * 2, $result['usage']['output']);
    $this->assertSame($calls, count($result['requests_by_stage']['assessment']) + count($result['requests_by_stage']['search_planning']) + count($result['requests_by_stage']['requirement_planning']));
    $this->assertSame('supported', end($result['plan']['areas'])['requirements']['parts'][0]['status']);
    $this->assertFalse(end($result['plan']['areas'])['requirements']['integration_verified']);
  }

}
