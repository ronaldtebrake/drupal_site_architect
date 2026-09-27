<?php

declare(strict_types=1);

namespace Drupal\site_architect_test\Plugin\ProjectBrowserSource;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\project_browser\Attribute\ProjectBrowserSource;
use Drupal\project_browser\Plugin\ProjectBrowserSourceBase;
use Drupal\project_browser\ProjectBrowser\Project;
use Drupal\project_browser\ProjectBrowser\ProjectsResultsPage;
use Drupal\project_browser\ProjectType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A public Project Browser source contract fixture; never contacts a network.
 */
#[ProjectBrowserSource(
  id: 'architect_fixture',
  label: new TranslatableMarkup('Architect fixture catalog'),
  description: new TranslatableMarkup('Fixtures for adapter tests.'),
)]
final class ArchitectFixture extends ProjectBrowserSourceBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterDefinitions(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $query = []): ProjectsResultsPage {
    \Drupal::state()->set('architect_test.query', $query);
    \Drupal::state()->set('architect_test.calls', \Drupal::state()->get('architect_test.calls', 0) + 1);
    if (\Drupal::state()->get('architect_test.fail')) {
      throw new \RuntimeException('Private source credentials must not leak.');
    }
    return $this->createResultsPage([
      new Project(
        logo: NULL,
        isCompatible: TRUE,
        machineName: 'editorial_recipe',
        body: ['value' => '<p>Editorial review &amp; approval.</p>'],
        title: 'Editorial recipe',
        packageName: 'example/editorial-recipe',
        url: Url::fromUri('https://catalog.example.test/recipe'),
        type: ProjectType::Recipe,
      ),
      new Project(
        logo: NULL,
        isCompatible: TRUE,
        machineName: 'system',
        body: ['value' => 'Enabled system module.'],
        title: 'System',
        packageName: 'drupal/core',
      ),
    ], 3, \Drupal::state()->get('architect_test.error'));
  }

}
