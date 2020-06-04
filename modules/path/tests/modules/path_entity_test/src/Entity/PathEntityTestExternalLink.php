<?php

namespace Drupal\path_entity_test\Entity;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Test entity for a canonical link to external URL.
 *
 * @ContentEntityType(
 *   id = "path_entity_test_external",
 *   label = @Translation("Test entity with external canonical link"),
 *   base_table = "path_entity_test_external",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 * )
 */
class PathEntityTestExternalLink extends EntityTest {

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    return Url::fromUri('http://example.com/');
  }

}
