<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Assessment\CapabilityOptions;
use Drupal\site_architect\Assessment\CapabilityPlan;
use PHPUnit\Framework\Attributes\Group;

/**
 * Regressions for useful building blocks hidden by a competing choice score.
 */
#[Group('site_architect')]
final class CapabilityOptionsTest extends UnitTestCase {

  /**
   * Creates fixture judgments, not claims about live model quality.
   */
  private function role(string $choice): array {
    return [
      'choice' => $choice,
      'probabilities' => array_replace(array_fill_keys(array_keys(CapabilityOptions::ROLES), 0.0), [$choice => 1.0]),
      'confidence' => 1.0,
      'needs_review' => FALSE,
      'criterion' => CapabilityOptions::ROLES[$choice],
    ];
  }

  /**
   * A relevant module remains visible with a low starting-point score.
   */
  public function testUsefulOptionsSurviveSelectionThreshold(): void {
    $site = ['bundles' => ['workshop' => ['label' => 'Workshop', 'fields' => ['date' => ['label' => 'Date']]]]];
    $candidates = [];
    $labels = [
      'recurring' => 'Recurring Events',
      'field' => 'Recurrence field fixture',
      'dispatcher' => 'Programming events fixture',
      'extra' => 'Other fixture',
    ];
    foreach ($labels as $id => $label) {
      $candidates[$id] = [
        'id' => $id,
        'label' => $label,
        'package' => 'fixture/' . $id,
        'description' => 'Fixture source description for ' . $label,
        'matched_queries' => ['event'],
      ];
    }
    $capabilities = ['events' => ['label' => 'events', 'query' => 'event']];
    $answers = [
      'plan__events' => [
        'choice' => 'bundle__workshop',
        'probabilities' => [
          'bundle__workshop' => 0.73,
          'configure' => 0.19,
          'recurring' => 0.06,
          'field' => 0.01,
          'dispatcher' => 0.0,
          'extra' => 0.0,
          'unresolved' => 0.01,
        ],
        'confidence' => 0.72,
        'needs_review' => TRUE,
        'criterion' => 'Existing Workshop type.',
      ],
      'check__events' => ['choice' => 'content', 'needs_review' => FALSE],
      'recipe__recurring' => [
        'choice' => 'relevant',
        'probabilities' => ['relevant' => 0.94, 'unrelated' => 0.06, 'unknown' => 0.0],
        'needs_review' => FALSE,
      ],
      // Relevance to a different work area must not imply a useful role here.
      'recipe__dispatcher' => [
        'choice' => 'relevant',
        'probabilities' => ['relevant' => 0.99, 'unrelated' => 0.01, 'unknown' => 0.0],
        'needs_review' => FALSE,
      ],
      'role__events__bundle__workshop' => $this->role('foundation'),
      'role__events__configure' => $this->role('foundation'),
      'role__events__recurring' => $this->role('foundation'),
      'role__events__field' => $this->role('complement'),
      'role__events__dispatcher' => $this->role('unrelated'),
      'role__events__extra' => $this->role('unknown'),
    ];
    $area = CapabilityPlan::build($site, $candidates, $capabilities, $answers)['areas']['events'];
    $options = array_column($area['options'], NULL, 'id');
    $this->assertCount(7, $options);
    $this->assertSame(0.06, $options['recurring']['selection_probability']);
    $this->assertSame(0.94, $options['recurring']['brief_relevance']['probabilities']['relevant']);
    $this->assertSame('foundation', $options['recurring']['contribution']['choice']);
    $this->assertSame(0.0, $options['dispatcher']['selection_probability']);
    $this->assertSame('unrelated', $options['dispatcher']['contribution']['choice']);
    $this->assertSame('bundle__workshop', $area['options'][0]['id']);
    $this->assertSame(['Date'], $options['bundle__workshop']['fields']);
    $this->assertSame(0.73, $area['selection']['probability']);
    $this->assertTrue($area['selection']['needs_review']);
    $this->assertContains('Recurring Events', $area['assembly']['foundations']);
    $this->assertNotContains('Recurring Events', $area['assembly']['complements']);
    $this->assertContains('Recurrence field fixture', $area['assembly']['complements']);
    $this->assertNotContains('Programming events fixture', $area['assembly']['complements']);
    $this->assertStringContainsString('Local capabilities and discovered candidates', $area['gap']);
    $this->assertStringNotContainsString('No suitable package', $area['gap']);
    $this->assertStringNotContainsString('topics', $area['check']);
  }

  /**
   * Independent questions reference the work area and only its own candidates.
   */
  public function testContributionQuestionsAreScoped(): void {
    $site = ['bundles' => []];
    $candidates = [
      'event_option' => ['package' => 'fixture/event', 'matched_queries' => ['event']],
      'other_option' => ['package' => 'fixture/search', 'matched_queries' => ['search']],
    ];
    $questions = CapabilityPlan::questions($site, $candidates, ['events' => ['query' => 'event']]);
    $role = $questions['role__events__event_option'];
    $this->assertStringContainsString('requirements.events', $role->getInstructions());
    $this->assertStringContainsString('recipes.event_option', $role->getInstructions());
    $this->assertSame(['foundation', 'complement', 'unrelated', 'unknown'], $role->getOptionKeys());
    $this->assertArrayNotHasKey('role__events__other_option', $questions);
    $this->assertArrayNotHasKey('role__events__unresolved', $questions);
    $this->assertArrayNotHasKey('role__events__configure', $questions);
  }

  /**
   * Contradictory role evidence prevents a confident package endorsement.
   */
  public function testSelectedCandidateWithUnrelatedRoleNeedsReview(): void {
    $site = ['bundles' => []];
    $candidates = ['fixture' => ['id' => 'fixture', 'label' => 'Fixture', 'package' => 'fixture/event']];
    $answers = [
      'plan__event' => [
        'choice' => 'fixture',
        'probabilities' => ['fixture' => 1.0],
        'needs_review' => FALSE,
        'criterion' => 'Fixture.',
      ],
      'check__event' => ['choice' => 'integration', 'needs_review' => FALSE],
      'role__event__fixture' => $this->role('unrelated'),
    ];
    $area = CapabilityPlan::build($site, $candidates, ['event' => ['query' => 'event']], $answers)['areas']['event'];
    $this->assertTrue($area['needs_review']);
    $this->assertNull($area['package']);
    $this->assertSame('fixture/event', $area['options'][0]['package']);
  }

}
