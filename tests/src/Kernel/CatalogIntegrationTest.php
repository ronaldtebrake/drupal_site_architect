<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests fresh discovery, real workflow evidence and built-in source wiring.
 */
#[Group('site_architect')]
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
    $account->method('hasPermission')->willReturnCallback(static fn ($permission) => $permission === 'access site architect');
    return $account;
  }

  /**
   * Adding and changing a real recipe requires no catalog code or cache clear.
   */
  public function testDynamicLocalDiscovery(): void {
    $this->container->get('module_installer')->install(['site_architect']);
    $directory = $this->recipeDirectory = sys_get_temp_dir() . '/architect-recipes-' . $this->randomMachineName();
    mkdir($directory . '/new-recipe', 0777, TRUE);
    $manifest = $directory . '/new-recipe/recipe.yml';
    file_put_contents($manifest, "name: ECA approval starter\ndescription: Review workflow\ninstall: [workflows]\n");
    file_put_contents($directory . '/new-recipe/composer.json', '{"name":"example/approval","type":"drupal-recipe"}');
    mkdir($directory . '/new-recipe/config');
    file_put_contents($directory . '/new-recipe/config/fixture.settings.yml', "label: Review settings\napi_key: fixture-secret-not-for-export\ndefault_value: do-not-export\n");
    $this->config('site_architect.settings')->set('recipe_directories', [realpath($directory)])->save();
    $catalog = $this->container->get('site_architect.catalog');
    $first = $catalog->search('ECA', 12);
    $this->assertCount(1, $first['items']);
    $item = $first['items'][0];
    $this->assertSame('example/approval', $item['package']);
    $this->assertSame('local_code', $item['availability']);
    $this->assertSame('unknown', $item['application_state']);
    $this->assertFalse($item['compatibility_verified']);
    $this->assertSame('Review settings', $item['configuration'][0]['label']);
    $this->assertFalse($item['configuration'][0]['active_exists']);
    $this->assertStringNotContainsString('fixture-secret', json_encode($item));
    $this->assertStringNotContainsString('do-not-export', json_encode($item));
    $this->container->get('config.storage')->write('fixture.settings', ['label' => 'Existing settings']);
    $this->container->get('config.factory')->reset('fixture.settings');
    $this->assertTrue($catalog->search('ECA', 12)['items'][0]['configuration'][0]['active_exists']);
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
    $this->container->get('module_installer')->install(['site_architect', 'content_moderation']);
    $path = $this->container->get('module_handler')->getModule('site_architect')->getPath() . '/recipes/workshop';
    RecipeRunner::processRecipe(Recipe::createFromDirectory($path));
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
    $collector = $this->container->get('site_architect.context');
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
   * Public PB plugins feed the catalog; MCP bindings install with the product.
   */
  public function testProjectBrowserAndMcpIntegrations(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->container->get('module_installer')->install(['site_architect', 'site_architect_test']);
    $this->config('project_browser.admin_settings')->set('enabled_sources', ['architect_fixture' => []])->save();
    $catalog = $this->container->get('site_architect.candidates');
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
    $this->assertSame('workflow', $this->container->get('state')->get('architect_test.query')['search']);
    $cached = $catalog->discover('workflow', $this->account());
    $this->assertSame(1, $this->container->get('state')->get('architect_test.calls'));
    $this->assertSame($result['items'], $cached['items']);
    $this->assertFalse(end($result['sources'])['cache']['hit']);
    $this->assertTrue(end($cached['sources'])['cache']['hit']);
    // The ordinary Project Browser refresh tag also refreshes our pages.
    $this->container->get('cache_tags.invalidator')->invalidateTags(['project_browser:architect_fixture']);
    $this->container->get('state')->set('architect_test.fail', TRUE);
    $partial = $catalog->discover('workflow', $this->account());
    $this->assertNotEmpty($partial['items'], 'Local discovery survives a Project Browser source failure.');
    $this->assertStringNotContainsString('credentials', implode(' ', $partial['warnings']));
    $this->assertStringContainsString('architect_fixture could not be queried', implode(' ', $partial['warnings']));
    $failed_source = end($partial['sources']);
    $this->assertSame('architect_fixture', $failed_source['id']);
    $this->assertNull($failed_source['matches'], 'Unavailable matches are not reported as zero.');
    $this->assertNotEmpty($failed_source['error']);
    $this->assertStringNotContainsString('credentials', json_encode($failed_source));
    $this->assertStringNotContainsString('No additional Project Browser sources', implode(' ', $partial['warnings']));

    $this->assertSame('site_architect:discover_candidates', $this->config('mcp_server_tool_bridge.mcp_tool_config.site_architect_discover')->get('tool_id'));
    $this->assertTrue($this->config('mcp_server_tool_bridge.mcp_tool_config.site_architect_assess')->get('status'));
    $this->assertArrayHasKey('site_architect:discover_candidates', $this->container->get('plugin.manager.tool')->getDefinitions());
  }

}
