<?php

namespace Drupal\Core\Entity\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a trait for common revision UI functionality.
 */
trait RevisionControllerTrait {

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  abstract protected function entityTypeManager();

  /**
   * Returns the language manager service.
   *
   * @return \Drupal\Core\Language\LanguageManagerInterface
   *   The language manager.
   */
  abstract public function languageManager();

  /**
   * Builds a link to revert an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $revision
   *   The entity to build a revert revision link for.
   *
   * @return array|null
   *   A link to revert an entity revision, or NULL if the entity type does not
   *   have an a route to revert an entity revision.
   */
  abstract protected function buildRevertRevisionLink(EntityInterface $revision): ?array;

  /**
   * Builds a link to delete an entity revision.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity_revision
   *   The entity to build a delete revision link for.
   *
   * @return array|null
   *   A link render array.
   */
  abstract protected function buildDeleteRevisionLink(EntityInterface $entity_revision): ?array;

  /**
   * Get a description of the revision.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The entity revision.
   *
   * @return array
   *   A render array describing the revision.
   */
  abstract protected function getRevisionDescription(RevisionableInterface $revision): array;

  /**
   * Loads all revision IDs of an entity sorted by revision ID descending.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   The entity.
   *
   * @return int|string[]
   *   An array of revision IDs.
   */
  protected function revisionIds(RevisionableInterface $entity): array {
    $entity_type = $entity->getEntityType();
    $result = $this->entityTypeManager()->getStorage($entity_type->id())->getQuery()
      ->allRevisions()
      ->condition($entity_type->getKey('id'), $entity->id())
      ->sort($entity_type->getKey('revision'), 'DESC')
      ->execute();
    return array_keys($result);
  }

  /**
   * Generates an overview table of revisions of an entity.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $entity
   *   A revisionable entity.
   *
   * @return array
   *   A render array.
   */
  protected function revisionOverview(RevisionableInterface $entity): array {
    $currentLangcode = $this->languageManager()
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    $entityStorage = $this->entityTypeManager()->getStorage($entity->getEntityTypeId());
    assert($entityStorage instanceof RevisionableStorageInterface);

    $rows = [];
    foreach ($entityStorage->loadMultipleRevisions($this->revisionIds($entity)) as $revision) {
      $row = [];

      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($currentLangcode) && $revision->getTranslation($currentLangcode)->isRevisionTranslationAffected()) {
        $row[] = $this->getRevisionDescription($revision);

        if ($revision->isDefaultRevision()) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
        }
        else {
          $links = $this->getOperationLinks($revision);
          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }
      }

      $rows[] = $row;
    }

    $build['entity_revisions_table'] = [
      '#theme' => 'table',
      '#header' => [
        $this->t('Revision'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
    ];

    (new CacheableMetadata())
      // Only dealing with this entity and no external dependencies.
      ->addCacheableDependency($entity)
      ->addCacheContexts(['languages:language_content'])
      ->applyTo($build);

    return $build;
  }

  /**
   * Get operations for an entity revision.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   The entity to build revision links for.
   *
   * @return array
   *   An array of operation links.
   */
  protected function getOperationLinks(RevisionableInterface $revision): array {
    // Removes links which are inaccessible or not rendered.
    return array_filter([
      $this->buildRevertRevisionLink($revision),
      $this->buildDeleteRevisionLink($revision),
    ]);
  }

}
