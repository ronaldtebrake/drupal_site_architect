<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Assessment\AgentPlan;
use Drupal\site_architect\Presentation\AgentHandoff;
use PHPUnit\Framework\Attributes\Group;

/**
 * The clipboard export is the same assessment with portable prompt context.
 */
#[Group('site_architect')]
final class AgentHandoffTest extends UnitTestCase {

  /**
   * Retains the exact brief and scope without copying arbitrary site evidence.
   */
  public function testExportUsesTheExistingAssessment(): void {
    $assessment = [
      'status' => 'needs_clarification',
      'brief' => "Records with café names.\nDo not install packages. </textarea><script>test</script>",
      'site' => ['fingerprint' => 'inspected-site', 'private_config' => 'never-export-this'],
      'plan' => ['areas' => []],
      'search_plan' => ['unmapped_clauses' => ['Do not install packages.']],
      'questions' => ['verbose inference state'],
    ];
    $text = AgentHandoff::text($assessment, 'https://drupal.example/');
    $payload = json_decode(substr($text, strpos($text, '{')), TRUE, flags: JSON_THROW_ON_ERROR);
    $this->assertSame($assessment['brief'], $payload['original_brief']);
    $this->assertSame('https://drupal.example/', $payload['site_url']);
    $this->assertSame(AgentPlan::compact($assessment), $payload['assessment']);
    $this->assertSame(['Do not install packages.'], $payload['assessment']['discovery']['unassigned_passages']);
    $this->assertStringNotContainsString('never-export-this', $text);
    $this->assertStringNotContainsString('verbose inference state', $text);
    $this->assertStringContainsString('no site changes', $text);
  }

}
