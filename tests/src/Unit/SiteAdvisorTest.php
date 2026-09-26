<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\ContentPlanningProfile;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\SiteAdvisor;
use Drupal\ai_site_advisor\Assessment\RequirementPlannerInterface;
use Drupal\ai_site_advisor\Assessment\SearchPlannerInterface;
use Drupal\ai_site_advisor\Context\CandidateCatalog;
use Drupal\ai_site_advisor\Context\SiteContextCollectorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests orchestration contracts without inference calls or model evals.
 */
#[Group('ai_site_advisor')]
final class SiteAdvisorTest extends UnitTestCase {

  /**
   * Returns a minimal evidence packet.
   */
  private function site(bool $canvas = TRUE): array {
    return [
      'bundles' => ['workshop' => ['label' => 'Workshop', 'fields' => []]],
      'enabled_features' => ['canvas' => $canvas],
      'site_policy' => 'Reuse suitable existing content types.',
    ];
  }

  /**
   * Creates complete answers with explicit overrides for edge cases.
   */
  private function response(DecisionInput $input, array $overrides = []): DecisionResponse {
    $choices = ['content_model' => 'records', 'presentation' => 'canvas_template', 'bundle__workshop' => 'ready'];
    $answers = [];
    foreach ($input->getQuestions() as $id => $question) {
      $choice = $choices[$id] ?? (str_starts_with($id, 'plan__') ? 'bundle__workshop' : (str_starts_with($id, 'role__') ? 'foundation' : 'scope'));
      $probabilities = array_fill_keys($question->getOptionKeys(), 0.0);
      $probabilities[$choice] = 1.0;
      $answers[$id] = new ChoiceAnswer($choice, $probabilities, 1.0);
    }
    return new DecisionResponse(array_replace($answers, $overrides), 'test-model');
  }

  /**
   * Assesses against mocked boundaries.
   */
  private function assess(?callable $respond = NULL, int $expected_requests = 1): array {
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->expects($this->once())->method('collect')->willReturn($this->site());
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->method('discover')->willReturn(['items' => []]);
    $decision = $this->createMock(DecisionClientInterface::class);
    $decision->expects($this->exactly($expected_requests))->method('decide')->willReturnCallback($respond ?? fn ($input) => $this->response($input));
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('access ai site advisor')->willReturn(TRUE);
    $planner = $this->createMock(SearchPlannerInterface::class);
    $planner->expects($this->never())->method('plan');
    return (new SiteAdvisor($context, $catalog, new ContentPlanningProfile(), $decision, $planner, $this->createMock(RequirementPlannerInterface::class)))->assess('Recurring workshops with date, location and capacity.', $account);
  }

  /**
   * Unauthorized callers cannot inspect evidence or incur provider costs.
   */
  public function testAccessBeforeInspectionAndInference(): void {
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->expects($this->never())->method('collect');
    $decision = $this->createMock(DecisionClientInterface::class);
    $decision->expects($this->never())->method('decide');
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(FALSE);
    $this->expectException(AccessDeniedHttpException::class);
    $planner = $this->createMock(SearchPlannerInterface::class);
    $planner->expects($this->never())->method('plan');
    (new SiteAdvisor($context, $this->createMock(CandidateCatalog::class), new ContentPlanningProfile(), $decision, $planner, $this->createMock(RequirementPlannerInterface::class)))->assess('Build workshops', $account);
  }

  /**
   * Advice carries candidates, exact questions and unknown usage honestly.
   */
  public function testReusableRecordsWithIndependentPresentation(): void {
    $result = $this->assess();
    $this->assertSame(['workshop'], $result['reuse_candidates']);
    $this->assertSame('assessed', $result['status']);
    $this->assertSame('Start with existing content: Workshop.', $result['summary']);
    $this->assertNull($result['usage']['input']);
    $this->assertArrayHasKey('bundle__workshop', $result['questions']);
  }

  /**
   * A presentation doubt must not hide confident content reuse advice.
   */
  public function testUncertaintyAsksTheSpecificQuestion(): void {
    $result = $this->assess(fn ($input) => $this->response($input, [
      'presentation' => new ChoiceAnswer('canvas_template', [
        'drupal_display' => 0.4,
        'unclear' => 0.0,
        'canvas_template' => 0.6,
        'canvas_page' => 0.0,
        'canvas_both' => 0.0,
        'not_applicable' => 0.0,
      ], 0.2),
    ]));
    $this->assertSame('needs_clarification', $result['status']);
    $this->assertSame(['workshop'], $result['reuse_candidates']);
    $this->assertStringContainsString('Workshop', $result['summary']);
    $this->assertStringContainsString('Choose the presentation', $result['follow_up']);
  }

  /**
   * Contradictory independent answers require clarification.
   */
  public function testContradictoryJudgments(): void {
    $result = $this->assess(fn ($input) => $this->response($input, [
      'presentation' => new ChoiceAnswer('canvas_page', [
        'drupal_display' => 0.0,
        'unclear' => 0.0,
        'canvas_template' => 0.0,
        'canvas_page' => 1.0,
        'canvas_both' => 0.0,
        'not_applicable' => 0.0,
      ], 1.0),
    ]));
    $this->assertTrue($result['contradictory_judgments']);
    $this->assertSame('needs_clarification', $result['status']);
    $this->assertTrue($result['answers']['presentation']['needs_review']);
  }

  /**
   * An incomplete provider response cannot yield partial confident advice.
   */
  public function testMissingAnswerIsRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->assess(fn ($input) => new DecisionResponse([]), 2);
  }

  /**
   * Rejects distributions that break the typed assessment contract.
   */
  #[DataProvider('malformedAnswers')]
  public function testMalformedDistribution(string $choice, array $distribution): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->assess(fn ($input) => $this->response($input, ['content_model' => new ChoiceAnswer($choice, $distribution, 1.0)]), 2);
  }

  /**
   * Supplies malformed but constructible upstream answer objects.
   */
  public static function malformedAnswers(): array {
    return [
      'missing options' => ['records', ['records' => 1.0]],
      'invented option' => [
        'invented',
        ['records' => 0.0, 'page' => 0.0, 'mixed' => 0.0, 'unclear' => 0.0, 'invented' => 1.0],
      ],
      'bad sum' => [
        'records',
        ['records' => 0.8, 'page' => 0.4, 'mixed' => 0.0, 'unclear' => 0.0, 'not_applicable' => 0.0],
      ],
      'wrong winner' => [
        'records',
        ['records' => 0.1, 'page' => 0.9, 'mixed' => 0.0, 'unclear' => 0.0, 'not_applicable' => 0.0],
      ],
    ];
  }

  /**
   * Missing Canvas never becomes a selectable capability.
   */
  public function testCoreOnlyPresentationOptions(): void {
    $input = (new ContentPlanningProfile())->buildInput('A visual page', $this->site(FALSE), []);
    $this->assertSame(['drupal_display', 'unclear', 'not_applicable'], $input->getQuestions()['presentation']->getOptionKeys());
  }

  /**
   * The route controls remote discovery and usage includes both model stages.
   */
  #[DataProvider('searchPlans')]
  public function testConditionalDiscovery(string $action, bool $search, bool $review): void {
    $brief = 'Reuse workshops and compare workflow options if useful.';
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->method('collect')->willReturn($this->site());
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->method('hasRemoteSources')->willReturn(TRUE);
    $catalog->expects($this->once())->method('discover')->with($search ? 'workflow' : $brief, $account, 12, $search)->willReturn(['items' => []]);
    $planner = $this->createMock(SearchPlannerInterface::class);
    $planner->expects($this->once())->method('plan')->with($brief, $this->site())->willReturn([
      'action' => $action,
      'query' => $search ? 'workflow' : NULL,
      'reason' => 'Fixture search decision.',
      'needs_review' => $review,
      'usage' => ['input' => 5, 'output' => 1, 'total' => 6],
    ]);
    $decision = $this->createMock(DecisionClientInterface::class);
    $decision->method('decide')->willReturnCallback(fn ($input) => new DecisionResponse($this->response($input)->getAnswers(), 'test-model', new TokenUsageDto(20, 3, 23)));
    $result = (new SiteAdvisor($context, $catalog, new ContentPlanningProfile(), $decision, $planner, $this->createMock(RequirementPlannerInterface::class)))->assess($brief, $account);
    $this->assertSame($search, $result['discovery']['searched_ecosystem']);
    $this->assertSame($action, $result['search_plan']['action']);
    $this->assertSame($review ? 'needs_clarification' : 'assessed', $result['status']);
    $this->assertSame(['input' => 25, 'output' => 4, 'total' => 29], $result['usage']);
    $this->assertSame(20, $result['usage_by_stage']['assessment']['input']);
  }

  /**
   * Covers all routing outcomes, including the no-search branches.
   */
  public static function searchPlans(): array {
    return [
      'search' => ['search', TRUE, FALSE],
      'reuse' => ['local', FALSE, FALSE],
      'clarify' => ['clarify', FALSE, TRUE],
    ];
  }

  /**
   * Existing callers can explicitly supply a query without a planning call.
   */
  public function testExplicitSearchOverride(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->method('collect')->willReturn($this->site());
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->expects($this->once())->method('discover')->with('workflow', $account, 12, TRUE)->willReturn(['items' => []]);
    $planner = $this->createMock(SearchPlannerInterface::class);
    $planner->expects($this->never())->method('plan');
    $decision = $this->createMock(DecisionClientInterface::class);
    $decision->method('decide')->willReturnCallback(fn ($input) => $this->response($input));
    $result = (new SiteAdvisor($context, $catalog, new ContentPlanningProfile(), $decision, $planner, $this->createMock(RequirementPlannerInterface::class)))->assess('An editorial workflow.', $account, 'workflow');
    $this->assertNull($result['usage_by_stage']['search_planning']);
    $this->assertSame('workflow', $result['search_plan']['query']);
  }

  /**
   * Local screening feeds assessment and counts usage without remote discovery.
   */
  public function testLocalModuleAssessmentAndUsage(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $site = $this->site();
    $site['available_modules']['module__fixture'] = [
      'id' => 'module__fixture',
      'machine_name' => 'fixture',
      'module_name' => 'fixture',
      'label' => 'Existing capability',
      'description' => 'A capability provided locally.',
      'kind' => 'module',
      'package' => 'drupal/core',
      'core' => TRUE,
      'availability' => 'local_code',
    ];
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->method('collect')->willReturn($site);
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->method('discover')->willReturn(['items' => []]);
    $planner = $this->createMock(SearchPlannerInterface::class);
    $planner->expects($this->never())->method('plan');
    $decision = $this->createMock(DecisionClientInterface::class);
    $calls = 0;
    $decision->method('decide')->willReturnCallback(function (DecisionInput $input) use (&$calls): DecisionResponse {
      $calls++;
      $answers = [];
      foreach ($input->getQuestions() as $id => $question) {
        $choice = match (TRUE) {
          str_starts_with($id, 'local__'), str_starts_with($id, 'recipe__') => 'relevant',
          str_starts_with($id, 'plan__') => 'module__fixture',
          str_starts_with($id, 'role__') => 'foundation',
          str_starts_with($id, 'bundle__') => 'unrelated',
          str_starts_with($id, 'check__') => 'integration',
          $id === 'content_model' => 'not_applicable',
          default => 'drupal_display',
        };
        $distribution = array_fill_keys($question->getOptionKeys(), 0.0);
        $distribution[$choice] = 1.0;
        $answers[$id] = new ChoiceAnswer($choice, $distribution, 1.0);
      }
      return new DecisionResponse($answers, 'fixture', new TokenUsageDto(10, 2, 12));
    });
    $result = (new SiteAdvisor($context, $catalog, new ContentPlanningProfile(), $decision, $planner, $this->createMock(RequirementPlannerInterface::class)))->assess('Use a suitable existing capability.', $account);
    $this->assertSame(2, $calls);
    $this->assertSame(['input' => 20, 'output' => 4, 'total' => 24], $result['usage']);
    $this->assertSame(10, $result['usage_by_stage']['local_discovery']['input']);
    $this->assertCount(1, $result['requests_by_stage']['local_discovery']);
    $this->assertSame('module__fixture', $result['plan']['areas']['brief']['selection']['id']);
    $this->assertSame('Core module', $result['plan']['areas']['brief']['handoff']['resources'][0]['kind']);
    $this->assertStringContainsString('No Composer download', $result['plan']['areas']['brief']['handoff']['resources'][0]['instruction']);
    $this->assertFalse($result['discovery']['searched_ecosystem']);
  }

}
