<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;
use Drupal\ai_decision\Value\ChoiceAnswer;
use Drupal\ai_site_advisor\Assessment\ContentPlanningProfile;
use Drupal\ai_site_advisor\Assessment\DecisionClientInterface;
use Drupal\ai_site_advisor\Assessment\SiteAdvisor;
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
      $choice = $choices[$id];
      $probabilities = array_fill_keys($question->getOptionKeys(), 0.0);
      $probabilities[$choice] = 1.0;
      $answers[$id] = new ChoiceAnswer($choice, $probabilities, 1.0);
    }
    return new DecisionResponse(array_replace($answers, $overrides), 'test-model');
  }

  /**
   * Assesses against mocked boundaries.
   */
  private function assess(?callable $respond = NULL): array {
    $context = $this->createMock(SiteContextCollectorInterface::class);
    $context->expects($this->once())->method('collect')->willReturn($this->site());
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->method('discover')->willReturn(['items' => []]);
    $decision = $this->createMock(DecisionClientInterface::class);
    $decision->expects($this->once())->method('decide')->willReturnCallback($respond ?? fn ($input) => $this->response($input));
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('access ai site advisor')->willReturn(TRUE);
    return (new SiteAdvisor($context, $catalog, new ContentPlanningProfile(), $decision))->assess('Recurring workshops with date, location and capacity.', $account);
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
    (new SiteAdvisor($context, $this->createMock(CandidateCatalog::class), new ContentPlanningProfile(), $decision))->assess('Build workshops', $account);
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
    $this->assess(fn ($input) => new DecisionResponse([]));
  }

  /**
   * Rejects distributions that break the typed assessment contract.
   */
  #[DataProvider('malformedAnswers')]
  public function testMalformedDistribution(string $choice, array $distribution): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->assess(fn ($input) => $this->response($input, ['content_model' => new ChoiceAnswer($choice, $distribution, 1.0)]));
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

}
