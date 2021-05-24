<?php

namespace Drupal\http_history;

use Drupal\Core\State\StateInterface;
use GuzzleHttp\Middleware;

/**
 * HTTP middleware for testing.
 */
final class HttpHistoryState {

  /**
   * HTTP history.
   *
   * @var array
   */
  protected $history;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Name of state to serialize history.
   */
  const STATE_KEY = 'http_history.history';

  /**
   * HttpHistoryState constructor.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
    $this->history = new class($state) extends \ArrayObject {

      /**
       * State.
       *
       * @var \Drupal\Core\State\StateInterface
       */
      protected StateInterface $state;

      /**
       * Constructs an array to capture HTTP history to state.
       */
      public function __construct(StateInterface $state) {
        $this->state = $state;
      }

      /**
       * {@inheritdoc}
       */
      public function offsetSet($key, $value) {
        $history = $this->state->get(HttpHistoryState::STATE_KEY, []);
        // Remove closures before serialization.
        unset($value['options']['handler']);
        $history[] = $value;
        $this->state->set(HttpHistoryState::STATE_KEY, $history);
      }

    };
  }

  /**
   * {@inheritdoc}
   */
  public function __invoke(): callable {
    return Middleware::history($this->history);
  }

  /**
   * Get history from state.
   */
  public function getHistory(): array {
    return $this->state->get(HttpHistoryState::STATE_KEY, []);
  }

  /**
   * Clear history in state.
   */
  public function clearHistory(): array {
    return $this->state->delete(HttpHistoryState::STATE_KEY);
  }

}
