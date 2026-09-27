<?php

declare(strict_types=1);

namespace Drupal\site_architect\Context;

use Composer\InstalledVersions;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\project_browser\Plugin\ProjectBrowserSourceInterface;

/**
 * Reuses short-lived catalogue pages without caching site state or advice.
 */
final class CatalogPageCache {

  public const MAX_AGE = 300;

  /**
   * Constructs the cache using Drupal's account/language cache contexts.
   */
  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    private readonly CacheContextsManager $contexts,
  ) {}

  /**
   * Fetches a page through the public source API, preserving refresh tags.
   */
  public function query(ProjectBrowserSourceInterface $source, array $query): array {
    $contexts = $this->contexts->convertTokensToKeys(['user', 'user.permissions', 'languages']);
    $ttl = Cache::mergeMaxAges(self::MAX_AGE, $contexts->getCacheMaxAge());
    $lock = InstalledVersions::getRootPackage()['install_path'] . '/composer.lock';
    $identity = [
      $source->getPluginId(),
      $source->getConfiguration(),
      $query,
      $contexts->getKeys(),
      \Drupal::VERSION,
      is_file($lock) ? hash_file('sha256', $lock) : NULL,
    ];
    $cid = 'site_architect:catalog:v1:' . hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
    $now = $this->time->getCurrentTime();
    $cached = $ttl > 0 ? $this->cache->get($cid) : FALSE;
    if ($cached && $cached->data['stored_at'] + $ttl > $now) {
      return $cached->data + ['hit' => TRUE, 'max_age' => $ttl];
    }
    $page = $source->getProjects($query);
    $result = ['page' => $page, 'stored_at' => $now];
    // Never cache failures. An empty successful page is still a valid result.
    if (!$page->error && $ttl > 0) {
      $this->cache->set($cid, $result, $now + $ttl, Cache::mergeTags($contexts->getCacheTags(), [
        'project_browser:' . $source->getPluginId(),
        'config:project_browser.admin_settings',
        'config:core.extension',
      ]));
    }
    return $result + ['hit' => FALSE, 'max_age' => $ttl];
  }

}
