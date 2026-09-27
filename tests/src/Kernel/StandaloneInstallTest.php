<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use Drupal\site_architect\Assessment\SiteArchitectInterface;
use Drupal\site_architect\Form\ArchitectForm;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves one-module installation, recipe independence and fresh evidence.
 */
#[Group('site_architect')]
final class StandaloneInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Installs into an isolated site and inspects actual field configuration.
   */
  public function testCompleteProductAndExampleRecipe(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $installer = $this->container->get('module_installer');
    $installer->install(['site_architect']);
    $this->assertInstanceOf(SiteArchitectInterface::class, $this->container->get('site_architect.architect'));
    $handler = $this->container->get('module_handler');
    $required_modules = [
      'ai', 'ai_decision', 'ai_provider_typesafeai', 'key', 'tool',
      'project_browser', 'api_browser', 'mcp_server', 'mcp_server_tool_bridge',
    ];
    foreach ($required_modules as $module) {
      $this->assertTrue($handler->moduleExists($module), $module . ' is installed with the product.');
    }
    $unrelated_modules = [
      'canvas',
      'canvas_tools',
      'webmcp_integration',
    ];
    foreach ($unrelated_modules as $module) {
      $this->assertFalse($handler->moduleExists($module), $module . ' is not required.');
    }
    $this->assertNull($this->container->get('entity_type.manager')->getStorage('node_type')->load('advisor_workshop'));
    $this->assertSame('site_architect:assess_content_brief', $this->config('mcp_server_tool_bridge.mcp_tool_config.site_architect_assess')->get('tool_id'));
    $this->assertSame('site_architect:discover_candidates', $this->config('mcp_server_tool_bridge.mcp_tool_config.site_architect_discover')->get('tool_id'));
    $this->assertArrayHasKey('api_browser_project:packagist_recipes', $this->container->get('Drupal\project_browser\Plugin\ProjectBrowserSourceManager')->getDefinitions());
    $recipe_path = $handler->getModule('site_architect')->getPath() . '/recipes/workshop';
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe_path));
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(static fn ($permission) => $permission === 'access site architect');
    $collector = $this->container->get('site_architect.context');
    $first = $collector->collect($account);
    $this->assertFalse($first['enabled_features']['canvas']);
    $this->assertSame('integer', $first['bundles']['advisor_workshop']['fields']['field_workshop_capacity']['type']);
    $this->assertArrayNotHasKey('uid', $first['bundles']['advisor_workshop']['fields']);
    $this->assertArrayNotHasKey('ai.settings', $first);
    // Drupal caches the form after an AJAX submission. Services must survive
    // restoration for subsequent submissions on the same page.
    $this->container->get('current_user')->setAccount($account);
    $form = unserialize(serialize(ArchitectForm::create($this->container)), ['allowed_classes' => [ArchitectForm::class]]);
    $rebuilt = $form->buildForm([], new FormState());
    $this->assertArrayHasKey('brief', $rebuilt);
    $this->assertArrayNotHasKey('context', $rebuilt);

    // A malformed model result clears prior advice and offers a safe retry.
    $invalid_architect = $this->createMock(SiteArchitectInterface::class);
    $invalid_architect->method('assess')->willThrowException(new \UnexpectedValueException('Untrusted response detail.'));
    $error_form = new ArchitectForm($invalid_architect);
    $error_state = (new FormState())->setValues(['brief' => 'An editorial workflow.', 'catalog_query' => '']);
    $error_state->set('assessment', ['previous' => 'advice']);
    $empty_form = [];
    $error_form->submitForm($empty_form, $error_state);
    $this->assertNull($error_state->get('assessment'));
    $this->assertStringContainsString('incomplete or inconsistent', (string) $error_state->get('architect_error'));
    $this->assertStringNotContainsString('Untrusted', (string) $error_state->get('architect_error'));

    FieldStorageConfig::create(['entity_type' => 'node', 'field_name' => 'field_test_price', 'type' => 'decimal'])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'advisor_workshop',
      'field_name' => 'field_test_price',
      'label' => 'Ticket price',
    ])->save();
    $next = $collector->collect($account);
    $this->assertNotSame($first['fingerprint'], $next['fingerprint']);
    $this->assertSame('Ticket price', $next['bundles']['advisor_workshop']['fields']['field_test_price']['label']);

    // The provider is configured by the host site, never shipped with a key.
    $this->assertEmpty($this->container->get('ai.provider')->getDefaultProviderForOperationType('decision'));
    $this->assertTrue($this->container->get('module_handler')->moduleExists('tool'));
    $this->assertArrayHasKey('site_architect:assess_content_brief', $this->container->get('plugin.manager.tool')->getDefinitions());
    $installer->uninstall(['site_architect']);
    $this->assertFalse($this->container->get('module_handler')->moduleExists('site_architect'));
    $this->assertTrue($this->config('mcp_server_tool_bridge.mcp_tool_config.site_architect_assess')->isNew());
    $this->assertTrue($this->config('mcp_server_tool_bridge.mcp_tool_config.site_architect_discover')->isNew());
    $this->assertNotNull($this->container->get('entity_type.manager')->getStorage('node_type')->load('advisor_workshop'), 'Recipe configuration survives uninstalling the planning module.');
  }

}
