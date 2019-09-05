<?php

namespace Drupal\user;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Provides capability for running user cancellation in batching.
 *
 * For backwards compatibility.
 *
 * @internal
 * @deprecated
 */
class UserCancellationBatch implements ContainerInjectionInterface {

  /**
   * The user cancellation service.
   *
   * @var \Drupal\user\UserCancellationInterface
   */
  protected $userCancellation;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The user entity storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a new UserCancellationBatch.
   *
   * @param \Drupal\user\UserCancellationInterface $userCancellation
   *   The user cancellation service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   The user entity storage.
   */
  public function __construct(UserCancellationInterface $userCancellation, AccountProxyInterface $currentUser, MessengerInterface $messenger, Session $session, UserStorageInterface $userStorage) {
    $this->userCancellation = $userCancellation;
    $this->currentUser = $currentUser;
    $this->messenger = $messenger;
    $this->session = $session;
    $this->userStorage = $userStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.cancellation'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('session'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * Cancels a user with Batch API.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of additional options. These values must be serializable.
   */
  public function batchCancelUser(UserInterface $user, $method, array $options = []) {
    $this->userCancellation->cancelUserHooks($user, $method, $options);

    // Actually cancel the account.
    $batch = (new BatchBuilder())
      ->setTitle(\t('Cancelling user account'))
      ->addOperation(
        [static::class, 'callbackBatchCancelUser'],
        [(int) $user->id(), $method, $options]
      );

    // After cancelling account, ensure that user is logged out.
    if ($user->id() == $this->currentUser->id()) {
      // Batch API stores data in the session, so use the finished operation to
      // manipulate the current user's session id.
      $batch->setFinishCallback([static::class, 'callbackBatchFinish']);
    }

    batch_set($batch->toArray());
  }

  /**
   * Implements callback_batch_operation().
   *
   * Last step for cancelling a user account.
   *
   * @param int $userId
   *   The ID of the user account to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of additional options.
   *
   * @see ::batchCancelUser()
   */
  public static function callbackBatchCancelUser(int $userId, $method, array $options = []) {
    /** @var static $batchUserCancellation */
    $batchUserCancellation = \Drupal::service('class_resolver')->getInstanceFromDefinition(static::class);
    $batchUserCancellation->doBatchCancelUser($userId, $method, $options);
  }

  /**
   * Implements callback_batch_operation().
   *
   * Last step for cancelling a user account.
   *
   * @param int $userId
   *   The ID of the user account to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of submitted form values.
   *
   * @internal This method should only be called by Drupal. It is public because
   *   batch cancellation requires access to it.
   */
  public function doBatchCancelUser(int $userId, $method, array $options = []) {
    // In case the user was hard-deleted since this queue item was created.
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($userId);
    if (!$user) {
      return;
    }

    $this->userCancellation->cancelUserHooks($user, $method, $options);

    switch ($method) {
      case UserCancellationInterface::USER_CANCEL_METHOD_BLOCK:
      case UserCancellationInterface::USER_CANCEL_METHOD_BLOCK_AND_UNPUBLISH:
      default:
        $this->userCancellation->blockUser($user, !empty($options['user_cancel_notify']));
        $this->messenger->addStatus(\t('%name has been disabled.', ['%name' => $user->getDisplayName()]));
        break;

      case UserCancellationInterface::USER_CANCEL_METHOD_REASSIGN_ANONYMOUS:
      case UserCancellationInterface::USER_CANCEL_METHOD_DELETE:
        $this->userCancellation->deleteUser($user, !empty($options['user_cancel_notify']));
        $this->messenger->addStatus(\t('%name has been deleted.', ['%name' => $user->getDisplayName()]));
        break;
    }

    // After cancelling account, ensure that user is logged out. We can't
    // destroy their session though, as we might have information in it, and we
    // can't regenerate it because batch API uses the session ID, we will
    // regenerate it in ::cancelUserBatchFinish().
    if ($user->id() == $this->currentUser->id()) {
      $this->currentUser->setAccount(new AnonymousUserSession());
    }
  }

  /**
   * Implements callback_batch_finished().
   *
   * Finished batch processing callback for cancelling a user account.
   *
   * @see ::batchCancelUser()
   */
  public static function callbackBatchFinish() {
    /** @var static $batchUserCancellation */
    $batchUserCancellation = \Drupal::service('class_resolver')->getInstanceFromDefinition(static::class);
    $batchUserCancellation->doCallbackBatchFinish();
  }

  /**
   * Finished batch processing callback for cancelling a user account.
   */
  public function doCallbackBatchFinish() {
    // Regenerate the users session instead of calling session_destroy() as we
    // want to preserve any messages that might have been set.
    $this->session->migrate();
  }

}
