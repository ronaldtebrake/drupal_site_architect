<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\site_architect\Assessment\SiteArchitectInterface;
use Drupal\site_architect\Context\CandidateCatalog;
use Drupal\site_architect\Presentation\AgentHandoff;
use Drupal\site_architect\Form\ArchitectForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tool inputs select compact/full output without changing shared services.
 */
#[Group('site_architect')]
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
    $this->container->get('module_installer')->install(['site_architect_tool']);
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
    $architect = $this->createMock(SiteArchitectInterface::class);
    $architect->expects($this->exactly(2))->method('assess')->willReturn($assessment);
    $catalog = $this->createMock(CandidateCatalog::class);
    $catalog->expects($this->exactly(2))->method('discover')->willReturn($discovery);
    $this->container->set('site_architect.architect', $architect);
    $this->container->set('site_architect.candidates', $catalog);
    $manager = $this->container->get('plugin.manager.tool');
    foreach ([
      ['assess_content_brief', 'brief', 'A useful content brief.', 'assessment', $assessment],
      ['discover_candidates', 'query', 'fixture', 'discovery', $discovery],
    ] as [$id, $input, $value, $output, $full]) {
      foreach ([NULL, 'full', 'invalid'] as $detail) {
        $tool = $manager->createInstance('site_architect:' . $id);
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

  /**
   * The rendered copy text remains inert and uses no inference service.
   */
  public function testCopyPreviewEscapesSourceText(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->container->get('module_installer')->install(['site_architect']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $architect = $this->createMock(SiteArchitectInterface::class);
    $architect->expects($this->never())->method('assess');
    $this->container->set('site_architect.architect', $architect);
    $assessment = [
      'brief' => '</textarea><script id="source-injection">alert(1)</script>',
      'status' => 'assessed',
      'plan' => ['areas' => []],
      'site' => ['fingerprint' => 'same-snapshot'],
      'answers' => [],
    ];
    $state = (new FormState())->set('assessment', $assessment);
    $form = (new ArchitectForm($architect))->buildForm([], $state);
    $build = $form['result'];
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $document = new \DOMDocument();
    @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new \DOMXPath($document);
    $this->assertSame(0, $xpath->query('//script[@id="source-injection"]')->length);
    $texts = $xpath->query('//textarea[@data-architect-copy-text]');
    $this->assertSame(1, $texts->length);
    $text = $texts->item(0)->textContent;
    $payload = json_decode(substr($text, strpos($text, '{')), TRUE, flags: JSON_THROW_ON_ERROR);
    $this->assertSame($assessment['brief'], $payload['original_brief']);
    $this->assertSame(1, $xpath->query('//button[@data-architect-copy and @type="button"]')->length);
    $expected = AgentHandoff::text($assessment, Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString());
    $this->assertSame($expected, $texts->item(0)->textContent);
  }

}
