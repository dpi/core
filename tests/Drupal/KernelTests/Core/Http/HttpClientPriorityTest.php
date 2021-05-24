<?php

namespace Drupal\KernelTests\Core\Http;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * Tests middlewares are executed in tag priority order.
 *
 * @group Http
 */
class HttpClientPriorityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['http_test', 'http_history'];

  /**
   * xxxx
   */
  public function testXyz(): void {
    $httpClient = \Drupal::httpClient();

    $response = $httpClient->get('http://localhost/', ['debug' => true]);
    $this->assertEquals([], $response->getHeaders());

    /** @var \Drupal\http_history\HttpHistoryState $httpHistory */
    $httpHistory = \Drupal::service('http_history.state');

    // test definition order.
    // test execution order
  }

//  /**
//   * {@inheritdoc}
//   */
//  public function register(ContainerBuilder $container) {
//    parent::register($container);
//
////    dpi.http_middleware2:
////    class: Drupal\dpi\DpiHttpMiddleware2
////    tags:
////      - { name: http_client_middleware, priority: 100 }
//
//
//    $container->set($id, $service)->push(Middleware::history($this->historyContainer));
//
//    $httpClient = new Client(['handler' => $handlerStack]);
//    $container->set('http_client', $httpClient);
//  }


}
