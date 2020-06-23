<?php

namespace Drupal\node_routes_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a testing block for node routes.
 *
 * @Block(
 *  id = "node_routes_test_block",
 *  admin_label = @Translation("Node routes test block")
 * )
 */
class NodeRoutesTestBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Creates a NodeRoutesTestBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $cacheable_metadata = new CacheableMetadata();
    $is_article_page = FALSE;
    if ($node = $this->routeMatch->getParameter('node')) {
      $is_article_page = $node->getType() == 'article';
      $cacheable_metadata->merge($node);
    }
    $build = [
      '#markup' => $is_article_page ? 'article page' : 'not an article page',
    ];
    $cacheable_metadata->applyTo($build);
    return $build;
  }

}
