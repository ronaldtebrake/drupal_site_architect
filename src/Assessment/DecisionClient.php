<?php

declare(strict_types=1);

namespace Drupal\site_architect\Assessment;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai_decision\OperationType\Decision\DecisionInput;
use Drupal\ai_decision\OperationType\Decision\DecisionResponse;

/**
 * Uses Drupal AI's provider proxy, preserving its logging and integrations.
 */
final class DecisionClient implements DecisionClientInterface {

  /**
   * Constructs the client.
   */
  public function __construct(private readonly AiProviderPluginManager $providers) {}

  /**
   * {@inheritdoc}
   */
  public function decide(DecisionInput $input): DecisionResponse {
    $default = $this->providers->getDefaultProviderForOperationType('decision');
    if (empty($default['provider_id']) || empty($default['model_id'])) {
      throw new \RuntimeException('Configure a default Decision provider and model in Drupal AI first.');
    }
    try {
      $provider = $this->providers->createInstance($default['provider_id']);
      return $provider->decision($input, $default['model_id'], ['site_architect'])->getNormalized();
    }
    catch (\Throwable) {
      // Transport adapters may log exception messages. Do not let raw provider
      // responses or credentials escape through Tool API or MCP Server.
      throw new \RuntimeException('The configured Decision provider could not complete the assessment. Check its configuration and retry.');
    }
  }

}
