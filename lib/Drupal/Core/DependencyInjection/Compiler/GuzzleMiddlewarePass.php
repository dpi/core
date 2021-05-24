<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds services tagged with HTTP middleware to HTTP handler stack configurator.
 *
 * @internal
 *
 * @deprecated in drupal:9.3.0 and is removed from drupal:10.0.0. Services
 * tagged with 'http_client_middleware' are now added to
 * 'http_handler_stack_configurator' via
 * \Drupal\Core\DependencyInjection\Compiler\TaggedHandlersPass
 *
 * @see https://www.drupal.org/project/drupal/issues/3215397
 */
class GuzzleMiddlewarePass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container) {
    // No-op.
  }

}
