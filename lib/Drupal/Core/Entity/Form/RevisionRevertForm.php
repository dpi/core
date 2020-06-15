<?php

namespace Drupal\Core\Entity\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting an entity revision.
 */
class RevisionRevertForm extends ConfirmFormBase implements EntityFormInterface {

  /**
   * The entity operation.
   *
   * @var string
   */
  protected $operation;

  /**
   * The entity revision.
   *
   * @var \Drupal\Core\Entity\RevisionableInterface
   */
  protected $revision;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity bundle information.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInformation;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new RevisionRevertForm instance.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInformation
   *   The bundle information.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(DateFormatterInterface $dateFormatter, EntityTypeBundleInfoInterface $bundleInformation, MessengerInterface $messenger) {
    $this->dateFormatter = $dateFormatter;
    $this->bundleInformation = $bundleInformation;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.bundle.info'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return $this->revision->getEntityTypeId() . '_revision_revert';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->revision->getEntityTypeId() . '_revision_revert';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    if ($this->getEntity() instanceof RevisionLogInterface) {
      return $this->t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->getEntity()->getRevisionCreationTime())]);
    }
    return $this->t('Are you sure you want to revert the revision?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->getEntity()->getEntityType()->hasLinkTemplate('version-history')) {
      return $this->getEntity()->toUrl('version-history');
    }
    return $this->getEntity()->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['submit']['#submit'] = [
      '::submitForm',
      '::save',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->revision = $this->prepareRevision($this->revision);
    $bundleLabel = $this->getBundleLabel($this->revision);
    $revisionLabel = $this->revision->label();
    if ($this->revision instanceof RevisionLogInterface) {
      // The revision timestamp will be updated when the revision is saved. Keep
      // the original one for the confirmation message.
      $originalRevisionTimestamp = $this->revision->getRevisionCreationTime();

      $date = $this->dateFormatter->format($originalRevisionTimestamp);
      $this->revision->setRevisionLogMessage($this->t('Copy of the revision from %date.', ['%date' => $date]));
      $this->messenger->addMessage($this->t('@type %title has been reverted to the revision from %revision-date.', [
        '@type' => $bundleLabel,
        '%title' => $revisionLabel,
        '%revision-date' => $date,
      ]));
    }
    else {
      $this->messenger->addMessage($this->t('@type %title has been reverted', [
        '@type' => $bundleLabel,
        '%title' => $revisionLabel,
      ]));
    }

    $this->logger($this->revision->getEntityType()->getProvider())->notice('@type: reverted %title revision %revision.', [
      '@type' => $this->revision->bundle(),
      '%title' => $revisionLabel,
      '%revision' => $this->revision->getRevisionId(),
    ]);

    $form_state->setRedirectUrl($this->revision->toUrl('version-history'));
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The revision to be reverted.
   *
   * @return \Drupal\Core\Entity\RevisionableInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevision(RevisionableInterface $revision): RevisionableInterface {
    $revision->setNewRevision();
    $revision->isDefaultRevision(TRUE);
    return $revision;
  }

  /**
   * Returns the bundle label of an entity.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel(RevisionableInterface $entity): string {
    $bundle_info = $this->bundleInformation->getBundleInfo($entity->getEntityTypeId());
    return $bundle_info[$entity->bundle()]['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function setOperation($operation) {
    $this->operation = $operation;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperation() {
    return $this->operation;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    return $this->revision;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntity(EntityInterface $entity) {
    $this->revision = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    return $route_match->getParameter($entity_type_id . '_revision');
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    return $this->revision;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->revision->save();
  }

  /**
   * {@inheritdoc}
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

}
