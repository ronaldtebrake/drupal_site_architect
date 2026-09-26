<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Combines discovery sources while retaining search limits and provenance.
 */
class CandidateCatalog {

  /**
   * The registered discovery sources.
   *
   * @var \Drupal\ai_site_advisor\Context\CatalogSourceInterface[]
   */
  private array $sources = [];

  /**
   * Registers a tagged source.
   */
  public function addSource(CatalogSourceInterface $source): void {
    $this->sources[] = $source;
  }

  /**
   * Whether an ecosystem adapter is available, without querying it.
   */
  public function hasRemoteSources(): bool {
    return (bool) array_filter($this->sources, static fn ($source) => $source->isRemote());
  }

  /**
   * Searches several capabilities and retains evidence from every query.
   */
  public function discoverMany(array $queries, AccountInterface $account, int $limit = 24): array {
    if (!$account->hasPermission('access ai site advisor')) {
      throw new AccessDeniedHttpException();
    }
    if (!$queries || count($queries) > 6 || $limit < 1 || $limit > 24) {
      throw new \InvalidArgumentException('Use 1–6 capability searches and a limit of 1–24.');
    }
    // Validate the entire batch before contacting any source.
    foreach ($queries as $query) {
      if (!is_string($query) || trim($query) === '' || mb_strlen($query) > 120) {
        throw new \InvalidArgumentException('Use non-empty capability terms of up to 120 characters.');
      }
    }
    $queries = array_values(array_unique(array_map('trim', $queries)));
    $all = $groups = $sources = $warnings = $searches = [];
    $truncated = FALSE;
    foreach ($queries as $query) {
      $result = $this->discover($query, $account, 12);
      $groups[] = array_keys($result['items']);
      foreach ($result['items'] as $id => $item) {
        $all[$id] ??= $item;
        $all[$id]['matched_queries'][] = $query;
      }
      foreach ($result['sources'] as $source) {
        $sources[] = $source + ['query' => $query];
      }
      $searches[] = [
        'query' => $query,
        'returned' => $result['returned'],
        'truncated' => $result['truncated'],
        'warnings' => $result['warnings'],
      ];
      $warnings = array_merge($warnings, $result['warnings']);
      $truncated = $truncated || $result['truncated'];
    }
    $selected = [];
    for ($index = 0; $index < 12 && count($selected) < $limit; $index++) {
      foreach ($groups as $group) {
        if (isset($group[$index]) && count($selected) < $limit) {
          $selected[$group[$index]] = $all[$group[$index]];
        }
      }
    }
    return [
      'query' => implode(', ', $queries),
      'queries' => $queries,
      'items' => $selected,
      'sources' => $sources,
      'searches' => $searches,
      'warnings' => array_values(array_unique($warnings)),
      'returned' => count($selected),
      'truncated' => $truncated || count($all) > count($selected),
      'scope' => 'Up to six capability searches, twelve candidates per search and twenty-four distinct candidates assessed. Queries share the final budget. Missing results do not establish that no solution exists.',
      'retrieved_at' => gmdate(DATE_ATOM),
    ];
  }

  /**
   * Searches configured sources. Remote searches require explicit keywords.
   */
  public function discover(string $query, AccountInterface $account, int $limit = 12, bool $include_remote = TRUE): array {
    if (!$account->hasPermission('access ai site advisor')) {
      throw new AccessDeniedHttpException();
    }
    $query = trim($query);
    if ($limit < 1 || $limit > 24 || $query === '' || mb_strlen($query) > ($include_remote ? 120 : 4000)) {
      throw new \InvalidArgumentException('Use a non-empty search (up to 120 characters for external sources) and a limit of 1–24.');
    }
    $items = [];
    $reports = [];
    $warnings = [];
    $has_remote = FALSE;
    $groups = [];
    foreach ($this->sources as $source) {
      $has_remote = $has_remote || $source->isRemote();
      if ($source->isRemote() && !$include_remote) {
        continue;
      }
      try {
        $result = $source->search($query, $limit);
        $group = [];
        foreach ($result['items'] as $item) {
          $identity = $item['kind'] . ':' . $item['package'];
          if ($item['package'] === 'drupal/core') {
            $identity .= ':' . $item['machine_name'];
          }
          $id = 'c_' . substr(hash('sha256', $identity), 0, 20);
          if (isset($items[$id])) {
            $items[$id]['also_reported_by'][] = $item['source'];
          }
          else {
            $item['id'] = $id;
            $items[$id] = $item;
            $group[] = $id;
          }
        }
        $groups[] = $group;
        $reports = array_merge($reports, $result['sources']);
        $warnings = array_merge($warnings, $result['warnings']);
      }
      catch (\Throwable) {
        $warnings[] = 'A discovery source failed. Its results are unavailable; retry or inspect that source separately.';
      }
    }
    if (!$has_remote) {
      $warnings[] = 'No ecosystem discovery adapter is installed. Only local recipe files were searched.';
    }
    elseif (!$include_remote) {
      $warnings[] = 'External sources were not queried for this assessment.';
    }
    $truncated = count($items) > $limit;
    $selected = [];
    // Round-robin across adapters so local matches do not hide the ecosystem.
    for ($index = 0; $index < $limit && count($selected) < $limit; $index++) {
      foreach ($groups as $group) {
        if (isset($group[$index]) && count($selected) < $limit) {
          $selected[$group[$index]] = $items[$group[$index]];
        }
      }
    }
    return [
      'query' => $query,
      'items' => $selected,
      'sources' => $reports,
      'warnings' => array_values(array_unique($warnings)),
      'returned' => count($selected),
      'truncated' => $truncated || (bool) array_filter($reports, static fn ($report) => $report['truncated'] ?? FALSE),
      'scope' => 'A bounded keyword search of configured sources, not an exhaustive ecosystem search. No matches or source failures are not evidence that custom development is necessary.',
      'retrieved_at' => gmdate(DATE_ATOM),
    ];
  }

}
