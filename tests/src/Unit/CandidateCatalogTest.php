<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_site_advisor\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_site_advisor\Context\CandidateCatalog;
use Drupal\ai_site_advisor\Context\CatalogSourceInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Tests discovery boundaries, source failures and fair candidate limits.
 */
#[Group('ai_site_advisor')]
final class CandidateCatalogTest extends UnitTestCase {

  /**
   * Returns an account with the requested discovery permission.
   */
  private function account(bool $allowed = TRUE): AccountInterface {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturn($allowed);
    return $account;
  }

  /**
   * Builds a fake source response, never a simulated live result.
   */
  private function source(bool $remote, string $prefix): CatalogSourceInterface {
    $source = $this->createMock(CatalogSourceInterface::class);
    $source->method('isRemote')->willReturn($remote);
    $items = [];
    for ($i = 0; $i < 4; $i++) {
      $items[] = [
        'kind' => 'recipe',
        'package' => $prefix . '/recipe_' . $i,
        'machine_name' => 'recipe_' . $i,
        'source' => $prefix,
      ];
    }
    $source->method('search')->willReturn(['items' => $items, 'sources' => [], 'warnings' => []]);
    return $source;
  }

  /**
   * Local matches must not crowd all ecosystem results out of the shortlist.
   */
  public function testFairLimitAndStableIds(): void {
    $catalog = new CandidateCatalog();
    $catalog->addSource($this->source(FALSE, 'local'));
    $catalog->addSource($this->source(TRUE, 'remote'));
    $first = $catalog->discover('workflow', $this->account(), 4);
    $this->assertSame(['local', 'remote', 'local', 'remote'], array_column($first['items'], 'source'));
    $this->assertTrue($first['truncated']);
    $this->assertSame(array_keys($first['items']), array_keys($catalog->discover('workflow', $this->account(), 4)['items']));
  }

  /**
   * Site briefs must not be sent to ecosystem sources implicitly.
   */
  public function testLocalOnlyDoesNotCallRemote(): void {
    $remote = $this->createMock(CatalogSourceInterface::class);
    $remote->method('isRemote')->willReturn(TRUE);
    $remote->expects($this->never())->method('search');
    $catalog = new CandidateCatalog();
    $catalog->addSource($remote);
    $result = $catalog->discover('Private project brief', $this->account(), 12, FALSE);
    $this->assertNotEmpty($result['warnings']);
  }

  /**
   * Failures are explicit and do not discard other sources' evidence.
   */
  public function testPartialFailureIsVisible(): void {
    $failed = $this->createMock(CatalogSourceInterface::class);
    $failed->method('search')->willThrowException(new \RuntimeException('Secret provider error'));
    $catalog = new CandidateCatalog();
    $catalog->addSource($this->source(FALSE, 'local'));
    $catalog->addSource($failed);
    $result = $catalog->discover('workflow', $this->account());
    $this->assertCount(4, $result['items']);
    $this->assertNotEmpty($result['warnings']);
    $this->assertStringNotContainsString('Secret', implode(' ', $result['warnings']));
  }

  /**
   * Unauthorized users cannot invoke even a read-only source.
   */
  public function testAccessBeforeSources(): void {
    $source = $this->createMock(CatalogSourceInterface::class);
    $source->expects($this->never())->method('search');
    $catalog = new CandidateCatalog();
    $catalog->addSource($source);
    $this->expectException(AccessDeniedHttpException::class);
    $catalog->discover('workflow', $this->account(FALSE));
  }

  /**
   * Independent searches retain coverage and merge duplicate provenance.
   */
  public function testMultipleQueriesRetainCoverage(): void {
    $source = $this->createMock(CatalogSourceInterface::class);
    $source->method('isRemote')->willReturn(TRUE);
    $source->method('search')->willReturnCallback(static function ($query): array {
      $items = [];
      foreach ([$query, 'shared'] as $name) {
        $items[] = ['kind' => 'module', 'package' => 'fixture/' . $name, 'machine_name' => $name, 'source' => 'fixture'];
      }
      return ['items' => $items, 'sources' => [], 'warnings' => []];
    });
    $catalog = new CandidateCatalog();
    $catalog->addSource($source);
    $result = $catalog->discoverMany(['event', 'group'], $this->account(), 3);
    $items = array_column($result['items'], NULL, 'package');
    $this->assertSame(['fixture/event', 'fixture/group', 'fixture/shared'], array_keys($items));
    $this->assertSame(['event', 'group'], $items['fixture/shared']['matched_queries']);
    $this->assertSame(['event', 'group'], $result['queries']);
    $this->assertCount(2, $result['searches']);
  }

  /**
   * An invalid later query must not allow an earlier one to contact a source.
   */
  public function testBatchValidationBeforeSources(): void {
    $source = $this->createMock(CatalogSourceInterface::class);
    $source->expects($this->never())->method('search');
    $catalog = new CandidateCatalog();
    $catalog->addSource($source);
    $this->expectException(\InvalidArgumentException::class);
    $catalog->discoverMany(['event', ''], $this->account());
  }

  /**
   * Batch searches preserve the service permission boundary.
   */
  public function testBatchAccessBeforeSources(): void {
    $source = $this->createMock(CatalogSourceInterface::class);
    $source->expects($this->never())->method('search');
    $catalog = new CandidateCatalog();
    $catalog->addSource($source);
    $this->expectException(AccessDeniedHttpException::class);
    $catalog->discoverMany(['group'], $this->account(FALSE));
  }

}
