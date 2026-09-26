<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor_tool\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_site_advisor\Assessment\BriefCapabilities;
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
  description: new TranslatableMarkup("Send the original brief before building. The Decision model identifies capabilities in batches, decides whether ecosystem discovery helps, and searches enabled Project Browser sources separately for each capability. Returns a draft plan with existing configuration, candidate projects, open decisions, integration checks, source evidence and summed usage across requests. Larger plans take more time and provider usage. Uncertain choices remain unresolved. This is not an executable or verified installation plan. No packages are installed and no content or configuration is changed."),
  operation: ToolOperation::Explain,
  input_definitions: [
    'brief' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content brief'),
      description: new TranslatableMarkup('10–20,000 characters describing users, capabilities, content, constraints and presentation. Paragraphs and lists are supported.'),
      required: TRUE,
      constraints: ['Length' => ['min' => 10, 'max' => BriefCapabilities::MAX_BRIEF_LENGTH]],
    ),
    'catalog_query' => new InputDefinition(data_type: 'string', label: new TranslatableMarkup('Explicit search override'), description: new TranslatableMarkup('Normally omit: the Decision model decides whether and what to search from the brief. Advanced callers can supply up to 120 characters to explicitly request a catalog search and bypass automatic planning.'), required: FALSE, constraints: ['Length' => ['max' => 120]]),
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
