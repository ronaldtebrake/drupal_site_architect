<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_site_advisor\Assessment\SiteAdvisorInterface;
use Drupal\ai_site_advisor\Context\RecipeCatalog;
use Drupal\ai_site_advisor\Context\SiteContextCollectorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A normal Drupal form exercising the same service offered to agents.
 */
final class AdvisorForm extends FormBase {

  /**
   * Constructs the form.
   */
  public function __construct(
    protected SiteAdvisorInterface $advisor,
    protected SiteContextCollectorInterface $context,
    protected RecipeCatalog $catalog,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self($container->get('ai_site_advisor.advisor'), $container->get('ai_site_advisor.context'), $container->get('ai_site_advisor.catalog'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_site_advisor_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attributes']['class'][] = 'site-advisor';
    $form['#attached']['library'][] = 'ai_site_advisor/advisor';
    $form['#prefix'] = '<div id="site-advisor-form">';
    $form['#suffix'] = '</div>';
    $form['#cache']['max-age'] = 0;
    $form['intro'] = [
      '#weight' => -50,
      '#type' => 'inline_template',
      '#template' => '<header class="sa-hero"><span class="sa-eyebrow">{{ eyebrow }}</span><h2>{{ title }}</h2><p>{{ body }}</p><div class="sa-flow"><span>01 · {{ a }}</span><span>02 · {{ b }}</span><span>03 · {{ c }}</span></div></header>',
      '#context' => [
        'eyebrow' => $this->t('Drupal + typed AI decisions'),
        'title' => $this->t('A better starting point for your build.'),
        'body' => $this->t('Describe what you need. Compare what this site already supports with recipes and modules from configured catalogs before building.'),
        'a' => $this->t('Inspect this site'),
        'b' => $this->t('Discover existing solutions'),
        'c' => $this->t('Assess before building'),
      ],
    ];
    $form['examples'] = ['#type' => 'container', '#weight' => -40, '#attributes' => ['class' => ['sa-examples']]];
    $examples = [
      'workshops' => [
        $this->t('Recurring workshops'),
        'We run recurring workshops. Editors need to store a date, location, capacity and description for each workshop, filter the listing by location, and present each workshop consistently with a shared visual layout. Reuse a suitable existing content type if possible.',
      ],
      'campaign' => [
        $this->t('One campaign page'),
        'Create a one-off visual campaign landing page with a hero, testimonials and a call to action. Editors want to freely arrange its sections. We do not need a collection of reusable records or structured filtering.',
      ],
      'news' => [
        $this->t('News + review'),
        'Our editors publish news articles regularly with a title, body and image. We need a draft, review and publish editorial workflow before these articles go live. Use an existing suitable content type where possible.',
      ],
      'unclear' => [
        $this->t('An unclear brief'),
        'We need something better for our website. Please help us figure out the right approach.',
      ],
      'workflow' => [
        $this->t('Editorial workflow'),
        'We need an editorial workflow for our existing news content. Writers should save drafts, editors review them and then publish approved articles. Check what the site already supports, and compare available workflow recipes or modules before proposing custom development.',
      ],
    ];
    foreach ($examples as $id => [$label, $brief]) {
      $form['examples'][$id] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $label,
        '#attributes' => [
          'type' => 'button',
          'class' => ['sa-example'],
          'data-advisor-example' => $brief,
          'data-advisor-search' => $id === 'workflow' ? 'workflow' : '',
        ],
      ];
    }
    $form['brief'] = [
      '#weight' => -30,
      '#type' => 'textarea',
      '#title' => $this->t('What are you planning?'),
      '#required' => TRUE,
      '#rows' => 4,
      '#maxlength' => 4000,
      '#default_value' => $form_state->getValue('brief') ?? $examples['workshops'][1],
      '#description' => $this->t('Include what editors will maintain, which information must be stored or filtered, and how it should be presented.'),
      '#attributes' => ['data-advisor-brief' => 'true'],
    ];
    $form['actions'] = ['#type' => 'actions', '#weight' => -20];
    $form['catalog_query'] = [
      '#type' => 'textfield',
      '#weight' => -25,
      '#title' => $this->t('Search the ecosystem for'),
      '#default_value' => $form_state->getValue('catalog_query') ?? '',
      '#maxlength' => 120,
      '#description' => $this->t('Optional short keywords, for example workflow. Searches enabled Project Browser sources when the integration is installed. Leave empty to assess local recipe files only. Search keywords go to configured catalogs; your site evidence goes only to the Decision provider.'),
      '#attributes' => ['data-advisor-query' => 'true'],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Assess this brief'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::refresh',
        'wrapper' => 'site-advisor-form',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Inspecting this site and asking the Decision model…'),
        ],
      ],
    ];
    $form['notice'] = [
      '#weight' => -10,
      '#markup' => '<p class="sa-note">' . $this->t('This sends your brief and selected site-structure metadata to the configured AI Decision provider. It creates advice only; building remains a separate step.') . '</p>',
    ];
    if ($error = $form_state->get('advisor_error')) {
      $form['error'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--error'], 'role' => 'alert'],
        'text' => ['#plain_text' => $error],
      ];
    }
    if ($assessment = $form_state->get('assessment')) {
      $form['result'] = ['#theme' => 'ai_site_advisor_result', '#assessment' => $assessment];
    }
    try {
      $form['context'] = [
        '#theme' => 'ai_site_advisor_context',
        '#snapshot' => $this->context->collect($this->currentUser()),
        '#recipes' => $this->catalog->collect(),
      ];
    }
    catch (\LengthException $e) {
      $form['context'] = ['#plain_text' => $e->getMessage()];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $length = mb_strlen(trim((string) $form_state->getValue('brief')));
    if ($length < 10 || $length > 4000) {
      $form_state->setErrorByName('brief', $this->t('Use between 10 and 4,000 characters.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->set('assessment', NULL)->set('advisor_error', NULL);
    try {
      $form_state->set('assessment', $this->advisor->assess((string) $form_state->getValue('brief'), $this->currentUser(), (string) $form_state->getValue('catalog_query')));
    }
    catch (\LengthException $e) {
      $form_state->set('advisor_error', $e->getMessage());
    }
    catch (\UnexpectedValueException) {
      $form_state->set('advisor_error', $this->t('The Decision provider returned an incomplete or inconsistent assessment. Refine the brief or retry. No partial advice or site changes were produced.'));
      $this->getLogger('ai_site_advisor')->warning('Assessment rejected by the response contract checks. No provider response was logged.');
    }
    catch (\Throwable $e) {
      // Provider errors can contain request data. Never echo or log raw errors.
      $form_state->set('advisor_error', $this->t('The assessment could not be completed. Check the default Decision provider and model in Drupal AI, then retry. No changes were made.'));
      $this->getLogger('ai_site_advisor')->warning('Assessment failed (@type). Raw provider errors are intentionally omitted.', ['@type' => get_class($e)]);
    }
    $form_state->setRebuild();
  }

  /**
   * Refreshes the form after assessment, preserving Drupal form protection.
   */
  public function refresh(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

}
