<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Context;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Discovers configuration destinations and safe model metadata from Drupal.
 */
final class ConfigurationInspector {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly EntityFieldManagerInterface $fields,
    private readonly ModuleExtensionList $extensions,
  ) {}

  /**
   * Lists registered configuration areas without reading arbitrary settings.
   */
  public function collect(AccountInterface $account, array $included_bundles = []): array {
    $areas = [];
    foreach ($this->entities->getDefinitions() as $id => $definition) {
      if (!$definition instanceof ConfigEntityTypeInterface || !$definition->hasLinkTemplate('collection')) {
        continue;
      }
      $provider = $definition->getProvider();
      $info = isset($this->extensions->getList()[$provider]) ? $this->extensions->getExtensionInfo($provider) : [];
      $area = [
        'id' => $id,
        'label' => (string) $definition->getCollectionLabel(),
        'item_label' => (string) $definition->getLabel(),
        'provider' => $provider,
        'description' => strip_tags((string) ($info['description'] ?? '')),
        'config_prefix' => $definition->getConfigPrefix(),
        'links' => $this->link(Url::fromRoute('entity.' . $id . '.collection'), $account, 'Open ' . $definition->getCollectionLabel()),
        'records' => [],
      ];
      // Bundle definitions describe structure, not content values. Other config
      // types are destinations only, not arbitrary configuration dumps.
      if ($entity_type = $definition->getBundleOf()) {
        $area['entity_type'] = $entity_type;
        foreach ($this->entities->getStorage($id)->loadMultiple() as $bundle_id => $bundle) {
          if ($entity_type === 'node' && $included_bundles && !in_array($bundle_id, $included_bundles, TRUE)) {
            continue;
          }
          $links = [];
          if ($bundle->hasLinkTemplate('edit-form')) {
            $links = $this->link($bundle->toUrl('edit-form'), $account, 'Edit ' . $bundle->label());
          }
          foreach ([
            'entity.' . $entity_type . '.field_ui_fields' => 'Manage fields',
            'entity.entity_form_display.' . $entity_type . '.default' => 'Manage form display',
            'entity.entity_view_display.' . $entity_type . '.default' => 'Manage display',
          ] as $route => $label) {
            $links = array_merge($links, $this->link(Url::fromRoute($route, [$id => $bundle_id]), $account, $label));
          }
          $fields = [];
          foreach ($this->fields->getFieldDefinitions($entity_type, $bundle_id) as $name => $field) {
            if (!$field instanceof FieldConfigInterface) {
              continue;
            }
            $fields[$name] = [
              'label' => (string) $field->getLabel(),
              'type' => $field->getType(),
              'required' => $field->isRequired(),
            ];
            if ($field->getType() === 'entity_reference') {
              $fields[$name]['target_type'] = $field->getSetting('target_type');
              $fields[$name]['target_bundles'] = $field->getSetting('handler_settings')['target_bundles'] ?? [];
            }
          }
          ksort($fields);
          $area['records'][$bundle_id] = [
            'label' => (string) $bundle->label(),
            'config_name' => $definition->getConfigPrefix() . '.' . $bundle_id,
            'fields' => $fields,
            'links' => $links,
          ];
        }
        ksort($area['records']);
      }
      $areas[$id] = $area;
    }
    ksort($areas);
    return $areas;
  }

  /**
   * Only expose destinations which exist and this caller can access.
   */
  private function link(Url $url, AccountInterface $account, string $label): array {
    try {
      return $url->access($account) ? [['label' => $label, 'url' => $url->toString()]] : [];
    }
    catch (\Exception) {
      // Optional UI modules and parameterized collections may have no usable
      // route on this site. Never invent a path or promise an accessible UI.
      return [];
    }
  }

}
