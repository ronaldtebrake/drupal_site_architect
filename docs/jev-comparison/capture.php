<?php

/**
 * @file
 * Development-only, read-only comparison runner. Never served as a route.
 *
 * See README.md for the Drush command and required output directory setting.
 *
 * Uses the configured Decision provider; normal inference charges apply.
 * Raw evidence is written only to the explicitly supplied output directory.
 * Review it before sharing. Credentials and provider settings are not exported.
 */

use Drupal\site_architect\Assessment\AgentPlan;

if (PHP_SAPI !== 'cli' || !class_exists('Drupal')) {
  exit;
}

$output = getenv('ARCHITECT_COMPARISON_OUTPUT');
if (!$output || !is_dir($output) || !is_writable($output)) {
  throw new RuntimeException('Set ARCHITECT_COMPARISON_OUTPUT to an existing writable directory outside the web root.');
}
$web_root = realpath(\Drupal::root());
if (str_starts_with(realpath($output) . DIRECTORY_SEPARATOR, $web_root . DIRECTORY_SEPARATOR)) {
  throw new RuntimeException('Raw captures must be outside the web root.');
}
$account = \Drupal::entityTypeManager()->getStorage('user')->load(1);
if (!$account || !$account->hasPermission('access site architect')) {
  throw new RuntimeException('The development comparison requires an authorized site architect account.');
}
$cases = json_decode(file_get_contents(__DIR__ . '/cases.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$only = getenv('ARCHITECT_COMPARISON_CASE');
foreach ($cases as $case) {
  if ($only && $case['id'] !== $only) {
    continue;
  }
  $path = $output . '/' . $case['id'] . '.json';
  if (is_file($path)) {
    print $case['id'] . ": retained existing capture\n";
    continue;
  }
  print $case['id'] . ": collecting shared evidence\n";
  $site = \Drupal::service('site_architect.context')->collect($account);
  // Declared search terms hold discovery constant. No model chooses them.
  $discovery = \Drupal::service('site_architect.candidates')->discover($case['query'], $account);
  print $case['id'] . ": running configured Jev assessment\n";
  $assessment = \Drupal::service('site_architect.architect')->assess($case['brief'], $account, $case['query']);
  if ($assessment['site']['fingerprint'] !== $site['fingerprint']) {
    throw new RuntimeException('Site evidence changed during capture; do not compare different snapshots.');
  }
  // Compare evidence, excluding upstream fetch timestamps/cache diagnostics.
  if ($discovery['items'] !== $assessment['discovery']['items']) {
    throw new RuntimeException('Catalog candidates changed during capture; retry with stable evidence.');
  }
  $record = [
    'case' => $case,
    'captured_at' => gmdate(DATE_ATOM),
    'method' => 'Fixed keyword discovery and the same site snapshot. BM25 replay uses all local modules and returned catalog candidates. Jev uses production semantic screening, assessment and requirement planning; automatic search-term planning is bypassed.',
    'shared_site' => $site,
    'shared_discovery' => $discovery,
    'assessment' => $assessment,
    'compact' => AgentPlan::compact($assessment),
  ];
  file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
  chmod($path, 0600);
  print json_encode([
    'case' => $case['id'],
    'model' => $assessment['model'],
    'status' => $assessment['status'],
    'elapsed_ms' => $assessment['elapsed_ms'],
    'source_sha256' => hash_file('sha256', $path),
  ]) . "\n";
}
