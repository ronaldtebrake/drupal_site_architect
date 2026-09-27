<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Kernel;

use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Dynamic entity metadata and access-checked destinations, without fixtures.
 */
#[Group('site_architect')]
final class ConfigurationInspectorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * A newly created non-node model is immediately available with real links.
   */
  public function testNewBundleAndRouteAccess(): void {
    $this->container->get('module_installer')->install(['site_architect', 'taxonomy', 'field_ui']);
    Vocabulary::create(['vid' => 'knowledge', 'name' => 'Knowledge areas'])->save();
    FieldStorageConfig::create([
      'entity_type' => 'taxonomy_term',
      'field_name' => 'field_external_id',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'taxonomy_term',
      'bundle' => 'knowledge',
      'field_name' => 'field_external_id',
      'label' => 'External identifier',
    ])->save();
    $this->container->get('router.builder')->rebuild();
    $inspector = $this->container->get('site_architect.configuration');
    Role::create([
      'id' => 'builder',
      'label' => 'Builder',
      'permissions' => ['administer taxonomy', 'administer taxonomy_term fields'],
    ])->save();
    $account = new UserSession(['uid' => 4, 'roles' => ['builder']]);
    $areas = $inspector->collect($account);
    $vocabulary = $areas['taxonomy_vocabulary']['records']['knowledge'];
    $this->assertSame('Knowledge areas', $vocabulary['label']);
    $this->assertSame('External identifier', $vocabulary['fields']['field_external_id']['label']);
    $this->assertSame('string', $vocabulary['fields']['field_external_id']['type']);
    $this->assertContains('/admin/structure/taxonomy/manage/knowledge', array_column($vocabulary['links'], 'url'));
    $this->assertContains('/admin/structure/taxonomy/manage/knowledge/overview/fields', array_column($vocabulary['links'], 'url'));
    $denied = $inspector->collect(new UserSession(['uid' => 5]));
    $this->assertEmpty($denied['taxonomy_vocabulary']['links']);
    $this->assertEmpty($denied['taxonomy_vocabulary']['records']['knowledge']['links']);
    // Configuration types outside content bundles expose no arbitrary values.
    $this->assertEmpty($areas['user_role']['records']);
  }

}
