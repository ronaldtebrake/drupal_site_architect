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
  description: new TranslatableMarkup("Before creating content types or Canvas layouts, assess a brief against this site's real node fields, enabled capabilities and a bounded core-recipe catalog. Returns typed content-model, presentation, reuse and recipe-relevance judgments with evidence and uncertainty. Uses the configured AI Decision provider. Advice only: no recipe is applied and no content or configuration is changed. Resolve uncertainty and validate compatibility with separate tools before building."),
  operation: ToolOperation::Explain,
  input_definitions: [
    'brief' => new InputDefinition(data_type: 'string', label: new TranslatableMarkup('Content brief'), description: new TranslatableMarkup('10–4,000 characters describing the content, required fields, filtering, reuse and presentation.'), required: TRUE),
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
    $result = $this->advisor->assess($values['brief'], $this->currentUser);
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
