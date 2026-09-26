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
  ) {}

  /**
   * {@inheritdoc}
   */
  public function assess(string $brief, AccountInterface $account, string $catalog_query = ''): array {
    if (!$account->hasPermission('access ai site advisor')) {
      throw new AccessDeniedHttpException();
    }
    $brief = trim($brief);
    if (mb_strlen($brief) < 10 || mb_strlen($brief) > 4000) {
      throw new \InvalidArgumentException('Describe the requested content in 10 to 4,000 characters.');
    }
    $started = microtime(TRUE);
    $site = $this->context->collect($account);
    $discovery = $this->catalog->discover(trim($catalog_query) ?: $brief, $account, 12, trim($catalog_query) !== '');
    $recipes = $discovery['items'];
    $input = $this->profile->buildInput($brief, $site, $recipes);
    if (strlen($input->toString()) > 100000) {
      throw new \LengthException('The evidence is too large. Narrow the content types in AI Site Advisor settings or use more specific catalog keywords.');
    }
    $response = $this->decision->decide($input);
    $answers = [];
    foreach ($input->getQuestions() as $id => $question) {
      // Incomplete, malformed or invented options must never become advice.
      $answer = $response->getChoice($id);
      $probabilities = $answer->getProbabilities();
      $expected = $question->getOptionKeys();
      if (array_diff(array_keys($probabilities), $expected) || array_diff($expected, array_keys($probabilities)) || abs(array_sum($probabilities) - 1.0) > 0.01 || $answer->getProbability($answer->getChoice()) < max($probabilities)) {
        throw new \UnexpectedValueException('The Decision provider returned an invalid assessment distribution.');
      }
      $answers[$id] = $answer->toArray();
      // A display policy for a prototype, not a calibrated correctness claim.
      $answers[$id]['needs_review'] = $answer->getConfidence() < 0.7
        || $answer->getProbability($answer->getChoice()) < 0.75
        || in_array($answer->getChoice(), ['unknown', 'unclear'], TRUE);
      $answers[$id]['criterion'] = $question->getCriteria()[$answer->getChoice()];
    }
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
    $needs_review = $answers['content_model']['needs_review'] || $answers['presentation']['needs_review'] || $contradiction;
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
    return [
      'status' => $needs_review ? 'needs_clarification' : 'assessed',
      'summary' => $summary,
      'brief' => $brief,
      'profile' => ContentPlanningProfile::VERSION,
      'model' => $response->getModel(),
      'usage' => $response->toArray()['usage'],
      'elapsed_ms' => (int) round((microtime(TRUE) - $started) * 1000),
      'site' => $site,
      'recipes' => $recipes,
      'candidates' => $recipes,
      'discovery' => $discovery,
      'answers' => $answers,
      'questions' => $input->toArray()['questions'],
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
