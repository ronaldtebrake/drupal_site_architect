<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_site_advisor\Assessment\SiteAdvisorInterface;
use Drupal\ai_site_advisor\Context\CandidateCatalog;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tool inputs select compact/full output without changing shared services.
 */
#[Group('ai_site_advisor')]
final class ToolResponseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Tests real plugin execution, defaults and input validation.
   */
  public function testResponseFormats(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->container->get('module_installer')->install(['ai_site_advisor_tool']);
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn(TRUE);
    $this->container->get('current_user')->setAccount($account);
    $assessment = [
      'status' => 'assessed',
      'plan' => ['areas' => []],
      'site' => ['fingerprint' => 'fixture'],
      'questions' => ['full evidence'],
    ];
    $discovery = [
      'query' => 'fixture',
      'items' => [],
      'sources' => ['full evidence'],
      'truncated' => FALSE,
      'warnings' => [],
    ];
    // Exactly two calls: an invalid detail must fail before the service runs.
    $advisor = $this->createMock(SiteAdvisorInterface::class);
    $advisor->expects($this->exactly(2))->method('assess')->willReturn($assessment);
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->expects($this->exactly(2))->method('discover')->willReturn($discovery);
    $this->container->set('ai_site_advisor.advisor', $advisor);
    $this->container->set('ai_site_advisor.candidates', $catalog);
    $manager = $this->container->get('plugin.manager.tool');
    foreach ([
      ['assess_content_brief', 'brief', 'A useful content brief.', 'assessment', $assessment],
      ['discover_candidates', 'query', 'fixture', 'discovery', $discovery],
    ] as [$id, $input, $value, $output, $full]) {
      foreach ([NULL, 'full', 'invalid'] as $detail) {
        $tool = $manager->createInstance('ai_site_advisor:' . $id);
        $tool->setInputValue($input, $value);
        if ($detail !== NULL) {
          $tool->setInputValue('detail', $detail);
        }
        $tool->execute();
        if ($detail === 'invalid') {
          $this->assertFalse($tool->getResult()->isSuccess());
          continue;
        }
        $this->assertTrue($tool->getResult()->isSuccess());
        $result = $tool->getOutputValue($output);
        if ($detail === 'full') {
          $this->assertSame($full, $result);
        }
        else {
          $this->assertSame('compact', $result['format']);
          $this->assertArrayNotHasKey('questions', $result);
          $this->assertArrayNotHasKey('sources', $result);
        }
      }
    }
  }

}
