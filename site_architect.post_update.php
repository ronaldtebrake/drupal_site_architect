<?php

/**
 * @file
 * Updates configuration owned by Site Architect.
 */

/**
 * Add the planning scope when upgrading a site that already has Simple OAuth.
 */
function site_architect_post_update_planning_oauth_scope(): void {
  \Drupal::service('site_architect.planning_scope_installer')->ensureScope();
}

/**
 * Repair installations where a later OAuth recipe skipped optional config.
 */
function site_architect_post_update_recipe_planning_scope(): void {
  \Drupal::service('site_architect.planning_scope_installer')->ensureScope();
}
