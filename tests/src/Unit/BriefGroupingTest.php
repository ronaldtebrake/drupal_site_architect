<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Assessment\BriefGrouping;
use PHPUnit\Framework\Attributes\Group;

/**
 * Subject/detail grouping preserves both source context and uncertain items.
 */
#[Group('site_architect')]
final class BriefGroupingTest extends UnitTestCase {

  /**
   * Attributes stay with their subject; ambiguous text stays visible.
   */
  public function testGroupingPreservesEveryPassage(): void {
    $clauses = array_map(static fn ($text) => ['source_text' => $text], [
      'Manage equipment', 'serial number', 'filter by availability',
      'Staff profiles', 'shared image', 'Do not install anything',
    ]);
    $areas = [
      'equipment' => [
        'id' => 'equipment',
        'label' => 'Equipment',
        'query' => 'equipment',
        'source_text' => 'Manage equipment',
      ],
      'profiles' => ['id' => 'profiles', 'label' => 'Profiles', 'query' => 'profile', 'source_text' => 'Staff profiles'],
    ];
    $input = BriefGrouping::input('The complete brief.', $clauses, $areas);
    $this->assertCount(6, $input->getQuestions());
    $this->assertSame(['equipment', 'profiles', 'separate'], $input->getQuestions()['group_1']->getOptionKeys());
    $this->assertSame('serial number', $input->getQuestions()['group_1']->getInstructions()['passage']);
    $answers = [];
    foreach (['equipment', 'equipment', 'equipment', 'profiles', 'equipment', 'separate'] as $i => $choice) {
      $probabilities = ['equipment' => 0.0, 'profiles' => 0.0, 'separate' => 0.0];
      $probabilities[$choice] = 1.0;
      $answers['group_' . $i] = ['choice' => $choice, 'probabilities' => $probabilities, 'confidence' => 1.0];
    }
    $answers['group_4']['confidence'] = 0.3;
    $result = BriefGrouping::build($clauses, $areas, [0 => 'equipment', 3 => 'profiles'], $answers);
    $this->assertSame(array_slice(array_column($clauses, 'source_text'), 0, 3), $result['capabilities']['equipment']['source_texts']);
    $this->assertSame(['shared image', 'Do not install anything'], $result['unmapped_clauses']);
    $this->assertSame(['Staff profiles'], $result['capabilities']['profiles']['source_texts']);
    $this->assertCount(2, $result['capabilities']);

    // A doubtful merge of two independent subjects retains the original one.
    $answers['group_3'] = $answers['group_4'];
    $result = BriefGrouping::build($clauses, $areas, [0 => 'equipment', 3 => 'profiles'], $answers);
    $this->assertTrue($result['capabilities']['profiles']['grouping_needs_review']);
    $this->assertSame(['Staff profiles'], $result['capabilities']['profiles']['source_texts']);
  }

}
