<?php

declare(strict_types=1);

namespace Drupal\ai_site_advisor\Assessment;

/**
 * A small build handoff; full inference evidence belongs in diagnostics.
 */
final class AgentPlan {

  /**
   * Retains every work area and a bounded set of independently useful options.
   */
  public static function compact(array $assessment): array {
    $areas = $candidates = [];
    foreach ($assessment['plan']['areas'] as $id => $area) {
      $options = array_column($area['options'], NULL, 'id');
      $primary = $options[$area['selection']['id']] ?? NULL;
      $chosen = self::shortlist($options, $primary);
      $consider = [];
      foreach ($chosen as $option) {
        $candidates[$option['id']] ??= self::candidate($option);
        $consider[] = [
          'candidate' => $option['id'],
          'role' => $option['contribution']['choice'] ?? 'unknown',
          'needs_review' => ($option['contribution']['needs_review'] ?? TRUE) || ($option['selected'] && $area['selection']['needs_review']),
        ];
      }
      $starting = ['kind' => 'undecided', 'needs_review' => TRUE];
      $existing = [];
      if (isset($primary['package'])) {
        $starting = ['candidate' => $primary['id'], 'needs_review' => $area['selection']['needs_review']];
        foreach ($area['handoff']['resources'] as $resource) {
          if ($resource['option_id'] === $primary['id']) {
            foreach ($resource['existing_models'] as $name => $record) {
              $existing[] = ['config' => $name, 'label' => $record['label'], 'links' => $record['links']];
            }
          }
        }
      }
      elseif (isset($primary['bundle_id'])) {
        $starting = [
          'kind' => 'existing_content_type',
          'id' => $primary['bundle_id'],
          'label' => $primary['label'],
          'needs_review' => $area['selection']['needs_review'],
        ];
        $record = $assessment['site']['configuration_areas']['node_type']['records'][$primary['bundle_id']] ?? NULL;
        if ($record) {
          $existing[] = ['config' => $record['config_name'], 'label' => $record['label'], 'links' => $record['links']];
        }
      }
      elseif (($primary['id'] ?? '') === 'configure') {
        $starting['kind'] = 'configuration_to_design';
      }
      $destination = $area['handoff']['configuration_area'];
      $areas[] = [
        'id' => $id,
        'label' => $area['label'],
        'status' => $area['status'],
        'starting_point' => $starting,
        'configure' => $destination ? [
          'area' => $destination['label'],
          'links' => $destination['links'],
          'needs_review' => $area['handoff']['configuration_needs_review'],
        ] : NULL,
        'existing_configuration' => $existing,
        'consider' => $consider,
        'resolve_before_building' => $area['check'],
        'check_needs_review' => $area['check_needs_review'] ?? TRUE,
        'other_package_options' => count(array_filter($options, static fn ($option) => isset($option['package']))) - count($chosen),
      ];
    }
    return [
      'format' => 'compact',
      'schema_version' => 'agent-plan-v1',
      'status' => 'draft',
      'needs_review' => $assessment['status'] === 'needs_clarification',
      'site_fingerprint' => $assessment['site']['fingerprint'] ?? NULL,
      'handoff' => [
        'Use the original brief with these work areas. Choose the starting points and supporting parts; alternatives are not an install-all list.',
        'For a selected external package, resolve a release compatible with the site and use Composer before enabling modules or applying recipes. Run commands in the project environment. Acquisition instructions are conditional, not authorization to change the site.',
        'Inspect current fields, dependencies, access and integration at the supplied locations. Existing configuration names do not prove identical recipe settings or fulfilled requirements. Implement the remaining changes and validate the chosen combination.',
      ],
      'work_areas' => $areas,
      'candidates' => $candidates,
      'discovery' => [
        'searched_ecosystem' => $assessment['discovery']['searched_ecosystem'] ?? FALSE,
        'truncated' => $assessment['discovery']['truncated'] ?? FALSE,
        'warnings' => $assessment['discovery']['warnings'] ?? [],
        'coverage' => $assessment['search_plan']['coverage'] ?? NULL,
      ],
      'details' => 'Repeat this tool with the same brief and detail="full" for all candidates, fields, scores and diagnostics. That performs a fresh assessment; results can change.',
    ];
  }

  /**
   * Preserve the preference and up to two candidates per useful role.
   */
  private static function shortlist(array $options, ?array $primary): array {
    $chosen = isset($primary['package']) ? [$primary['id'] => $primary] : [];
    foreach (['foundation', 'complement'] as $role) {
      $matching = array_filter($options, static fn ($option) => isset($option['package']) && ($option['contribution']['choice'] ?? '') === $role);
      uasort($matching, static fn ($a, $b) => ($a['contribution']['needs_review'] <=> $b['contribution']['needs_review'])
        ?: ($b['contribution']['probabilities'][$role] <=> $a['contribution']['probabilities'][$role]));
      $chosen += array_slice($matching, 0, 2, TRUE);
    }
    return $chosen;
  }

  /**
   * A candidate is named once, with pointers and conditional acquisition steps.
   */
  public static function candidate(array $option): array {
    $package = $option['package'];
    $availability = $option['availability'] ?? 'unknown';
    $recipe = ($option['kind'] ?? '') === 'recipe';
    // Catalog strings must never become arbitrary shell instructions. Return
    // argv only for a valid Composer package identity, without shell parsing.
    $valid_package = preg_match('~^[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?/[a-z0-9](?:[a-z0-9_.-]*[a-z0-9])?$~D', $package) === 1;
    $acquire = match (TRUE) {
      in_array($availability, ['local_code', 'enabled_module'], TRUE) => ['action' => 'code_available'],
      $availability === 'catalog_only' && $valid_package => [
        'action' => 'composer_require_if_selected',
        'argv' => ['composer', 'require', $package],
      ],
      default => ['action' => 'inspect_acquisition'],
    };
    $candidate = [
      'label' => $option['label'],
      'kind' => $option['kind'] ?? 'candidate',
      'package' => $package,
      'availability' => $availability,
      'inspect' => $option['url'] ?? NULL,
      'if_selected' => [
        'acquire' => $acquire,
        'then' => $recipe
          ? 'Inspect recipe configuration and dependencies; apply compatible changes or configure manually. Local files do not prove the recipe was applied.'
          : ($availability === 'enabled_module' ? 'Inspect and configure the enabled module.' : 'Enable the required module/submodules after checking their dependencies, then configure and integrate.'),
      ],
    ];
    if ($recipe && $availability === 'local_code') {
      $candidate['manifest_reference'] = $option['source'] ?? NULL;
      $configuration = $option['configuration'] ?? [];
      if ($configuration) {
        $candidate['explicit_configuration'] = [
          'existing' => count(array_filter($configuration, static fn ($item) => $item['active_exists'] === TRUE)),
          'missing' => count(array_filter($configuration, static fn ($item) => $item['active_exists'] === FALSE)),
          'unresolved' => count(array_filter($configuration, static fn ($item) => $item['active_exists'] === NULL)),
        ];
      }
    }
    return $candidate;
  }

  /**
   * Discovery also provides pointers instead of complete catalog descriptions.
   */
  public static function discovery(array $discovery): array {
    $items = [];
    foreach ($discovery['items'] as $id => $item) {
      $items[$id] = self::candidate($item);
    }
    return [
      'format' => 'compact',
      'query' => $discovery['query'],
      'items' => $items,
      'truncated' => $discovery['truncated'],
      'warnings' => $discovery['warnings'],
      'scope' => 'Catalog matches, not recommendations. Inspect project details before choosing. Composer steps apply only to selected external packages; check compatible versions first.',
      'details' => 'Repeat with the same query and detail="full" for descriptions and source reports. Discovery makes no model call.',
    ];
  }

}
