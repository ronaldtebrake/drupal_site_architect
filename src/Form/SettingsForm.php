<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the policy and limits of evidence collection.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_site_advisor_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_site_advisor.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ai_site_advisor.settings');
    $form['site_policy'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Site planning policy'),
      '#default_value' => $config->get('site_policy'),
      '#maxlength' => 4000,
      '#description' => $this->t('Guidance for suitability, such as reuse preferences or editorial requirements. This cannot establish that a field or capability exists. Do not include credentials or private content.'),
    ];
    $form['included_bundles'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content types in scope'),
      '#default_value' => implode(', ', $config->get('included_bundles') ?? []),
      '#description' => $this->t('Optional comma-separated content type machine names. Empty includes all. At most 24 content types can be assessed at once.'),
    ];
    $form['provider'] = ['#markup' => '<p>' . $this->t('The advisor uses the default Decision provider and model configured in Drupal AI. Configure the TypeSafe provider to use Jev. Credentials remain managed by the provider and Key modules.') . '</p>'];
    $form['recipe_directories'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional recipe directories'),
      '#default_value' => implode("\n", $config->get('recipe_directories') ?? []),
      '#description' => $this->t('One directory per line, relative to the Composer project root or absolute. Core recipes, conventional recipe directories and installed Composer recipe packages are discovered automatically.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $ids = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $form_state->getValue('included_bundles'))))));
    if (count($ids) > 24 || array_filter($ids, static fn ($id) => !preg_match('/^[a-z0-9_]+$/D', $id))) {
      $form_state->setErrorByName('included_bundles', $this->t('Enter up to 24 valid content type machine names.'));
    }
    $form_state->set('bundle_ids', $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $directories = preg_split('/\R/', trim((string) $form_state->getValue('recipe_directories')), -1, PREG_SPLIT_NO_EMPTY);
    $this->config('ai_site_advisor.settings')
      ->set('site_policy', trim((string) $form_state->getValue('site_policy')))
      ->set('included_bundles', $form_state->get('bundle_ids'))
      ->set('recipe_directories', array_values(array_unique(array_map('trim', $directories))))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
