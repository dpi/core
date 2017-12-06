<?php

namespace Drupal\entity_reference_selection_test\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Allows access to all entities except for the host entity.
 *
 * @EntityReferenceSelection(
 *   id = "entity_test_all_except_host",
 *   label = @Translation("All except host entity."),
 *   entity_types = {"entity_test"},
 *   group = "entity_test_all_except_host"
 * )
 */
class AllExceptHostEntity extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $this->configuration['entity'];

    // The host entity should be the same type as the entity type this plugin
    // supports.
    if ($entity instanceof EntityTest) {
      $target_type = $this->configuration['target_type'];
      $entity_type = $this->entityManager->getDefinition($target_type);
      $query->condition($entity_type->getKey('id'), $entity->id(), '<>');
    }

    return $query;
  }

}
