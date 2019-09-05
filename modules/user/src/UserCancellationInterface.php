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
   * Since the user cancellation process needs to be run in a batch, either
   * Form API will invoke it, or batch_process() needs to be invoked after
   * calling this function and should define the path to redirect to.
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
   * Dispatches user cancellation hooks.
   *
   * @param \Drupal\user\UserInterface $user
   *   The cancelled user.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of additional options.
   */
  public function cancelUserHooks(UserInterface $user, $method, array $options = []);

  /**
   * Block a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to block.
   * @param bool $notify
   *   Whether to notify the user it was blocked.
   *
   * @internal This method should only be called by Drupal. It is public because
   *   batch cancellation requires access to it.
   */
  public function blockUser(UserInterface $user, $notify = FALSE);

  /**
   * Delete a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to delete.
   * @param bool $notify
   *   Whether to notify the user it was deleted.
   *
   * @internal This method should only be called by Drupal. It is public because
   *   batch cancellation requires access to it.
   */
  public function deleteUser(UserInterface $user, $notify = FALSE);

}
