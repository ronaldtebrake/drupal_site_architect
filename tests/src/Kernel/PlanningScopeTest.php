<?php

declare(strict_types=1);

namespace Drupal\Tests\site_architect\Kernel;

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies scope ownership and upgrades without changing an existing scope.
 */
#[Group('site_architect')]
final class PlanningScopeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * OAuth enabled later imports the scope; upgrades preserve customization.
   */
  public function testLateOauthAndUpgrade(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $installer = $this->container->get('module_installer');
    $installer->install(['site_architect']);
    $path = $this->container->get('module_handler')->getModule('site_architect')->getPath();
    require_once $path . '/site_architect.post_update.php';
    site_architect_post_update_planning_oauth_scope();
    $this->assertTrue($this->config('simple_oauth.oauth2_scope.drupal_site_architect_plan')->isNew());

    $installer->install(['simple_oauth']);
    $storage = $this->container->get('entity_type.manager')->getStorage('oauth2_scope');
    $scope = $storage->load('drupal_site_architect_plan');
    $this->assertNotNull($scope, 'Enabling OAuth later imports optional configuration.');
    $this->assertSame('drupal:site-architect:plan', $scope->get('name'));
    $this->assertSame(['permission' => 'access site architect'], $scope->get('granularity_configuration'));
    $this->assertTrue($scope->isGrantTypeEnabled('authorization_code'));
    $this->assertTrue($scope->isGrantTypeEnabled('refresh_token'));
    $this->assertFalse($scope->isGrantTypeEnabled('client_credentials'));

    $scope->delete();
    site_architect_post_update_planning_oauth_scope();
    $restored = $storage->load('drupal_site_architect_plan');
    $this->assertNotNull($restored, 'An existing installation receives the missing scope.');
    $restored->set('description', 'Administrator customization')->disable()->save();
    site_architect_post_update_planning_oauth_scope();
    $preserved = $storage->load('drupal_site_architect_plan');
    $this->assertFalse($preserved->status());
    $this->assertSame('Administrator customization', $preserved->get('description'));

    $installer->uninstall(['site_architect']);
    $this->assertNull($storage->load('drupal_site_architect_plan'));
    $this->assertTrue($this->container->get('module_handler')->moduleExists('simple_oauth'));
  }

  /**
   * A recipe can install OAuth without importing existing optional config.
   */
  public function testLateOauthRecipe(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->container->get('module_installer')->install(['site_architect']);
    $path = $this->container->get('module_handler')->getModule('site_architect')->getPath();
    require_once $path . '/site_architect.post_update.php';
    site_architect_post_update_planning_oauth_scope();
    $recipe_path = $path . '/tests/fixtures/recipes/oauth_without_optional';
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe_path));
    $storage = $this->container->get('entity_type.manager')->getStorage('oauth2_scope');
    $scope = $storage->load('drupal_site_architect_plan');
    $this->assertNotNull($scope, 'Recipe completion registers the missing planning scope.');
    $this->assertSame('drupal:site-architect:plan', $scope->get('name'));
    $scope->delete();
    site_architect_post_update_recipe_planning_scope();
    $scope = $storage->load('drupal_site_architect_plan');
    $this->assertNotNull($scope, 'Upgrades repair sites whose earlier update ran before the OAuth recipe.');
    $scope->set('description', 'Existing site policy')->disable()->save();
    site_architect_post_update_recipe_planning_scope();
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe_path));
    $scope = $storage->load('drupal_site_architect_plan');
    $this->assertFalse($scope->status());
    $this->assertSame('Existing site policy', $scope->get('description'));
    $scope->delete();
    RecipeRunner::processRecipe(Recipe::createFromDirectory($recipe_path));
    $this->assertNull($storage->load('drupal_site_architect_plan'), 'Reapplying a recipe that installs no relevant modules does not undo scope deletion.');
  }

}
