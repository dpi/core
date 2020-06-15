<?php

namespace Drupal\Core\Entity\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting an entity revision.
 */
class RevisionRevertForm extends EntityConfirmFormBase {

  /**
   * The entity revision.
   *
   * @var \Drupal\Core\Entity\RevisionableInterface|\Drupal\Core\Entity\RevisionLogInterface
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
  public function getQuestion() {
    if ($this->revision instanceof RevisionLogInterface) {
      return $this->t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
    }
    return $this->t('Are you sure you want to revert the revision?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    if ($this->revision->getEntityType()->hasLinkTemplate('version-history')) {
      return $this->revision->toUrl('version-history');
    }
    return $this->revision->toUrl();
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
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\RevisionableInterface|null $_entity_revision
   *   The entity revision as supplied by EntityRevisionRouteEnhancer, a default
   *   value is set to maintain compatibility with interface, though it will
   *   never be null.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RevisionableInterface $_entity_revision = NULL) {
    $this->revision = $_entity_revision;
    // Ensure revision is never null.
    assert($this->revision instanceof RevisionableInterface);
    return parent::buildForm($form, $form_state);
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

    $this->revision->save();

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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The bundle label.
   */
  protected function getBundleLabel(EntityInterface $entity): string {
    $bundle_info = $this->bundleInformation->getBundleInfo($entity->getEntityTypeId());
    return $bundle_info[$entity->bundle()]['label'];
  }

}
