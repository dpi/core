<?php

namespace Drupal\user;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Psr\Log\LoggerInterface;

/**
 * Defines the user cancellation service.
 */
class UserCancellation implements UserCancellationInterface {

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
   * Constructs a new UserCancellation.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel for user.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler, AccountProxyInterface $currentUser, LoggerInterface $logger) {
    $this->moduleHandler = $moduleHandler;
    $this->currentUser = $currentUser;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelUser(UserInterface $user, $method, array $options) {
    $this->cancelUserHooks($user, $method, $options);

    switch ($method) {
      case static::USER_CANCEL_METHOD_BLOCK:
      case static::USER_CANCEL_METHOD_BLOCK_AND_UNPUBLISH:
      default:
        $this->blockUser($user, !empty($edit['user_cancel_notify']));
        break;

      case static::USER_CANCEL_METHOD_REASSIGN_ANONYMOUS:
      case static::USER_CANCEL_METHOD_DELETE:
        $this->deleteUser($user, !empty($edit['user_cancel_notify']));
        break;
    }

    // After cancelling account, ensure that user is logged out. We can't
    // destroy their session though, as we might have information in it, and we
    // can't regenerate it because batch API uses the session ID.
    if ($user->id() == $this->currentUser->id()) {
      $this->currentUser->setAccount(new AnonymousUserSession());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cancelUserHooks(UserInterface $user, $method, array $options = []) {
    // When the delete method is used, entity delete hooks are invoked for the
    // entity. Modules should use those hooks to respond to user deletion.
    if ($method !== static::USER_CANCEL_METHOD_DELETE) {
      $this->moduleHandler->invokeAll('user_cancel', [$options, $user, $method]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockUser(UserInterface $user, $notify = FALSE) {
    // Send account blocked notification if option was checked.
    if ($notify) {
      $this->mail($user, 'status_blocked');
    }
    $user->block();
    $user->save();
    $this->logger->notice('Blocked user: %name %email.', ['%name' => $user->getAccountName(), '%email' => '<' . $user->getEmail() . '>']);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteUser(UserInterface $user, $notify = FALSE) {
    // Send account canceled notification if option was checked.
    if ($notify) {
      $this->mail($user, 'status_canceled');
    }
    $user->delete();
    $this->logger->notice('Deleted user: %name %email.', ['%name' => $user->getAccountName(), '%email' => '<' . $user->getEmail() . '>']);
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

}
