<?php

declare(strict_types=1);

namespace Drupal\site_architect\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\site_architect\Assessment\BriefCapabilities;
use Drupal\site_architect\Assessment\AgentPlan;
use Drupal\site_architect\Assessment\SiteArchitectInterface;
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
  id: 'site_architect:assess_content_brief',
  label: new TranslatableMarkup('Assess a Drupal content brief'),
  description: new TranslatableMarkup('Start a Drupal planning conversation from the original user brief. Returns scored building blocks, source excerpts, configuration pointers and a continuation with decisions and next actions. Present a connected provisional plan, compare up to three relevant approaches, then ask the first question that changes the design. Continue with the original brief plus confirmed answers. Ambiguity may trigger a bounded public catalog lookup to inform the conversation; it does not establish an implementation. Preserve uncertainty and inspect compatibility before building. Use detail="full" for complete evidence and diagnostics; this performs a fresh assessment. No site changes are made.'),
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
   * The shared read-only architect.
   */
  protected SiteArchitectInterface $architect;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->architect = $container->get('site_architect.architect');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    // The service repeats access checks even if a caller skips tool->access().
    $result = $this->architect->assess($values['brief'], $this->currentUser, $values['catalog_query'] ?? '');
    $compact = AgentPlan::compact($result);
    if (($values['detail'] ?? 'compact') === 'compact') {
      $result = $compact;
    }
    else {
      $result['continuation'] = $compact['continuation'];
    }
    return ExecutableResult::success(new TranslatableMarkup('Continue planning using continuation: explain the evidence-backed options, resolve the next decision, and refine the brief before building.'), ['assessment' => $result]);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $result = AccessResult::allowedIfHasPermission($account, 'access site architect');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
