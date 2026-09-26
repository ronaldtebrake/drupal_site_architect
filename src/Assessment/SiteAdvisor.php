<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

use Drupal\Core\Session\AccountInterface;
use Drupal\ai_site_advisor\Context\RecipeCatalog;
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
    private readonly RecipeCatalog $catalog,
    private readonly ContentPlanningProfile $profile,
    private readonly DecisionClientInterface $decision,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function assess(string $brief, AccountInterface $account): array {
    if (!$account->hasPermission('access ai site advisor')) {
      throw new AccessDeniedHttpException();
    }
    $brief = trim($brief);
    if (mb_strlen($brief) < 10 || mb_strlen($brief) > 4000) {
      throw new \InvalidArgumentException('Describe the requested content in 10 to 4,000 characters.');
    }
    $started = microtime(TRUE);
    $site = $this->context->collect($account);
    $recipes = $this->catalog->collect();
    $input = $this->profile->buildInput($brief, $site, $recipes);
    if (strlen($input->toString()) > 100000) {
      throw new \LengthException('The site evidence is too large. Narrow the content types in AI Site Advisor settings.');
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
      $answers['content_model']['needs_review'] || $contradiction => 'Clarify the brief before choosing an approach.',
      $model === 'page' && $presentation === 'canvas_page' => 'Explore a standalone Canvas page for this one-off composition.',
      (bool) $ready => 'Start with existing content: ' . implode(', ', array_map(fn ($id) => $site['bundles'][$id]['label'], $ready)) . '.',
      (bool) $extend => 'Inspect an extension of existing content: ' . implode(', ', array_map(fn ($id) => $site['bundles'][$id]['label'], $extend)) . '.',
      default => 'No clear reusable content type was identified in the inspected scope. Review the options before building.',
    };
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
      'answers' => $answers,
      'questions' => $input->toArray()['questions'],
      'reuse_candidates' => $ready,
      'extension_candidates' => $extend,
      'contradictory_judgments' => $contradiction,
      'follow_up' => implode(' ', $follow_up),
      'limitations' => [
        'Read-only advice; no content, configuration or recipe has been changed.',
        'Recipe results describe relevance only. Inspect config effects, dependencies and existing equivalents before applying.',
        'Confidence describes model uncertainty, not proven correctness. No eval calibration has been performed in phase one.',
      ],
    ];
  }

}
