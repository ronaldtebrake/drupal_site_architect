<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\site_architect\Assessment\ContentPlanningProfile;
use Drupal\site_architect\Assessment\DecisionBatch;
use PHPUnit\Framework\Attributes\Group;

/**
 * Packing optimizations must preserve judgments and all referenced evidence.
 */
#[Group('site_architect')]
final class ContentPlanningProfileTest extends UnitTestCase {

  /**
   * Large plans retain exact questions, choices and facts in smaller packets.
   */
  public function testCandidatePackingPreservesEvidence(): void {
    $brief = 'Compare the whole build, keeping access, translation and listings in view.';
    $site = [
      'bundles' => [
        'record' => ['label' => 'Record', 'fields' => ['body' => ['label' => 'Body', 'type' => 'text_long']]],
      ],
      'configuration_areas' => ['fixture' => ['label' => 'Settings', 'records' => []]],
      'site_policy' => 'Inspect what exists before adding anything.',
    ];
    $candidates = [];
    for ($i = 0; $i < 60; $i++) {
      $candidates['c' . $i] = [
        'id' => 'c' . $i,
        'package' => 'fixture/c' . $i,
        'label' => 'Candidate ' . $i,
        'description' => str_repeat('Complete candidate evidence. ', 15),
        'dependencies' => ['fixture:records'],
        'availability' => 'catalog_only',
      ];
    }
    $candidates['c59']['matched_queries'] = ['area0'];
    $candidates['c58']['matched_queries'] = ['supporting need'];
    $capabilities = [];
    for ($i = 0; $i < 8; $i++) {
      $capabilities['area' . $i] = [
        'id' => 'area' . $i,
        'query' => 'area' . $i,
        'source_text' => 'Complete source requirement ' . $i,
      ];
    }
    $capabilities['area0']['supporting_capabilities'] = [['query' => 'supporting need']];
    $profile = new ContentPlanningProfile();
    $original = $profile->buildInput($brief, $site, $candidates, $capabilities);
    $packed = $profile->buildInputs($brief, $site, $candidates, $capabilities);
    $this->assertGreaterThan(100000, DecisionBatch::bytes($original));
    $seen = [];
    $has_focused_packet = FALSE;
    foreach ($packed as $input) {
      $this->assertLessThanOrEqual(100000, DecisionBatch::bytes($input));
      $this->assertSame($brief, $input->getState()['brief']);
      $this->assertSame($site, $input->getState()['site']);
      $this->assertLessThanOrEqual(1, count($input->getState()['requirements']), 'Large-plan role scoring must not mix work areas.');
      foreach ($input->getQuestions() as $id => $question) {
        $this->assertArrayNotHasKey($id, $seen);
        $seen[$id] = TRUE;
        $this->assertSame($original->getQuestions()[$id]->toArray(), $question->toArray());
        if (str_starts_with($id, 'plan__')) {
          foreach ($question->getOptionKeys() as $key) {
            if (isset($candidates[$key])) {
              $this->assertSame($candidates[$key], $input->getState()['recipes'][$key], 'Every competing alternative retains its full evidence.');
            }
          }
        }
        if (str_starts_with($id, 'role__')) {
          [, $area, $candidate] = explode('__', $id, 3);
          $this->assertSame($capabilities[$area], $input->getState()['requirements'][$area]);
          if (isset($candidates[$candidate])) {
            $this->assertSame($candidates[$candidate], $input->getState()['recipes'][$candidate]);
            $has_focused_packet = $has_focused_packet || count($input->getState()['recipes']) < 59;
          }
        }
      }
    }
    $this->assertCount(count($original->getQuestions()), $seen, 'No scoring or validation question is dropped.');
    $this->assertTrue($has_focused_packet);
    $this->assertContains('c59', $original->getQuestions()['plan__area0']->getOptionKeys());
    $this->assertNotContains('c59', $original->getQuestions()['plan__area1']->getOptionKeys());
    $this->assertContains('c58', $original->getQuestions()['plan__area0']->getOptionKeys());
    $this->assertNotContains('c58', $original->getQuestions()['plan__area1']->getOptionKeys());
  }

}
