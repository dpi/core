<?php

namespace Drupal\entity_test_revlog;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;

/**
 * Defines the access control handler for test entity types.
 */
class EntityTestRevlogAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    assert($entity instanceof EntityTestWithRevisionLog);

    // Revision access checks use label instead of permission so access can
    // vary by individual revisions, since 'name' field can vary by revision.
    $labels = explode(',', $entity->label());
    $labels = array_map('trim', $labels);
    if (in_array($operation, [
      'view',
      'view label',
      'view all revisions',
      'view revision',
      'revert',
      'delete revision',
    ], TRUE)) {
      return AccessResult::allowedIf(in_array($operation, $labels, TRUE));
    }

    // No opinion.
    return AccessResult::neutral();
  }

}
