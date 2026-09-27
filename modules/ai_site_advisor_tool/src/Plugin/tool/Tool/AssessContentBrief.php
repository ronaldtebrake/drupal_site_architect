<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor_tool\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_site_advisor\Assessment\BriefCapabilities;
use Drupal\ai_site_advisor\Assessment\AgentPlan;
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
  description: new TranslatableMarkup('Send the original brief before building or extending a Drupal site. Discovers relevant local capabilities and ecosystem projects. Returns a compact scored plan: work areas, existing configuration and field references, candidate building blocks, selected probabilities and confidence, open decisions and conditional Composer steps. Contribution, selection and coverage are separate judgments; preserve review flags. Choose among alternatives; do not install them all. Inspect dependencies and compatibility before implementing with other tools. Use detail="full" for complete source evidence, score distributions and diagnostics; it can be very large and performs a fresh assessment. Compact output reduces response size, not internal inference work. No site changes are made.'),
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
    'detail' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Response detail'),
      description: new TranslatableMarkup('compact (default): a scored building handoff with configuration pointers, open decisions and dependency steps. full: the complete assessment and diagnostics.'),
      required: FALSE,
      default_value: 'compact',
      constraints: ['AllowedValues' => ['choices' => ['compact', 'full']]],
    ),
  ],
  output_definitions: [
    'assessment' => new ContextDefinition(data_type: 'map', label: new TranslatableMarkup('Compact build handoff or full assessment'), required: TRUE),
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
    if (($values['detail'] ?? 'compact') === 'compact') {
      $result = AgentPlan::compact($result);
    }
    return ExecutableResult::success(new TranslatableMarkup('Planning handoff ready. Choose the parts to use, inspect dependencies, and validate before building.'), ['assessment' => $result]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'access ai site advisor');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
