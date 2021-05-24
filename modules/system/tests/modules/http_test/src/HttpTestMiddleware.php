<?php

namespace Drupal\http_test;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * HTTP middleware for testing.
 */
final class HttpTestMiddleware {

  /**
   * An arbitrary message for testing.
   *
   * @var string
   */
  protected string $message;

  /**
   * HttpTestMiddleware constructor.
   */
  public function __construct(string $message) {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke(): callable {
    return function ($nextHandler): callable {
      return function (RequestInterface $request, array $options) use ($nextHandler): PromiseInterface {
        $options['test execution order'] = $this->message;
        return $nextHandler($request, $options);
      };
    };
  }

}
