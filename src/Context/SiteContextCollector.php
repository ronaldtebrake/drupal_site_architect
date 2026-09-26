<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Reads only the metadata required for content planning.
 */
final class SiteContextCollector implements SiteContextCollectorInterface {

  /**
   * Constructs the collector.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFieldManagerInterface $fields,
    private readonly ModuleHandlerInterface $modules,
    private readonly ConfigFactoryInterface $config,
    private readonly ModuleExtensionList $extensions,
    private readonly ConfigurationInspector $configuration,
    private readonly ModuleInventory $moduleInventory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function collect(AccountInterface $account): array {
    if (!$account->hasPermission('access ai site advisor')) {
      throw new AccessDeniedHttpException();
    }
    $settings = $this->config->get('ai_site_advisor.settings');
    $included = $settings->get('included_bundles') ?? [];
    $types = $this->entities->getStorage('node_type')->loadMultiple();
    ksort($types);
    $bundles = [];
    foreach ($types as $id => $type) {
      if ($included && !in_array($id, $included, TRUE)) {
        continue;
      }
      $definitions = $this->fields->getFieldDefinitions('node', $id);
      ksort($definitions);
      $fields = [];
      foreach ($definitions as $name => $field) {
        if ($name !== 'title' && !$field instanceof FieldConfigInterface) {
          continue;
        }
        $fields[$name] = [
          'label' => (string) $field->getLabel(),
          'description' => strip_tags((string) $field->getDescription()),
          'type' => $field->getType(),
          'required' => $field->isRequired(),
          'cardinality' => $field->getFieldStorageDefinition()->getCardinality(),
        ];
        if ($field->getType() === 'entity_reference') {
          $fields[$name]['target_type'] = $field->getSetting('target_type');
          $fields[$name]['target_bundles'] = $field->getSetting('handler_settings')['target_bundles'] ?? [];
        }
      }
      $bundles[$id] = [
        'id' => $id,
        'label' => (string) $type->label(),
        'description' => strip_tags($type->getDescription()),
        'fields' => $fields,
        'source' => 'node.type.' . $id,
      ];
    }
    if (count($bundles) > 24) {
      throw new \LengthException('Select up to 24 content types in AI Site Advisor settings before assessing this site.');
    }
    $features = [];
    foreach (['node', 'views', 'canvas', 'content_moderation', 'workflows', 'media', 'webform'] as $module) {
      $features[$module] = $this->modules->moduleExists($module);
    }
    $enabled_modules = [];
    foreach ($this->modules->getModuleList() as $id => $extension) {
      $info = $this->extensions->getExtensionInfo($id);
      $enabled_modules[$id] = [
        'label' => (string) ($info['name'] ?? $id),
        'description' => mb_substr(strip_tags((string) ($info['description'] ?? '')), 0, 240),
      ];
    }
    ksort($enabled_modules);
    $supporting = [];
    foreach (['view', 'workflow', 'content_template'] as $entity_type) {
      $supporting[$entity_type] = [];
      if (!$this->entities->hasDefinition($entity_type)) {
        continue;
      }
      foreach ($this->entities->getStorage($entity_type)->loadMultiple() as $id => $entity) {
        // List identities, not executable configuration or content values.
        $supporting[$entity_type][$id] = (string) $entity->label();
      }
      ksort($supporting[$entity_type]);
    }
    $workflows = [];
    if ($this->entities->hasDefinition('workflow')) {
      foreach ($this->entities->getStorage('workflow')->loadMultiple() as $id => $workflow) {
        if (!$workflow->status() || $workflow->get('type') !== 'content_moderation') {
          continue;
        }
        $workflow_settings = $workflow->get('type_settings');
        $states = [];
        foreach ($workflow_settings['states'] ?? [] as $state_id => $state) {
          $states[$state_id] = [
            'label' => (string) ($state['label'] ?? $state_id),
            'published' => (bool) ($state['published'] ?? FALSE),
          ];
        }
        $transitions = [];
        foreach ($workflow_settings['transitions'] ?? [] as $transition_id => $transition) {
          $transitions[$transition_id] = [
            'label' => (string) ($transition['label'] ?? $transition_id),
            'from' => $transition['from'] ?? [],
            'to' => $transition['to'] ?? '',
          ];
        }
        $workflows[$id] = [
          'label' => (string) $workflow->label(),
          'states' => $states,
          'transitions' => $transitions,
          'node_bundles' => $workflow_settings['entity_types']['node'] ?? [],
          'source' => 'workflows.workflow.' . $id,
          'scope' => 'Active state, transition and bundle configuration. Role permissions, notifications and automation behavior are not inspected.',
        ];
      }
    }
    $areas = $this->configuration->collect($account, $included);
    $snapshot = [
      'schema_version' => '4',
      'drupal_version' => \Drupal::VERSION,
      'scope' => $included ? 'Selected content types only' : 'All node content types',
      'bundles' => $bundles,
      'enabled_features' => $features,
      'enabled_modules' => $enabled_modules,
      'available_modules' => $this->moduleInventory->collect($account, $areas),
      'supporting_configuration' => $supporting,
      'workflows' => $workflows,
      'configuration_areas' => $areas,
      'site_policy' => (string) $settings->get('site_policy'),
      'limitations' => [
        'Views and templates are listed by identity only. Moderation workflow structure is inspected, but role access and automation behavior are not verified.',
        'No content values, credentials or arbitrary configuration are collected.',
        'Node content models receive full suitability judgments. Other bundle types supply field metadata and configuration destinations; their runtime behavior is not verified.',
        'Enabled module descriptions indicate available code, not verified configuration, entity access or working integrations.',
      ],
    ];
    // Stable digest changes with evidence, not the clock. Do not cache advice.
    $snapshot['fingerprint'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    return $snapshot;
  }

}
