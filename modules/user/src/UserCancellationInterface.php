<?php

namespace Drupal\user;

/**
 * Defines the user cancellation service.
 */
interface UserCancellationInterface {

  /**
   * Blocks the user from logging in.
   */
  const USER_CANCEL_METHOD_BLOCK = 'user_cancel_block';

  /**
   * Blocks the user and unpublishes all content it owns.
   */
  const USER_CANCEL_METHOD_BLOCK_AND_UNPUBLISH = 'user_cancel_block_unpublish';

  /**
   * Deletes user, all content is attributed to anonymous.
   */
  const USER_CANCEL_METHOD_REASSIGN_ANONYMOUS = 'user_cancel_reassign';

  /**
   * Deletes user and all content it owns.
   */
  const USER_CANCEL_METHOD_DELETE = 'user_cancel_delete';

  /**
   * Cancel a user.
   *
   * This method may run for a while, designed for headless execution or where
   * timeouts are guaranteed not to occur.
   *
   * If user cancellation is triggered with via Form API,
   * progressiveUserCancellation should be used instead.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of additional options.
   */
  public function cancelUser(UserInterface $user, $method, array $options);

  /**
   * Cancels a user with progressive Batch API.
   *
   * User cancellation for browser execution, designed to be called with forms.
   *
   * Executes user cancellation with Batch API where cancellation is executed
   * over multiple requests.
   *
   * In contrast to ::cancelUser, this method will emit messages.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of additional options. These values must be serializable.
   */
  public function progressiveUserCancellation(UserInterface $user, $method, array $options = []);

}
