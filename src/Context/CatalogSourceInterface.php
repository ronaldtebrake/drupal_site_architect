<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

/**
 * Supplies bounded, attributed discovery results without installing anything.
 */
interface CatalogSourceInterface {

  /**
   * Whether querying this source may contact an external service.
   */
  public function isRemote(): bool;

  /**
   * Returns items, source reports and warnings for this query.
   */
  public function search(string $query, int $limit): array;

}
