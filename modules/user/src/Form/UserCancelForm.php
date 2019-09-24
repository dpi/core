<?php

namespace Drupal\user\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserCancellationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form for cancelling user account.
 *
 * @internal
 */
class UserCancelForm extends ContentEntityConfirmFormBase {

  /**
   * Available account cancellation methods.
   *
   * @var array
   */
  protected $cancelMethods;

  /**
   * Whether allowed to select cancellation method.
   *
   * @var bool
   */
  protected $selectCancel;

  /**
   * The user being cancelled.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $entity;

  /**
   * The user cancellation service.
   *
   * @var \Drupal\user\UserCancellationInterface
   */
  protected $userCancellation;

  /**
   * Constructs a new UserCancelForm.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\user\UserCancellationInterface|null $userCancellation
   *   The user cancellation service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, UserCancellationInterface $userCancellation = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->userCancellation = $userCancellation ?: \Drupal::service('user.cancellation');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('user.cancellation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->entity->id() == $this->currentUser()->id()) {
      return $this->t('Are you sure you want to cancel your account?');
    }
    return $this->t('Are you sure you want to cancel the account %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $description = '';
    $default_method = $this->config('user.settings')->get('cancel_method');
    $own_account = $this->entity->id() == $this->currentUser()->id();
    if ($this->selectCancel) {
      $description = $this->t('Select the method to cancel the account above.');
    }
    // Options supplied via user_cancel_methods() can have a custom
    // #confirm_description property for the confirmation form description.
    // This text refers to "Your account" so only user it if cancelling own account.
    elseif ($own_account && isset($this->cancelMethods[$default_method]['#confirm_description'])) {
      $description = $this->cancelMethods[$default_method]['#confirm_description'];
    }
    else {
      $description = $this->cancelMethods['#options'][$default_method];
    }
    return $description . ' ' . $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Cancel account');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = $this->currentUser();
    $this->cancelMethods = user_cancel_methods();

    // Display account cancellation method selection, if allowed.
    $own_account = $this->entity->id() == $user->id();
    $this->selectCancel = $user->hasPermission('administer users') || $user->hasPermission('select account cancellation method');

    $form['user_cancel_method'] = [
      '#type' => 'radios',
      '#title' => $own_account ? $this->t('When cancelling your account') : $this->t('When cancelling the account'),
      '#access' => $this->selectCancel,
    ];
    $form['user_cancel_method'] += $this->cancelMethods;

    // When managing another user, can skip the account cancellation
    // confirmation mail (by default).
    $override_access = !$own_account;
    $form['user_cancel_confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require email confirmation to cancel account'),
      '#default_value' => !$override_access,
      '#access' => $override_access,
      '#description' => $this->t('When enabled, the user must confirm the account cancellation via email.'),
    ];
    // Also allow to send account canceled notification mail, if enabled.
    $default_notify = $this->config('user.settings')->get('notify.status_canceled');
    $form['user_cancel_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify user when account is canceled'),
      '#default_value' => ($override_access ? FALSE : $default_notify),
      '#access' => $override_access && $default_notify,
      '#description' => $this->t('When enabled, the user will receive an email notification after the account has been canceled.'),
    ];

    // Always provide entity id in the same form key as in the entity edit form.
    $form['uid'] = ['#type' => 'value', '#value' => $this->entity->id()];

    // Store the user permissions so that it can be altered in hook_form_alter()
    // if desired.
    $form['access'] = [
      '#type' => 'value',
      '#value' => !$own_account,
    ];

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Cancel account immediately, if the current user has administrative
    // privileges, no confirmation mail shall be sent, and the user does not
    // attempt to cancel the own account.
    if (!$form_state->isValueEmpty('access') && $form_state->isValueEmpty('user_cancel_confirm') && $this->entity->id() != $this->currentUser()->id()) {
      $this->userCancellation->progressiveUserCancellation($this->entity, $form_state->getValue('user_cancel_method'), $form_state->getValues());

      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    }
    else {
      // Store cancelling method and whether to notify the user in
      // $this->entity for
      // \Drupal\user\Controller\UserController::confirmCancel().
      $this->entity->user_cancel_method = $form_state->getValue('user_cancel_method');
      $this->entity->user_cancel_notify = $form_state->getValue('user_cancel_notify');
      $this->entity->save();
      _user_mail_notify('cancel_confirm', $this->entity);
      $this->messenger()->addStatus($this->t('A confirmation request to cancel your account has been sent to your email address.'));
      $this->logger('user')->notice('Sent account cancellation request to %name %email.', ['%name' => $this->entity->label(), '%email' => '<' . $this->entity->getEmail() . '>']);

      $form_state->setRedirect(
        'entity.user.canonical',
        ['user' => $this->entity->id()]
      );
    }
  }

}
