<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks access to a entity revision.
 *
 * @todo This will be replaced by the solution implemented in
 *   https://www.drupal.org/project/drupal/issues/3043321 and is a temporary
 *   measure until that issue is implemented.
 */
class EntityRevisionAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Stores calculated access check results.
   *
   * @var bool[]
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
   *   The currently logged in account.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account, RouteMatchInterface $route_match = NULL): AccessResultInterface {
    $operation = $route->getRequirement('_entity_access_revision');
    [$entity_type_id, $operation] = explode('.', $operation, 2);

    if ($operation === 'list') {
      $entity = $route_match->getParameter($entity_type_id);
      return AccessResult::allowedIf($this->checkAccess($entity, $account, $operation))->cachePerPermissions();
    }
    else {
      $entity_revision = $route_match->getParameter($entity_type_id . '_revision');
      return AccessResult::allowedIf($entity_revision && $this->checkAccess($entity_revision, $account, $operation))->cachePerPermissions();
    }
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
   *   The specific operation being checked. Defaults to 'view'.
   *
   * @return bool
   *   Whether the operation may be performed.
   */
  protected function checkAccess(RevisionableInterface $revision, AccountInterface $account, $operation = 'view'): bool {
    $entity_type = $revision->getEntityType();
    $entity_type_id = $revision->getEntityTypeId();
    $entity_access = $this->entityTypeManager->getAccessControlHandler($entity_type_id);

    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($entity_storage instanceof RevisionableStorageInterface);

    $map = [
      'view' => "view all $entity_type_id revisions",
      'list' => "view all $entity_type_id revisions",
      'update' => "revert all $entity_type_id revisions",
      'delete' => "delete all $entity_type_id revisions",
    ];
    $bundle = $revision->bundle();
    $type_map = [
      'view' => "view $entity_type_id $bundle revisions",
      'list' => "view $entity_type_id $bundle revisions",
      'update' => "revert $entity_type_id $bundle revisions",
      'delete' => "delete $entity_type_id $bundle revisions",
    ];

    if (!$revision || !isset($map[$operation]) || !isset($type_map[$operation])) {
      // If there was no node to check against, or the $op was not one of the
      // supported ones, we return access denied.
      return FALSE;
    }

    // Statically cache access by revision ID, language code, user account ID,
    // and operation.
    $langcode = $revision->language()->getId();
    $cid = $revision->getRevisionId() . ':' . $langcode . ':' . $account->id() . ':' . $operation;

    if (!isset($this->accessCache[$cid])) {
      $admin_permission = $entity_type->getAdminPermission();

      // Perform basic permission checks first.
      if (!$account->hasPermission($map[$operation]) && !$account->hasPermission($type_map[$operation]) && ($admin_permission && !$account->hasPermission($admin_permission))) {
        $this->accessCache[$cid] = FALSE;
        return FALSE;
      }

      if (($admin_permission = $entity_type->getAdminPermission()) && $account->hasPermission($admin_permission)) {
        $this->accessCache[$cid] = TRUE;
      }
      else {
        // Entity access handlers are generally not aware of the "list"
        // operation.
        $operation = $operation == 'list' ? 'view' : $operation;
        // First check the access to the default revision and finally, if the
        // node passed in is not the default revision then access to that, too.
        $this->accessCache[$cid] = $entity_access->access($entity_storage->load($revision->id()), $operation, $account) && ($revision->isDefaultRevision() || $entity_access->access($revision, $operation, $account));
      }
    }

    return $this->accessCache[$cid];
  }

}
