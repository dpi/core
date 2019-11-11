<?php

namespace Drupal\Tests\path\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestNoCanonicalLinkTemplate;
use Drupal\KernelTests\KernelTestBase;
use Drupal\path_entity_test\Entity\PathEntityTestExternalLink;

/**
 * Tests path field list class.
 *
 * @group path
 * @coversDefaultClass \Drupal\path\Plugin\Field\FieldType\PathFieldItemList
 */
class PathFieldItemListTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'path',
    'system',
    'entity_test',
    'path_entity_test',
    'user',
  ];

  /**
   * Tests getting path for an unsaved entity.
   *
   * @covers ::computeValue
   */
  public function testPathUnsaved() {
    $this->installEntitySchema('entity_test');

    $entity = EntityTest::create();
    // Unset ID to throw EntityMalformedException from $entity->toUrl().
    $this->assertEquals('', $entity->path->value);
  }

  /**
   * Tests getting path for an entity without a canonical link template.
   *
   * @covers ::computeValue
   */
  public function testPathWithoutCanonicalLinkTemplate() {
    $this->installEntitySchema('entity_test_no_canonical');

    $entity = EntityTestNoCanonicalLinkTemplate::create();
    $this->assertFalse($entity->hasLinkTemplate('canonical'));
    // Need to save to avoid unsaved check.
    $entity->save();
    // Entities with no canonical link template throw
    // UndefinedLinkTemplateException from $entity->toUrl().
    $this->assertNull($entity->path->value);
  }

  /**
   * Tests getting path for an entity with a canonical URL linking to external.
   *
   * @covers ::computeValue
   */
  public function testPathCanonicalExternal() {
    $this->installEntitySchema('path_entity_test_external');

    $entity = PathEntityTestExternalLink::create();
    // Need to save to avoid unsaved check.
    $entity->save();
    // Entities with canonical URLS to external (aka unrouted) throw
    // UnexpectedValueException from $entity->toUrl().
    $this->assertNull($entity->path->value);
  }

}
