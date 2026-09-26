<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_site_advisor\Assessment\CapabilityPlan;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that plan cards preserve uncertainty and inspected evidence.
 */
#[Group('ai_site_advisor')]
final class CapabilityPlanTest extends UnitTestCase {

  /**
   * A possible package remains an investigation, even with a clear selection.
   */
  public function testCandidateAndUnresolvedWorkAreas(): void {
    $site = ['bundles' => []];
    $candidates = [
      'fixture' => [
        'id' => 'fixture',
        'label' => 'Inspected example',
        'package' => 'example/candidate',
        'description' => 'Description from the configured source.',
        'url' => 'https://example.org/project',
      ],
    ];
    $capabilities = [
      'one' => ['label' => 'groups', 'query' => 'group'],
      'two' => ['label' => 'notifications', 'query' => 'notification'],
    ];
    $answers = [
      'plan__one' => [
        'choice' => 'fixture',
        'needs_review' => TRUE,
        'criterion' => 'Fixture criterion.',
        'probabilities' => ['fixture' => 0.5],
      ],
      'plan__two' => [
        'choice' => 'unresolved',
        'needs_review' => TRUE,
        'criterion' => 'Fixture criterion.',
        'probabilities' => [],
      ],
      'check__one' => ['choice' => 'access', 'needs_review' => FALSE],
      'check__two' => ['choice' => 'delivery', 'needs_review' => FALSE],
    ];
    $plan = CapabilityPlan::build($site, $candidates, $capabilities, $answers);
    $this->assertSame('draft', $plan['status']);
    $this->assertNull($plan['areas']['one']['package']);
    $options = array_column($plan['areas']['one']['options'], NULL, 'id');
    $this->assertSame('example/candidate', $options['fixture']['package']);
    $this->assertSame('needs_review', $plan['areas']['one']['status']);
    $this->assertSame($candidates['fixture']['description'], $options['fixture']['description']);
    $this->assertNull($plan['areas']['two']['package']);
    $this->assertStringContainsString('broaden discovery', $plan['areas']['two']['gap']);
    $this->assertStringContainsString('channels', $plan['areas']['two']['check']);
  }

}
