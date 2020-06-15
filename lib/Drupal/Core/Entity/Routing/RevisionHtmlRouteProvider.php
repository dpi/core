<?php

namespace Drupal\Core\Entity\Routing;

use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\Controller\VersionHistoryController;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides entity revision routes.
 */
class RevisionHtmlRouteProvider implements EntityRouteProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = new RouteCollection();
    $entityTypeId = $entity_type->id();

    if ($version_history_route = $this->getVersionHistoryRoute($entity_type)) {
      $collection->add("entity.$entityTypeId.version_history", $version_history_route);
    }

    if ($revision_view_route = $this->getRevisionViewRoute($entity_type)) {
      $collection->add("entity.$entityTypeId.revision", $revision_view_route);
    }

    if ($revision_revert_route = $this->getRevisionRevertRoute($entity_type)) {
      $collection->add("entity.$entityTypeId.revision_revert_form", $revision_revert_route);
    }

    return $collection;
  }

  /**
   * Gets the entity revision history route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision revert route, or NULL if the entity type does not
   *   support viewing version history.
   */
  protected function getVersionHistoryRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('version-history')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    return (new Route($entityType->getLinkTemplate('version-history')))
      ->addDefaults([
        '_controller' => VersionHistoryController::class . '::versionHistory',
        '_title' => 'Revisions',
      ])
      ->setRequirement('_entity_access_revision', "$entityTypeId.list")
      ->setOption('entity_type_id', $entityTypeId)
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
      ]);
  }

  /**
   * Gets the entity revision view route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision view route, or NULL if the entity type does not
   *   support viewing revisions.
   */
  protected function getRevisionViewRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('revision')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    return (new Route($entityType->getLinkTemplate('revision')))
      ->addDefaults([
        '_controller' => EntityViewController::class . '::viewRevision',
        '_title_callback' => EntityController::class . '::title',
      ])
      ->addRequirements([
        '_entity_access_revision' => "$entityTypeId.view",
      ])
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
        $entityTypeId . '_revision' => [
          'type' => 'entity_revision:' . $entityTypeId,
        ],
      ]);
  }

  /**
   * Gets the entity revision revert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The entity revision revert route, or NULL if the entity type does not
   *   support reverting revisions.
   */
  protected function getRevisionRevertRoute(EntityTypeInterface $entityType): ?Route {
    if (!$entityType->hasLinkTemplate('revision-revert-form')) {
      return NULL;
    }

    $entityTypeId = $entityType->id();
    return (new Route($entityType->getLinkTemplate('revision-revert-form')))
      ->addDefaults([
        '_entity_form' => $entityTypeId . '.revision-revert',
        '_title' => 'Revert revision',
      ])
      ->addRequirements([
        '_entity_access_revision' => "$entityTypeId.update",
      ])
      ->setOption('parameters', [
        $entityTypeId => [
          'type' => 'entity:' . $entityTypeId,
        ],
        $entityTypeId . '_revision' => [
          'type' => 'entity_revision:' . $entityTypeId,
        ],
      ]);
  }

}
