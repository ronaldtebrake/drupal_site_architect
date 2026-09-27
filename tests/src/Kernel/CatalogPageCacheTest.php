<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\UserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_site_advisor_project_browser\CatalogPageCache;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers cache scope, expiry, refresh and failures with real Drupal services.
 */
#[Group('ai_site_advisor')]
final class CatalogPageCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Queries are reused only within their account, configuration and lifetime.
   */
  public function testCacheScopeAndFreshness(): void {
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->container->get('module_installer')->install(['ai_site_advisor_project_browser', 'ai_site_advisor_test']);
    $state = $this->container->get('state');
    $source = $this->container->get('Drupal\project_browser\Plugin\ProjectBrowserSourceManager')->createInstance('advisor_fixture');
    $now = time();
    $clock = $this->createMock(TimeInterface::class);
    $clock->method('getCurrentTime')->willReturnCallback(static function () use (&$now): int {
      return $now;
    });
    $pages = new CatalogPageCache($this->container->get('cache.default'), $clock, $this->container->get('cache_contexts_manager'));
    $query = ['search' => 'workflow', 'limit' => 24, 'page' => 0];
    $this->assertFalse($pages->query($source, $query)['hit']);
    $this->assertTrue($pages->query($source, $query)['hit']);
    $this->assertSame(1, $state->get('advisor_test.calls'));

    $this->assertFalse($pages->query($source, array_replace($query, ['search' => 'translation']))['hit']);
    $this->assertFalse($pages->query($source, array_replace($query, ['limit' => 12]))['hit']);
    $source->setConfiguration(['changed' => TRUE]);
    $this->assertFalse($pages->query($source, $query)['hit']);

    $this->container->get('current_user')->setAccount(new UserSession(['uid' => 17]));
    $this->assertFalse($pages->query($source, $query)['hit'], 'Catalogue pages are not shared between accounts.');
    $this->assertTrue($pages->query($source, $query)['hit']);
    $now += CatalogPageCache::MAX_AGE;
    $this->assertFalse($pages->query($source, $query)['hit'], 'Expiry uses current time even inside a long-running request.');

    $this->container->get('cache_tags.invalidator')->invalidateTags(['project_browser:advisor_fixture']);
    $this->assertFalse($pages->query($source, $query)['hit']);
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:project_browser.admin_settings']);
    $this->assertFalse($pages->query($source, $query)['hit']);
    $this->container->get('cache_tags.invalidator')->invalidateTags(['config:core.extension']);
    $this->assertFalse($pages->query($source, $query)['hit']);

    $this->container->get('cache_tags.invalidator')->invalidateTags(['project_browser:advisor_fixture']);
    $state->set('advisor_test.error', 'Fixture source error.');
    $this->assertFalse($pages->query($source, $query)['hit']);
    $this->assertFalse($pages->query($source, $query)['hit'], 'Error pages must never be reused.');
    $state->delete('advisor_test.error');
    $this->assertFalse($pages->query($source, $query)['hit'], 'Recovery contacts the source again.');
    $this->assertTrue($pages->query($source, $query)['hit']);
    $this->assertSame(12, $state->get('advisor_test.calls'));
  }

}
