<?php

namespace Drupal\user;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Defines the user cancellation service.
 */
class UserCancellation implements UserCancellationInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The logger channel for user.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
   * Constructs a new User Cancellation service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, AccountProxyInterface $currentUser, LoggerInterface $logger, MessengerInterface $messenger, Session $session, EntityTypeManagerInterface $entityTypeManager) {
    $this->moduleHandler = $moduleHandler;
    $this->currentUser = $currentUser;
    $this->logger = $logger;
    $this->messenger = $messenger;
    $this->session = $session;
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * {@inheritdoc}
   */
  public function cancelUser(UserInterface $user, $method, array $options = []) {
    $this->createBatches($user, $method, TRUE, $options);
    // Run batches.
    $batch =& $this->getBatch();
    $batch['progressive'] = FALSE;
    $this->processBatch();
  }

  /**
   * {@inheritdoc}
   */
  public function progressiveUserCancellation(UserInterface $user, $method, array $options = []) {
    $this->createBatches($user, $method, FALSE, $options);
  }

  /**
   * Create and set cancellation batches for a user.
   *
   * Some hook_user_cancel() implementations may immediately take action, rather
   * than adding batches to be executed later.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param bool $silent
   *   Whether messages should be emitted.
   * @param array $options
   *   An array of additional options. These values must be serializable.
   */
  protected function createBatches(UserInterface $user, $method, $silent, array $options) {
    // Initialize batch (to set title).
    $batch = (new BatchBuilder())->setTitle($this->t('Cancelling account'));
    $this->setBatch($batch->toArray());

    $this->invokeHooks($user, $method, $options);

    // Actually cancel the account.
    $batch = (new BatchBuilder())
      ->setTitle($this->t('Cancelling user account'))
      ->addOperation(
        [static::class, 'callbackCancelUser'],
        [(int) $user->id(), $method, $silent, $options]
      );

    // After cancelling account, ensure that user is logged out.
    if ($user->id() == $this->currentUser->id()) {
      // Batch API stores data in the session, so use the finished operation to
      // manipulate the current user's session id.
      $batch->setFinishCallback([static::class, 'callbackFinish']);
    }

    $this->setBatch($batch->toArray());
  }

  /**
   * Dispatches user cancellation hooks.
   *
   * Implementors are encouraged to add additional batch sets rather than
   * executing cancellation related logic immediately.
   *
   * @param \Drupal\user\UserInterface $user
   *   The cancelled user.
   * @param string $method
   *   The account cancellation method.
   * @param array $options
   *   An array of additional options.
   */
  protected function invokeHooks(UserInterface $user, $method, array $options) {
    // When the delete method is used, entity delete hooks are invoked for the
    // entity. Modules should use those hooks to respond to user deletion.
    if ($method !== static::USER_CANCEL_METHOD_DELETE) {
      $this->moduleHandler->invokeAll('user_cancel', [$options, $user, $method]);
    }
  }

  /**
   * Implements callback_batch_operation().
   *
   * @param int $userId
   *   The ID of the user account to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param bool $silent
   *   Whether messages should be emitted.
   * @param array $options
   *   An array of additional options.
   *
   * @internal method is specific to this implementation and may be modified or
   *   removed at any time.
   *
   * @see ::createBatches()
   */
  public static function callbackCancelUser(int $userId, $method, $silent, array $options = []) {
    /** @var static $userCancellation */
    $userCancellation = \Drupal::service('user.cancellation');
    $userCancellation->doCancelUser($userId, $method, $silent, $options);
  }

  /**
   * Blocks or deletes a user.
   *
   * @param int $userId
   *   The ID of the user account to cancel.
   * @param string $method
   *   The account cancellation method.
   * @param bool $silent
   *   Whether messages should be emitted.
   * @param array $options
   *   An array of submitted form values.
   */
  protected function doCancelUser(int $userId, $method, $silent, array $options = []) {
    // In case the user was hard-deleted since this queue item was created.
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->userStorage->load($userId);
    if (!$user) {
      return;
    }

    switch ($method) {
      case UserCancellationInterface::USER_CANCEL_METHOD_BLOCK:
      case UserCancellationInterface::USER_CANCEL_METHOD_BLOCK_AND_UNPUBLISH:
      default:
        $this->blockUser($user, !empty($options['user_cancel_notify']));
        if (!$silent) {
          $this->messenger->addStatus($this->t('%name has been disabled.', ['%name' => $user->getDisplayName()]));
        }
        break;

      case UserCancellationInterface::USER_CANCEL_METHOD_REASSIGN_ANONYMOUS:
      case UserCancellationInterface::USER_CANCEL_METHOD_DELETE:
        $this->deleteUser($user, !empty($options['user_cancel_notify']));
        if (!$silent) {
          $this->messenger->addStatus($this->t('%name has been deleted.', ['%name' => $user->getDisplayName()]));
        }
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
   * Block a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to block.
   * @param bool $notify
   *   Whether to notify the user it was blocked.
   */
  protected function blockUser(UserInterface $user, $notify = FALSE) {
    // Send account blocked notification if option was checked.
    if ($notify) {
      $this->mail($user, 'status_blocked');
    }
    $user->block();
    $user->save();
    $this->logger->notice('Blocked user: %name %email.', ['%name' => $user->getAccountName(), '%email' => '<' . $user->getEmail() . '>']);
  }

  /**
   * Delete a user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to delete.
   * @param bool $notify
   *   Whether to notify the user it was deleted.
   */
  protected function deleteUser(UserInterface $user, $notify = FALSE) {
    // Send account canceled notification if option was checked.
    if ($notify) {
      $this->mail($user, 'status_canceled');
    }
    $user->delete();
    $this->logger->notice('Deleted user: %name %email.', ['%name' => $user->getAccountName(), '%email' => '<' . $user->getEmail() . '>']);
  }

  /**
   * Implements callback_batch_finished().
   *
   * @internal method is specific to this implementation and may be modified or
   *   removed at any time.
   *
   * @see ::createBatches()
   */
  public static function callbackFinish() {
    /** @var static $userCancellation */
    $userCancellation = \Drupal::service('user.cancellation');
    $userCancellation->doFinish();
  }

  /**
   * Finishes batch processing callback for cancelling a user account.
   */
  protected function doFinish() {
    // Regenerate the users session instead of calling session_destroy() as we
    // want to preserve any messages that might have been set.
    $this->session->migrate();
  }

  /**
   * Proxy to user mailer.
   *
   * @param \Drupal\user\UserInterface $user
   *   The cancelled user.
   * @param string $operation
   *   The operation being performed on the account.
   *
   * @return array
   *   An array containing various information about the message.
   *   See \Drupal\Core\Mail\MailManagerInterface::mail() for details.
   */
  protected function mail(UserInterface $user, $operation) {
    return \_user_mail_notify($operation, $user);
  }

  /**
   * Proxy to batch getter.
   */
  protected function &getBatch() {
    return \batch_get();
  }

  /**
   * Proxy to batch setter.
   */
  protected function setBatch(array $batchDefinition) {
    \batch_set($batchDefinition);
  }

  /**
   * Proxy to batch processor.
   */
  protected function processBatch() {
    return \batch_process();
  }

}
