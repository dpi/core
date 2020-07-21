<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access to an entity revision.
 */
class EntityRevisionAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Statically cached access check results.
   *
   * @var \Drupal\Core\Access\AccessResultInterface[]
   */
  protected $accessCache = [];

  /**
   * Creates a new EntityRevisionAccessCheck instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Checks routing access for an entity revision.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which to check access.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   A route match.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, RouteMatchInterface $routeMatch): AccessResultInterface {
    $operation = $route->getRequirement('_entity_access_revision');
    [$revisionParameterName, $operation] = explode('.', $operation, 2);
    $revision = $routeMatch->getParameter($revisionParameterName);
    assert($revision instanceof RevisionableInterface);
    return $this->checkAccess($revision, $account, $operation);
  }

  /**
   * Checks entity revision access.
   *
   * @param \Drupal\Core\Entity\RevisionableInterface $revision
   *   An entity revision.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   A user object representing the user for whom the operation is to be
   *   performed.
   * @param string $operation
   *   The operation to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Whether the operation may be performed.
   */
  protected function checkAccess(RevisionableInterface $revision, AccountInterface $account, string $operation): AccessResultInterface {
    $langcode = $revision->language()->getId();
    $cid = $revision->getRevisionId() . ':' . $langcode . ':' . $account->id() . ':' . $operation;
    if (isset($this->accessCache[$cid])) {
      return $this->accessCache[$cid];
    }

    $this->accessCache[$cid] = AccessResult::neutral();
    if ($operation === 'delete revision') {
      // Disallow deleting latest and current revision.
      $this->accessCache[$cid] = AccessResult::allowedIf(!$revision->isDefaultRevision() && !$revision->isLatestRevision());
    }
    elseif ($operation === 'revert') {
      // Disallow reverting to latest (a pointless exercise).
      $this->accessCache[$cid] = AccessResult::allowedIf(!$revision->isLatestRevision());
    }
    else {
      throw new \LogicException('Unrecognized operation');
    }

    return $this->accessCache[$cid];
  }

}
