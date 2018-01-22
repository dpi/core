<?php

namespace Drupal\user\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\user\PermissionHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'User Permission' condition.
 *
 * @Condition(
 *   id = "user_permission",
 *   label = @Translation("User Permission"),
 *   context = {
 *     "user" = @ContextDefinition("entity:user", label = @Translation("User"))
 *   }
 * )
 */
class UserPermission extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $userPermissions;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PermissionHandlerInterface $userPermissions) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->userPermissions = $userPermissions;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user.permissions')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $permissions = $this->userPermissions->getPermissions();

    $options = array_combine(
      array_keys($permissions),
      array_column($permissions, 'title')
    );
    $form['permissions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('When the user has the following permissions'),
      '#default_value' => $this->configuration['permissions'],
      '#options' => $options,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'permissions' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['permissions'] = array_filter($form_state->getValue('permissions'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    $permissions = $this->userPermissions->getPermissions();
    $options = array_combine(
      array_keys($permissions),
      array_column($permissions, 'title')
    );

    $permissions = array_intersect_key($options, $this->configuration['permissions']);
    if (count($permissions) > 1) {
      $permissions = implode(', ', $permissions);
    }
    else {
      $permissions = reset($permissions);
    }
    if (!empty($this->configuration['negate'])) {
      return $this->t('The user does not have permissions @permissions', ['@permissions' => $permissions]);
    }
    else {
      return $this->t('The user has permissions @permissions', ['@permissions' => $permissions]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    $permissions = $this->configuration['permissions'];
    if (empty($permissions)) {
      return TRUE;
    }

    $user = $this->getContextValue('user');
    foreach ($permissions as $permission) {
      if (!$user->hasPermission($permission)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return [
      'user.permissions',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $permissions = $this->userPermissions->getPermissions();
    foreach ($this->configuration['permissions'] as $permission) {
      $definition = isset($permissions[$permission]) ? $permissions[$permission] : NULL;
      if ($definition && isset($definition['provider'])) {
        $dependencies['module'][] = $definition['provider'];
      }
    }

    return $dependencies;
  }

}
