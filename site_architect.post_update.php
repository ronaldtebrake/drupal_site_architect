<?php

/**
 * @file
 * Updates configuration owned by Site Architect.
 */

use Drupal\Core\Config\FileStorage;

/**
 * Add the planning scope when upgrading a site that already has Simple OAuth.
 */
function site_architect_post_update_planning_oauth_scope(): void {
  if (!\Drupal::moduleHandler()->moduleExists('simple_oauth')) {
    // Enabling OAuth later installs this module's optional configuration.
    return;
  }
  $storage = \Drupal::entityTypeManager()->getStorage('oauth2_scope');
  if ($storage->load('drupal_site_architect_plan')) {
    // Preserve administrator changes, including a deliberately disabled scope.
    return;
  }
  $source = new FileStorage(__DIR__ . '/config/optional');
  $data = $source->read('simple_oauth.oauth2_scope.drupal_site_architect_plan');
  if ($data === FALSE) {
    throw new \RuntimeException('The Site Architect planning scope definition is missing.');
  }
  $storage->create($data)->save();
}
