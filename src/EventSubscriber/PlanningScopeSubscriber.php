<?php

declare(strict_types=1);

namespace Drupal\site_architect\EventSubscriber;

use Drupal\Core\Recipe\RecipeAppliedEvent;
use Drupal\site_architect\Integration\PlanningScopeInstaller;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Completes the planning integration when a recipe skips optional config.
 */
final class PlanningScopeSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly PlanningScopeInstaller $scopeInstaller,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RecipeAppliedEvent::class => 'onRecipeApplied'];
  }

  /**
   * Registers only our missing scope after relevant installation recipes.
   */
  public function onRecipeApplied(RecipeAppliedEvent $event): void {
    if (!array_intersect(['simple_oauth', 'site_architect'], $event->recipe->install->modules)) {
      return;
    }
    // Recipes deliberately skip optional configuration. Wait until their own
    // configuration is in place; never overwrite a scope supplied by a recipe
    // or administrator, and do not recreate scopes after unrelated recipes.
    $this->scopeInstaller->ensureScope();
  }

}
