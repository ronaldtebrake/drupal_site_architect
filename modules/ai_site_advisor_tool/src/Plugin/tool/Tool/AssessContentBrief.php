<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor_tool\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_site_advisor\Assessment\SiteAdvisorInterface;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Assesses before building; this tool never mutates the site.
 */
#[Tool(
  id: 'ai_site_advisor:assess_content_brief',
  label: new TranslatableMarkup('Assess a Drupal content brief'),
  description: new TranslatableMarkup("Before building, assess a brief against this site's actual node fields, moderation workflows and discovered recipes or modules. Supply catalog_query to search enabled Project Browser sources as well as local recipe manifests. Returns typed content-model, presentation, reuse, workflow-fit and candidate-relevance judgments with evidence and uncertainty. Uses the configured AI Decision provider. Advice only: no packages are installed and no content or configuration is changed. Resolve uncertainty and verify compatibility with separate tools before building."),
  operation: ToolOperation::Explain,
  input_definitions: [
    'brief' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content brief'),
      description: new TranslatableMarkup('10–4,000 characters describing the requested content or workflow, fields, reuse and presentation.'),
      required: TRUE,
      constraints: ['Length' => ['min' => 10, 'max' => 4000]],
    ),
    'catalog_query' => new InputDefinition(data_type: 'string', label: new TranslatableMarkup('Catalog search keywords'), description: new TranslatableMarkup('Optional short keywords (maximum 120 characters) to search configured ecosystem sources, for example workflow. Without this, only local recipes are considered.'), required: FALSE, constraints: ['Length' => ['max' => 120]]),
  ],
  output_definitions: [
    'assessment' => new ContextDefinition(data_type: 'map', label: new TranslatableMarkup('Assessment and evidence'), required: TRUE),
  ],
)]
final class AssessContentBrief extends ToolBase {

  /**
   * The shared read-only advisor.
   */
  protected SiteAdvisorInterface $advisor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->advisor = $container->get('ai_site_advisor.advisor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    // The service repeats access checks even if a caller skips tool->access().
    $result = $this->advisor->assess($values['brief'], $this->currentUser, $values['catalog_query'] ?? '');
    return ExecutableResult::success(new TranslatableMarkup('Content brief assessed. Review the evidence and uncertainty before building.'), ['assessment' => $result]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'access ai site advisor');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
