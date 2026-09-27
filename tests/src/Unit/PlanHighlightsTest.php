<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Hook\ThemeHooks;
use Drupal\site_architect\Presentation\PlanHighlights;
use PHPUnit\Framework\Attributes\Group;

/**
 * UI recommendations preserve choices, review states and the full comparison.
 */
#[Group('site_architect')]
final class PlanHighlightsTest extends UnitTestCase {

  /**
   * Highlights a chosen option, with at most two other resources.
   */
  public function testSelectedChoiceAndIndependentSupportingScores(): void {
    $area = $this->area();
    $highlights = PlanHighlights::build($area);
    $this->assertSame('recommended', $highlights['state']);
    $this->assertSame('chosen', $highlights['primary']['id']);
    $this->assertSame(['chosen', 'support', 'alternative'], array_column($highlights['resources'], 'option_id'));
    $this->assertSame(1, $highlights['more_resources']);
    // Contribution 96% keeps this useful addition despite a 1% starting score.
    $this->assertSame(0.96, $highlights['resources'][1]['role_probability']);
    $this->assertSame(0.01, $area['options'][2]['selection_probability']);
    $area['selection']['needs_review'] = TRUE;
    $this->assertSame('open', PlanHighlights::build($area)['state']);
    $this->assertNull(PlanHighlights::build($area)['primary']);
    $this->assertSame(['support', 'alternative', 'chosen'], array_column(PlanHighlights::build($area)['resources'], 'option_id'));
    $area['selection']['needs_review'] = FALSE;
    $area['options'][0]['contribution']['needs_review'] = TRUE;
    $this->assertSame('open', PlanHighlights::build($area)['state']);
    $this->assertSame(['support', 'alternative', 'uncertain'], array_column(PlanHighlights::build($area)['resources'], 'option_id'));

    $original = ['plan' => ['areas' => ['fixture' => $area]]];
    $variables = ['assessment' => $original];
    (new ThemeHooks())->preprocessResult($variables);
    $this->assertSame($area['options'], $variables['assessment']['plan']['areas']['fixture']['options']);
    $this->assertSame($area['handoff']['resources'], $variables['assessment']['plan']['areas']['fixture']['handoff']['resources']);
    $this->assertArrayHasKey('highlights', $variables['assessment']['plan']['areas']['fixture']);
    $this->assertArrayNotHasKey('highlights', $original['plan']['areas']['fixture']);
  }

  /**
   * Existing models count towards the limit and uncertainty is not promoted.
   */
  public function testExistingAndUnresolvedChoices(): void {
    $area = $this->area();
    $area['options'][] = [
      'id' => 'existing',
      'bundle_id' => 'fixture',
      'label' => 'Existing content type',
      'contribution' => ['choice' => 'foundation', 'needs_review' => FALSE],
    ];
    $area['selection']['id'] = 'existing';
    $highlights = PlanHighlights::build($area);
    $this->assertSame('recommended', $highlights['state']);
    $this->assertCount(2, $highlights['resources']);
    $this->assertSame('existing', $highlights['primary']['id']);
    $area['options'][4]['contribution']['choice'] = 'unrelated';
    $this->assertSame('open', PlanHighlights::build($area)['state']);
    $area['selection']['id'] = 'configure';
    $area['options'][] = ['id' => 'configure', 'kind' => 'configuration'];
    $highlights = PlanHighlights::build($area);
    $this->assertSame('open', $highlights['state']);
    $this->assertNull($highlights['primary']);
    $this->assertCount(3, $highlights['resources']);
    $area['options'] = $area['handoff']['resources'] = [];
    $highlights = PlanHighlights::build($area);
    $this->assertSame('open', $highlights['state']);
    $this->assertEmpty($highlights['resources']);
  }

  /**
   * A useful part is visible even when its whole-area role remains uncertain.
   */
  public function testCheckedPartIsNotCrowdedOut(): void {
    $area = $this->area();
    $area['selection']['needs_review'] = TRUE;
    $area['options'][0]['contribution']['probabilities']['foundation'] = 0.44;
    $area['options'][0]['contribution']['confidence'] = 0.25;
    $area['options'][0]['contribution']['needs_review'] = TRUE;
    $area['handoff']['resources'][0]['needs_review'] = TRUE;
    $this->assertNotContains('chosen', array_column(PlanHighlights::build($area)['resources'], 'option_id'));
    $area['requirements']['parts'] = [[
      'option_id' => 'chosen',
      'text' => 'Provide replies on the selected record model.',
      'status' => 'partial',
      'coverage' => ['probabilities' => ['direct' => 0.02, 'partial' => 0.96]],
    ],
    ];
    $result = PlanHighlights::build($area);
    $this->assertSame('open', $result['state']);
    $this->assertNull($result['primary']);
    $this->assertSame('chosen', $result['resources'][0]['option_id']);
    $this->assertSame('partial', $result['resources'][0]['parts'][0]['status']);
    $this->assertFalse($result['resources'][0]['selected']);
    $this->assertCount(3, $result['resources']);
  }

  /**
   * Fixtures deliberately separate selection and contribution probabilities.
   */
  private function area(): array {
    $options = $resources = [];
    foreach ([
      ['chosen', 'foundation', 0.8, FALSE],
      ['alternative', 'foundation', 0.9, FALSE],
      ['support', 'complement', 0.96, FALSE],
      ['uncertain', 'foundation', 0.99, TRUE],
    ] as [$id, $role, $probability, $review]) {
      $options[] = [
        'id' => $id,
        'package' => 'fixture/' . $id,
        'selection_probability' => $id === 'chosen' ? 0.85 : 0.01,
        'contribution' => [
          'choice' => $role,
          'probabilities' => [$role => $probability],
          'confidence' => 0.85,
          'needs_review' => $review,
        ],
      ];
      $resources[] = ['option_id' => $id, 'needs_review' => $review];
    }
    return [
      'selection' => ['id' => 'chosen', 'needs_review' => FALSE],
      'options' => $options,
      'handoff' => ['resources' => $resources],
    ];
  }

}
