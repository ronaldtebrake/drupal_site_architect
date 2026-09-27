<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Kernel;

use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Shipped core features remain visible independently of their enabled state.
 */
#[Group('site_architect')]
final class ModuleInventoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Reads real module metadata and permission-checked configuration links.
   */
  public function testDisabledCoreAndEnabledCapabilities(): void {
    $installer = $this->container->get('module_installer');
    $installer->install(['site_architect']);
    Role::create(['id' => 'builder', 'label' => 'Builder', 'is_admin' => TRUE])->save();
    $account = new UserSession(['uid' => 4, 'roles' => ['builder']]);
    $first = $this->container->get('site_architect.context')->collect($account);
    foreach (['views', 'locale', 'content_translation', 'config_translation', 'language'] as $name) {
      $module = $first['available_modules']['module__' . $name];
      $this->assertTrue($module['core']);
      $this->assertSame('drupal/core', $module['package']);
      $this->assertSame('local_code', $module['availability']);
      $this->assertSame($name, $module['module_name']);
      $this->assertStringEndsWith($name . '.info.yml', $module['source']);
    }
    $this->assertStringContainsString('interface text', $first['available_modules']['module__locale']['description']);
    $this->assertStringContainsString('translate content', $first['available_modules']['module__content_translation']['description']);
    $this->assertEmpty($first['available_modules']['module__content_translation']['links']);
    $this->assertFalse($first['available_modules']['module__site_architect']['core']);
    $this->assertSame('enabled_module', $first['available_modules']['module__site_architect']['availability']);

    $installer->install(['views_ui', 'content_translation']);
    $this->container->get('router.builder')->rebuild();
    $next = $this->container->get('site_architect.context')->collect($account);
    $this->assertNotSame($first['fingerprint'], $next['fingerprint']);
    $this->assertSame('enabled_module', $next['available_modules']['module__views']['availability']);
    $this->assertSame('local_code', $next['available_modules']['module__locale']['availability']);
    $this->assertContains('/admin/structure/views', array_column($next['available_modules']['module__views']['links'], 'url'));
    $this->assertContains('/admin/config/regional/content-language', array_column($next['available_modules']['module__content_translation']['links'], 'url'));
    $denied = $this->container->get('site_architect.module_inventory')->collect(new UserSession(['uid' => 5]), []);
    $this->assertEmpty($denied['module__content_translation']['links']);
    $this->assertEmpty($denied['module__views_ui']['links']);
  }

}
