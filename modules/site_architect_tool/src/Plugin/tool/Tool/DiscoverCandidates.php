<?php

declare(strict_types=1);

namespace Drupal\site_architect_tool\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\site_architect\Context\CandidateCatalog;
use Drupal\site_architect\Assessment\AgentPlan;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Discovers existing solutions before an agent proposes custom development.
 */
#[Tool(
  id: 'site_architect:discover_candidates',
  label: new TranslatableMarkup('Discover Drupal recipes and modules'),
  description: new TranslatableMarkup('Search local recipe manifests and enabled Project Browser sources using short keywords. Returns compact candidate pointers, local availability and conditional Composer steps by default. Use detail="full" for source descriptions and reports. Results are catalog matches, not installation recommendations. No model call or site changes occur. For a plan from an original brief, call assess_content_brief directly. Results are bounded; try other terms before concluding a custom build is needed.'),
  operation: ToolOperation::Read,
  input_definitions: [
    'query' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Catalog keywords'),
      description: new TranslatableMarkup('1–120 characters. These keywords may be sent to configured catalog providers. Do not include confidential site details.'),
      required: TRUE,
      constraints: ['Length' => ['min' => 1, 'max' => 120]],
    ),
    'detail' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Response detail'),
      description: new TranslatableMarkup('compact (default): package pointers and dependency steps. full: complete candidates and source reports.'),
      required: FALSE,
      default_value: 'compact',
      constraints: ['AllowedValues' => ['choices' => ['compact', 'full']]],
    ),
  ],
  output_definitions: [
    'discovery' => new ContextDefinition(data_type: 'map', label: new TranslatableMarkup('Candidates and source reports'), required: TRUE),
  ],
)]
final class DiscoverCandidates extends ToolBase {

  /**
   * The catalog service.
   */
  protected CandidateCatalog $catalog;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->catalog = $container->get('site_architect.candidates');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $result = $this->catalog->discover($values['query'], $this->currentUser);
    if (($values['detail'] ?? 'compact') === 'compact') {
      $result = AgentPlan::discovery($result);
    }
    return ExecutableResult::success(new TranslatableMarkup('Discovery complete. Review scope and compatibility before choosing a solution.'), ['discovery' => $result]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'access site architect');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
