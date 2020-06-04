<?php

namespace Drupal\entity_test\Entity;

/**
 * Test entity without a canonical link template.
 *
 * Useful for testing getting a link or URL from entity, where behaviour
 * specifically relies on existence on the default 'canonical' template.
 *
 * @ContentEntityType(
 *   id = "entity_test_no_canonical",
 *   label = @Translation("Test entity without canonical link template"),
 *   base_table = "entity_test_no_canonical",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 * )
 */
class EntityTestNoCanonicalLinkTemplate extends EntityTest {

}
