<?php

namespace Drupal\content_moderation\Plugin\Action;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\DependencyTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Changes moderation_state of an entity.
 *
 * @Action(
 *   id = "moderation_state_change",
 *   deriver = "\Drupal\content_moderation\Plugin\Derivative\ModerationStateChangeDeriver"
 * )
 */
class ModerationStateChange extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use DependencyTrait;

  /**
   * The moderation info service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Moderation state change constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModerationInformationInterface $moderation_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moderationInfo = $moderation_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'workflow' => NULL,
      'state' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $workflow_options = [];
    $workflows = Workflow::loadMultipleByType('content_moderation');

    foreach ($workflows as $workflow) {
      if (in_array($this->pluginDefinition['type'], $workflow->getTypePlugin()->getEntityTypes(), TRUE)) {
        $workflow_options[$workflow->id()] = $workflow->label();
      }
    }

    $form['configuration'] = [
      '#type' => 'container',
      '#id' => 'edit-configuration',
    ];

    $form['configuration']['workflow'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow'),
      '#options' => $workflow_options,
      '#default_value' => $this->configuration['workflow'],
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [static::class, 'configurationFormAjax'],
        'wrapper' => 'edit-configuration',
      ],
    ];

    $form['configuration']['workflow_submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change workflow'),
      '#limit_validation_errors' => [['workflow']],
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#submit' => [[static::class, 'configurationFormAjaxSubmit']],
    ];

    if (!$workflow = $form_state->getValue('workflow')) {
      if (!empty($this->configuration['workflow'])) {
        $workflow = $this->configuration['workflow'];
      }
      else {
        $workflow = key($workflow_options);
      }
    }

    $state_options = [];
    foreach ($workflows[$workflow]->getTypePlugin()->getStates() as $state) {
      $state_options[$state->id()] = $this->t('Change moderation state to @state', ['@state' => $state->label()]);
    }

    $form['configuration']['state'] = [
      '#type' => 'select',
      '#title' => $this->t('State'),
      '#options' => $state_options,
      '#default_value' => $this->configuration['state'],
      '#required' => TRUE,
    ];

    return $form;

  }

  /**
   * Ajax callback for the configuration form.
   *
   * @see static::buildConfigurationForm()
   */
  public static function configurationFormAjax($form, FormStateInterface $form_state) {
    return $form['configuration'];
  }

  /**
   * Submit configuration for the non-JS case.
   *
   * @see static::buildConfigurationForm()
   */
  public static function configurationFormAjaxSubmit($form, FormStateInterface $form_state) {
    // Rebuild the form.
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['workflow'] = $form_state->getValue('workflow');
    $this->configuration['state'] = $form_state->getValue('state');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    if (!empty($this->configuration['workflow'])) {
      $this->addDependency('config', 'workflows.workflow.' . $this->configuration['workflow']);
    }
    return $this->dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(ContentEntityInterface $entity = NULL) {
    $entity = $this->loadLatestRevision($entity);
    $entity->moderation_state->value = $this->configuration['state'];
    $entity->save();
  }

  /**
   * Loads the latest revision of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The latest revision of content entity.
   */
  protected function loadLatestRevision(ContentEntityInterface $entity) {
    return $this->moderationInfo->getLatestRevision($entity->getEntityTypeId(), $entity->id());
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!$object || !$object instanceof ContentEntityInterface) {
      $result = AccessResult::forbidden('Not a valid entity.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    if ($workflow = $this->moderationInfo->getWorkflowForEntity($object)) {
      if ($workflow->id() !== $this->configuration['workflow']) {
        $result = AccessResult::forbidden('Not a valid workflow for this entity.');
        $result->addCacheableDependency($workflow);
        return $return_as_object ? $result : $result->isAllowed();
      }
    }
    else {
      $result = AccessResult::forbidden('No workflow found for the entity.');
      return $return_as_object ? $result : $result->isAllowed();
    }
    $object = $this->loadLatestRevision($object);
    // Let content moderation do its job. See content_moderation_entity_access()
    // for more details.
    $access = $object->access('update', $account, TRUE);

    $to_state_id = $this->configuration['state'];
    $from_state = $workflow->getTypePlugin()->getState($object->moderation_state->value);
    // Make sure we can make the transition.
    if ($from_state->canTransitionTo($to_state_id)) {
      $transition = $from_state->getTransitionTo($to_state_id);
      $result = AccessResult::allowedIfHasPermission($account, 'use ' . $workflow->id() . ' transition ' . $transition->id())
        ->andIf($access);
    }
    else {
      $result = AccessResult::forbidden('No valid transition found.');
    }
    $result->addCacheableDependency($workflow);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
