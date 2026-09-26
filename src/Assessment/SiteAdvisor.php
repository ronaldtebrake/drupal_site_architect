<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Drupal\Core\Session\AccountInterface;
use Drupal\ai_site_advisor\Context\CandidateCatalog;
use Drupal\ai_site_advisor\Context\SiteContextCollectorInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Combines inspected evidence with typed judgments, without side effects.
 */
final class SiteAdvisor implements SiteAdvisorInterface {

  /**
   * Constructs the advisor.
   */
  public function __construct(
    private readonly SiteContextCollectorInterface $context,
    private readonly CandidateCatalog $catalog,
    private readonly ContentPlanningProfile $profile,
    private readonly DecisionClientInterface $decision,
    private readonly SearchPlannerInterface $searchPlanner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function assess(string $brief, AccountInterface $account, string $catalog_query = ''): array {
    if (!$account->hasPermission('access ai site advisor')) {
      throw new AccessDeniedHttpException();
    }
    $brief = trim($brief);
    if (mb_strlen($brief) < 10 || mb_strlen($brief) > BriefCapabilities::MAX_BRIEF_LENGTH) {
      throw new \InvalidArgumentException('Describe the requested content in 10 to 20,000 characters.');
    }
    $started = microtime(TRUE);
    $site = $this->context->collect($account);
    $search_plan = [
      'action' => 'unavailable',
      'query' => NULL,
      'reason' => 'No ecosystem adapter is installed. Local recipe manifests and current site configuration were considered.',
      'needs_review' => FALSE,
      'usage' => NULL,
    ];
    if (trim($catalog_query) !== '') {
      // Retain the explicit keyword override for existing programmatic callers.
      $search_plan = [
        'action' => 'search',
        'query' => trim($catalog_query),
        'reason' => 'The caller supplied explicit search keywords.',
        'needs_review' => FALSE,
        'usage' => NULL,
      ];
    }
    elseif ($this->catalog->hasRemoteSources()) {
      $search_plan = $this->searchPlanner->plan($brief, $site);
    }
    $search = $search_plan['action'] === 'search';
    $queries = $search_plan['queries'] ?? ($search ? [$search_plan['query']] : []);
    $capabilities = $search_plan['capabilities'] ?? [];
    if (!$capabilities) {
      $capabilities = [
        'brief' => [
          'id' => 'brief',
          'label' => 'The requested build',
          'query' => $search_plan['query'],
          'source_text' => $brief,
        ],
      ];
    }
    $discovery = $search && count($queries) > 1
      ? $this->catalog->discoverMany($queries, $account)
      : $this->catalog->discover($search ? $queries[0] : $brief, $account, 12, $search);
    $discovery['searched_ecosystem'] = $search;
    $recipes = $discovery['items'];
    $batch = DecisionBatch::run($this->decision, $this->profile->buildInputs($brief, $site, $recipes, $capabilities));
    $response = $batch['response'];
    $answers = [];
    foreach ($batch['questions'] as $id => $question) {
      // Incomplete, malformed or invented options must never become advice.
      $answer = $response->getChoice($id);
      ChoiceValidator::validate($answer, $question);
      $answers[$id] = $answer->toArray();
      // A display policy for a prototype, not a calibrated correctness claim.
      $answers[$id]['needs_review'] = $answer->getConfidence() < 0.7
        || $answer->getProbability($answer->getChoice()) < 0.75
        || in_array($answer->getChoice(), ['unknown', 'unclear', 'unresolved'], TRUE);
      $answers[$id]['criterion'] = $question->getCriteria()[$answer->getChoice()];
    }
    $plan = CapabilityPlan::build($site, $recipes, $capabilities, $answers);
    $ready = [];
    $extend = [];
    foreach ($site['bundles'] as $id => $bundle) {
      $answer = $answers['bundle__' . $id];
      if (!$answer['needs_review'] && $answer['choice'] === 'ready') {
        $ready[] = $id;
      }
      if (!$answer['needs_review'] && $answer['choice'] === 'extend') {
        $extend[] = $id;
      }
    }
    $workflow_candidates = [];
    foreach ($site['workflows'] ?? [] as $id => $workflow) {
      $answer = $answers['workflow__' . $id];
      if (!$answer['needs_review'] && in_array($answer['choice'], ['ready', 'extend'], TRUE)) {
        $workflow_candidates[$id] = $answer['choice'];
      }
    }
    $adoption_candidates = [];
    foreach ($recipes as $id => $candidate) {
      $answer = $answers['recipe__' . $id];
      if (!$answer['needs_review'] && $answer['choice'] === 'relevant') {
        $adoption_candidates[] = $id;
      }
    }
    // Independent judgments can disagree. Do not turn a contradiction into a
    // confident recommendation for a builder to follow.
    $model = $answers['content_model']['choice'];
    $presentation = $answers['presentation']['choice'];
    $contradiction = ($presentation === 'canvas_page' && in_array($model, ['records', 'mixed'], TRUE))
      || ($presentation === 'canvas_template' && $model === 'page')
      || ($presentation === 'canvas_both' && $model !== 'mixed');
    $needs_review = $answers['content_model']['needs_review'] || $answers['presentation']['needs_review'] || $contradiction || $search_plan['needs_review'];
    $needs_review = $needs_review || (bool) array_filter($plan['areas'], static fn ($area) => $area['needs_review']) || ($search_plan['terms_truncated'] ?? FALSE);
    if ($contradiction) {
      $answers['presentation']['needs_review'] = TRUE;
    }
    $summary = match (TRUE) {
      (bool) $workflow_candidates => 'Inspect existing workflow configuration before adding another solution.',
      $answers['content_model']['needs_review'] || $contradiction => 'Clarify the brief before choosing an approach.',
      $model === 'page' && $presentation === 'canvas_page' => 'Explore a standalone Canvas page for this one-off composition.',
      (bool) $ready => 'Start with existing content: ' . implode(', ', array_map(fn ($id) => $site['bundles'][$id]['label'], $ready)) . '.',
      (bool) $extend => 'Inspect an extension of existing content: ' . implode(', ', array_map(fn ($id) => $site['bundles'][$id]['label'], $extend)) . '.',
      default => 'No clear reusable content type was identified in the inspected scope. Review the options before building.',
    };
    if (!$ready && !$extend && !$workflow_candidates && $adoption_candidates) {
      $summary = 'Compare the discovered solutions before designing something custom.';
    }
    $follow_up = [];
    if ($search_plan['needs_review']) {
      $follow_up[] = $search_plan['reason'];
    }
    if ($answers['content_model']['needs_review']) {
      $follow_up[] = 'Will editors maintain repeated records, a one-off page, or both? Specify the attributes that must be stored or filtered.';
    }
    if ($answers['presentation']['needs_review']) {
      $follow_up[] = 'Choose the presentation: standard entity displays, a shared visual template, or a separately composed landing page. Confirm which parts editors need to arrange visually.';
    }
    if ($contradiction) {
      $follow_up[] = 'The content-model and presentation judgments conflict. Resolve this before using build tools.';
    }
    if (!$follow_up) {
      $follow_up[] = 'Verify the proposed configuration and resolve uncertain matches before using separate build tools.';
    }
    $assessment_usage = $response->toArray()['usage'];
    $usage = $assessment_usage;
    if ($search_plan['usage'] !== NULL) {
      foreach ($usage as $key => $value) {
        $planning_value = $search_plan['usage'][$key] ?? NULL;
        $usage[$key] = $value !== NULL && $planning_value !== NULL ? $value + $planning_value : NULL;
      }
    }
    return [
      'status' => $needs_review ? 'needs_clarification' : 'assessed',
      'summary' => $summary,
      'brief' => $brief,
      'profile' => ContentPlanningProfile::VERSION,
      'model' => $response->getModel(),
      'usage' => $usage,
      'usage_by_stage' => ['search_planning' => $search_plan['usage'], 'assessment' => $assessment_usage],
      'search_plan' => $search_plan,
      'plan' => $plan,
      'elapsed_ms' => (int) round((microtime(TRUE) - $started) * 1000),
      'site' => $site,
      'recipes' => $recipes,
      'candidates' => $recipes,
      'discovery' => $discovery,
      'answers' => $answers,
      'questions' => array_map(static fn ($question) => $question->toArray(), $batch['questions']),
      'requests_by_stage' => ['search_planning' => $search_plan['requests'] ?? [], 'assessment' => $batch['requests']],
      'reuse_candidates' => $ready,
      'extension_candidates' => $extend,
      'workflow_candidates' => $workflow_candidates,
      'adoption_candidates' => $adoption_candidates,
      'build_guidance' => 'Custom development is an option after checking gaps in the existing site and the discovered candidates. A bounded search cannot establish that no reusable solution exists.',
      'contradictory_judgments' => $contradiction,
      'follow_up' => implode(' ', $follow_up),
      'limitations' => [
        'Read-only advice; no content, configuration or recipe has been changed.',
        'Catalog results describe relevance only. Inspect config effects, package dependencies and existing equivalents before installing or applying.',
        'Confidence describes model uncertainty, not proven correctness. No eval calibration has been performed in phase one.',
      ],
    ];
  }

}
