<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests fresh discovery, real workflow evidence and optional source wiring.
 */
#[Group('ai_site_advisor')]
final class CatalogIntegrationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * The real filesystem directory used to exercise Finder and realpath.
   */
  private ?string $recipeDirectory = NULL;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->recipeDirectory !== NULL) {
      (new Filesystem())->remove($this->recipeDirectory);
    }
    parent::tearDown();
  }

  /**
   * Returns an authorized account without creating user content.
   */
  private function account(): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->with('access ai site advisor')->willReturn(TRUE);
    return $account;
  }

  /**
   * Adding and changing a real recipe requires no catalog code or cache clear.
   */
  public function testDynamicLocalDiscovery(): void {
    $this->container->get('module_installer')->install(['ai_site_advisor']);
    $directory = $this->recipeDirectory = sys_get_temp_dir() . '/advisor-recipes-' . $this->randomMachineName();
    mkdir($directory . '/new-recipe', 0777, TRUE);
    $manifest = $directory . '/new-recipe/recipe.yml';
    file_put_contents($manifest, "name: ECA approval starter\ndescription: Review workflow\ninstall: [workflows]\n");
    file_put_contents($directory . '/new-recipe/composer.json', '{"name":"example/approval","type":"drupal-recipe"}');
    $this->config('ai_site_advisor.settings')->set('recipe_directories', [realpath($directory)])->save();
    $catalog = $this->container->get('ai_site_advisor.catalog');
    $first = $catalog->search('ECA', 12);
    $this->assertCount(1, $first['items']);
    $item = $first['items'][0];
    $this->assertSame('example/approval', $item['package']);
    $this->assertSame('local_code', $item['availability']);
    $this->assertSame('unknown', $item['application_state']);
    $this->assertFalse($item['compatibility_verified']);
    file_put_contents($manifest, "name: ECA approval starter\ndescription: Changed workflow requirements\ninstall: [workflows, content_moderation]\n");
    $changed = $catalog->search('ECA', 12)['items'][0];
    $this->assertNotSame($item['source_hash'], $changed['source_hash']);
    $this->assertContains('content_moderation', $changed['installs']);
    mkdir($directory . '/another-recipe');
    file_put_contents($directory . '/another-recipe/recipe.yml', "name: ECA follow-up\n");
    $this->assertCount(2, $catalog->search('ECA', 12)['items']);
    file_put_contents($manifest, 'name: [broken');
    $partial = $catalog->search('ECA', 12);
    $this->assertCount(1, $partial['items']);
    $this->assertStringContainsString('new-recipe', implode(' ', $partial['warnings']));
  }

  /**
   * Workflow evidence comes from current configuration, with no recipe history.
   */
  public function testActiveWorkflowEvidence(): void {
    $this->container->get('module_installer')->install(['ai_site_advisor_demo', 'content_moderation']);
    Workflow::create([
      'id' => 'review',
      'label' => 'Review content',
      'type' => 'content_moderation',
      'type_settings' => [
        'states' => [
          'draft' => ['label' => 'Draft', 'weight' => 0, 'published' => FALSE, 'default_revision' => FALSE],
          'published' => ['label' => 'Published', 'weight' => 1, 'published' => TRUE, 'default_revision' => TRUE],
        ],
        'transitions' => ['publish' => ['label' => 'Publish', 'from' => ['draft'], 'to' => 'published', 'weight' => 0]],
        'entity_types' => ['node' => ['advisor_workshop']],
        'default_moderation_state' => 'draft',
      ],
    ])->save();
    $collector = $this->container->get('ai_site_advisor.context');
    $first = $collector->collect($this->account());
    $workflow = $first['workflows']['review'];
    $this->assertSame('draft', $workflow['transitions']['publish']['from'][0]);
    $this->assertSame(['advisor_workshop'], $workflow['node_bundles']);
    $this->assertTrue($workflow['states']['published']['published']);
    $this->assertArrayHasKey('site_policy', $first);
    $this->config('workflows.workflow.review')->set('type_settings.transitions.publish.from', ['draft', 'published'])->save();
    $this->container->get('entity_type.manager')->getStorage('workflow')->resetCache();
    $this->assertNotSame($first['fingerprint'], $collector->collect($this->account())['fingerprint']);
    Workflow::load('review')->disable()->save();
    $this->assertArrayNotHasKey('review', $collector->collect($this->account())['workflows']);
  }

  /**
   * Enabled public PB plugins feed the catalog and MCP bindings install alone.
   */
  public function testProjectBrowserAndMcpIntegrations(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->container->get('module_installer')->install(['ai_site_advisor_project_browser', 'ai_site_advisor_test']);
    $this->config('project_browser.admin_settings')->set('enabled_sources', ['advisor_fixture' => []])->save();
    $catalog = $this->container->get('ai_site_advisor.candidates');
    $result = $catalog->discover('workflow', $this->account());
    $items = array_column($result['items'], NULL, 'package');
    $recipe = $items['example/editorial-recipe'];
    $this->assertSame('catalog_only', $recipe['availability']);
    $this->assertTrue($recipe['source_claims']['compatible']);
    $this->assertFalse($recipe['compatibility_verified']);
    $this->assertSame('unknown', $recipe['application_state']);
    $this->assertSame('Editorial review & approval.', $recipe['description']);
    $this->assertSame('enabled_module', $items['drupal/core']['availability']);
    $this->assertTrue($result['truncated']);
    $this->assertSame('workflow', $this->container->get('state')->get('advisor_test.query')['search']);
    $this->container->get('state')->set('advisor_test.fail', TRUE);
    $partial = $catalog->discover('workflow', $this->account());
    $this->assertNotEmpty($partial['items'], 'Local discovery survives a Project Browser source failure.');
    $this->assertStringNotContainsString('credentials', implode(' ', $partial['warnings']));
    $this->assertStringContainsString('advisor_fixture could not be queried', implode(' ', $partial['warnings']));

    $this->container->get('module_installer')->install(['ai_site_advisor_mcp']);
    $this->assertSame('ai_site_advisor:discover_candidates', $this->config('mcp_server_tool_bridge.mcp_tool_config.ai_site_advisor_discover')->get('tool_id'));
    $this->assertTrue($this->config('mcp_server_tool_bridge.mcp_tool_config.ai_site_advisor_assess')->get('status'));
    $this->assertArrayHasKey('ai_site_advisor:discover_candidates', $this->container->get('plugin.manager.tool')->getDefinitions());
  }

}
