<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_site_advisor\Assessment\SiteAdvisorInterface;
use Drupal\ai_site_advisor\Form\AdvisorForm;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves installation and fresh evidence without optional integrations.
 */
#[Group('ai_site_advisor')]
final class StandaloneInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Installs into an isolated site and inspects actual field configuration.
   */
  public function testStandaloneAndOptionalIntegrations(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $installer = $this->container->get('module_installer');
    $installer->install(['ai_site_advisor_demo']);
    $this->assertInstanceOf(SiteAdvisorInterface::class, $this->container->get('ai_site_advisor.advisor'));
    $handler = $this->container->get('module_handler');
    $optional_modules = [
      'canvas',
      'canvas_tools',
      'tool',
      'webmcp_integration',
      'ai_provider_typesafeai',
      'project_browser',
      'api_browser',
      'mcp_server',
    ];
    foreach ($optional_modules as $module) {
      $this->assertFalse($handler->moduleExists($module), $module . ' is not required.');
    }
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(static fn ($permission) => $permission === 'access ai site advisor');
    $collector = $this->container->get('ai_site_advisor.context');
    $first = $collector->collect($account);
    $this->assertFalse($first['enabled_features']['canvas']);
    $this->assertSame('integer', $first['bundles']['advisor_workshop']['fields']['field_workshop_capacity']['type']);
    $this->assertArrayNotHasKey('uid', $first['bundles']['advisor_workshop']['fields']);
    $this->assertArrayNotHasKey('ai.settings', $first);
    // Drupal caches the form after an AJAX submission. Services must survive
    // restoration for subsequent submissions on the same page.
    $this->container->get('current_user')->setAccount($account);
    $form = unserialize(serialize(AdvisorForm::create($this->container)), ['allowed_classes' => [AdvisorForm::class]]);
    $rebuilt = $form->buildForm([], new FormState());
    $this->assertArrayHasKey('brief', $rebuilt);
    $this->assertArrayNotHasKey('context', $rebuilt);

    // A malformed model result clears prior advice and offers a safe retry.
    $invalid_advisor = $this->createMock(SiteAdvisorInterface::class);
    $invalid_advisor->method('assess')->willThrowException(new \UnexpectedValueException('Untrusted response detail.'));
    $error_form = new AdvisorForm($invalid_advisor);
    $error_state = (new FormState())->setValues(['brief' => 'An editorial workflow.', 'catalog_query' => '']);
    $error_state->set('assessment', ['previous' => 'advice']);
    $empty_form = [];
    $error_form->submitForm($empty_form, $error_state);
    $this->assertNull($error_state->get('assessment'));
    $this->assertStringContainsString('incomplete or inconsistent', (string) $error_state->get('advisor_error'));
    $this->assertStringNotContainsString('Untrusted', (string) $error_state->get('advisor_error'));

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
    $installer->install(['ai_site_advisor_tool']);
    $this->assertTrue($this->container->get('module_handler')->moduleExists('tool'));
    $this->assertArrayHasKey('ai_site_advisor:assess_content_brief', $this->container->get('plugin.manager.tool')->getDefinitions());
    $installer->uninstall(['ai_site_advisor_tool']);
    $this->assertTrue($this->container->get('module_handler')->moduleExists('ai_site_advisor'));
  }

}
