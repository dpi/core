<?php

namespace Drupal\Tests\system\Kernel\Entity;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestNoLabel;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests entity reference selection plugins.
 *
 * @group entity_reference
 */
class EntityReferenceSelectionReferenceableTest extends KernelTestBase {

  use EntityReferenceTestTrait;

  /**
   * Bundle of 'entity_test_no_label' entity.
   *
   * @var string
   */
  protected $bundle;

  /**
   * Labels to be tested.
   *
   * @var array
   */
  protected static $labels = ['abc', 'Xyz_', 'xyabz_', 'foo_', 'bar_', 'baz_', 'șz_', NULL, '<strong>'];

  /**
   * The selection handler.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface.
   */
  protected $selectionHandler;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'user', 'field', 'entity_reference', 'node', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_no_label');

    // Create a new node-type.
    NodeType::create([
      'type' => $node_type = Unicode::strtolower($this->randomMachineName()),
      'name' => $this->randomString(),
    ])->save();

    // Create an entity reference field targeting 'entity_test_no_label'
    // entities.
    $field_name = Unicode::strtolower($this->randomMachineName());
    $this->createEntityReferenceField('node', $node_type, $field_name, $this->randomString(), 'entity_test_no_label');
    $field_config = FieldConfig::loadByName('node', $node_type, $field_name);
    $this->selectionHandler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($field_config);

    // Generate a bundle name to be used with 'entity_test_no_label'.
    $this->bundle = Unicode::strtolower($this->randomMachineName());
  }

  /**
   * Tests the 'allow_self_reference' selection handler setting.
   *
   * @param $allow_self_reference
   *   Value for 'allow_self_reference' selection handler setting.
   * @param $entity_is_referencable
   *   Assert whether the referencing entity is referencable.
   *
   * @dataProvider providerAllowSelfReference
   */
  public function testAllowSelfReference($allow_self_reference, $entity_is_referencable) {
    $field_name = 'field_test';
    $this->createEntityReferenceField('entity_test', 'entity_test', $field_name, 'Test entity reference', 'entity_test');
    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', $field_name);
    $handler_settings = $field_config->getSetting('handler_settings');
    $handler_settings['allow_self_reference'] = $allow_self_reference;
    $field_config
      ->setSetting('handler_settings', $handler_settings)
      ->save();

    $entity = EntityTestNoLabel::create(['name' => $this->randomMachineName()]);
    $entity->save();
    $other = EntityTest::create(['name' => $this->randomMachineName()]);
    $other->save();

    $handler = $this->container
      ->get('plugin.manager.entity_reference_selection')
      ->getSelectionHandler($field_config, $entity);

    $referenceable = $handler->getReferenceableEntities()['entity_test'];
    if ($entity_is_referencable) {
      $this->assertArrayHasKey($entity->id(), $referenceable, 'Can reference entity.');
    }
    else {
      $this->assertArrayNotHasKey($entity->id(), $referenceable, 'Can not reference entity.');
    }
    // Other entity is always referencable.
    $this->assertArrayHasKey($other->id(), $referenceable, 'Can reference other entity.');
  }

  /**
   * Data provider for testAllowSelfReference().
   */
  public function providerAllowSelfReference() {
    return [
      'when setting is omitted, referencing entity is referencable' => [
        NULL,
        TRUE,
      ],
      'when setting is true, referencing entity is referencable' => [
        TRUE,
        TRUE,
      ],
      'when setting is false, referencing entity is not referencable' => [
        FALSE,
        FALSE,
      ],
    ];
  }

  /**
   * Tests entities can be referenced despite having the same ID as referencer.
   *
   * Ensures that the 'allow_self_reference' selection handler setting does not
   * attempt to exclude entities that are different entity types.
   */
  public function testPreventSelfReferenceDifferentEntityType() {
    $field_name = 'field_test';
    $this->createEntityReferenceField('entity_test', 'entity_test', $field_name, 'Test entity reference', 'entity_test');
    $field_config = FieldConfig::loadByName('entity_test', 'entity_test', $field_name);
    $handler_settings = $field_config->getSetting('handler_settings');
    $handler_settings['allow_self_reference'] = FALSE;
    $field_config
      ->setSetting('handler_settings', $handler_settings)
      ->save();

    $entity = EntityTestNoLabel::create(['name' => $this->randomMachineName()]);
    $entity->save();
    $other = EntityTest::create(['name' => $this->randomMachineName()]);
    $other->save();

    // The entity type doesn't matter, so long as it is a different type to the
    // other entity.
    $this->assertNotEquals($entity->getEntityTypeId(), $other->getEntityTypeId());
    // This test requires ID's to be the same.
    $this->assertEquals($entity->id(), $other->id());

    $handler = $this->container
      ->get('plugin.manager.entity_reference_selection')
      ->getSelectionHandler($field_config, $entity);

    $referenceable = $handler->getReferenceableEntities()['entity_test'];
    $this->assertArrayHasKey($entity->id(), $referenceable, 'Can reference entity even though the IDs are the same.');
  }

  /**
   * Tests values returned by SelectionInterface::getReferenceableEntities()
   * when the target entity type has no 'label' key.
   *
   * @param mixed $match
   *   The input text to be checked.
   * @param string $match_operator
   *   The matching operator.
   * @param int $limit
   *   The limit of returning records.
   * @param int $count_limited
   *   The expected number of limited entities to be retrieved.
   * @param array $items
   *   Array of entity labels expected to be returned.
   * @param int $count_all
   *   The total number (unlimited) of entities to be retrieved.
   *
   * @dataProvider providerTestCases
   */
  public function testReferenceablesWithNoLabelKey($match, $match_operator, $limit, $count_limited, array $items, $count_all) {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity.manager')
      ->getStorage('entity_test_no_label');

    // Create 6 entities to be referenced by the field.
    foreach (static::$labels as $name) {
      $storage->create([
        'id' => Unicode::strtolower($this->randomMachineName()),
        'name' => $name,
        'type' => $this->bundle,
      ])->save();
    }

    // Test ::getReferenceableEntities().
    $referenceables = $this->selectionHandler->getReferenceableEntities($match, $match_operator, $limit);

    // Number of returned items.
    if (empty($count_limited)) {
      $this->assertTrue(empty($referenceables[$this->bundle]));
    }
    else {
      $this->assertSame(count($referenceables[$this->bundle]), $count_limited);
    }

    // Test returned items.
    foreach ($items as $item) {
      // SelectionInterface::getReferenceableEntities() always return escaped
      // entity labels.
      // @see \Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface::getReferenceableEntities()
      $item = is_string($item) ? Html::escape($item) : $item;
      $this->assertTrue(array_search($item, $referenceables[$this->bundle]) !== FALSE);
    }

    // Test ::countReferenceableEntities().
    $count_referenceables = $this->selectionHandler->countReferenceableEntities($match, $match_operator);
    $this->assertSame($count_referenceables, $count_all);
  }

  /**
   * Provides test cases for ::testReferenceablesWithNoLabelKey() test.
   *
   * @return array[]
   */
  public function providerTestCases() {
    return [
      // All referenceables, no limit. Expecting 9 items.
      [NULL, 'CONTAINS', 0, 9, static::$labels, 9],
      // Referenceables containing 'w', no limit. Expecting no item.
      ['w', 'CONTAINS', 0, 0, [], 0],
      // Referenceables starting with 'w', no limit. Expecting no item.
      ['w', 'STARTS_WITH', 0, 0, [], 0],
      // Referenceables containing 'ab', no limit. Expecting 2 items ('abc',
      // 'xyabz').
      ['ab', 'CONTAINS', 0, 2, ['abc', 'xyabz_'], 2],
      // Referenceables starting with 'A', no limit. Expecting 1 item ('abc').
      ['A', 'STARTS_WITH', 0, 1, ['abc'], 1],
      // Referenceables containing '_', limited to 3. Expecting 3 limited items
      // ('Xyz_', 'xyabz_', 'foo_') and 5 total.
      ['_', 'CONTAINS', 3, 3, ['Xyz_', 'xyabz_', 'foo_'], 6],
      // Referenceables ending with 'z_', limited to 3. Expecting 3 limited
      // items ('Xyz_', 'xyabz_', 'baz_') and 4 total.
      ['z_', 'ENDS_WITH', 3, 3, ['Xyz_', 'xyabz_', 'baz_'], 4],
      // Referenceables identical with 'xyabz_', no limit. Expecting 1 item
      // ('xyabz_').
      ['xyabz_', '=', 0, 1, ['xyabz_'], 1],
      // Referenceables greater than 'foo', no limit. Expecting 4 items ('Xyz_',
      // 'xyabz_', 'foo_', 'șz_').
      ['foo', '>', 0, 4, ['Xyz_', 'xyabz_', 'foo_', 'șz_'], 4],
      // Referenceables greater or identical with 'baz_', no limit. Expecting 5
      // items ('Xyz_', 'xyabz_', 'foo_', 'baz_', 'șz_').
      ['baz_', '>=', 0, 5, ['Xyz_', 'xyabz_', 'foo_', 'baz_', 'șz_'], 5],
      // Referenceables less than 'foo', no limit. Expecting 5 items ('abc',
      // 'bar_', 'baz_', NULL, '<strong>').
      ['foo', '<', 0, 5, ['abc', 'bar_', 'baz_', NULL, '<strong>'], 5],
      // Referenceables less or identical with 'baz_', no limit. Expecting 5
      // items ('abc', 'bar_', 'baz_', NULL, '<strong>').
      ['baz_', '<=', 0, 5, ['abc', 'bar_', 'baz_', NULL, '<strong>'], 5],
      // Referenceables not identical with 'baz_', no limit. Expecting 7 items
      // ('abc', 'Xyz_', 'xyabz_', 'foo_', 'bar_', 'șz_', NULL, '<strong>').
      ['baz_', '<>', 0, 8, ['abc', 'Xyz_', 'xyabz_', 'foo_', 'bar_', 'șz_', NULL, '<strong>'], 8],
      // Referenceables in ('bar_', 'baz_'), no limit. Expecting 2 items
      // ('bar_', 'baz_')
      [['bar_', 'baz_'], 'IN', 0, 2, ['bar_', 'baz_'], 2],
      // Referenceables not in ('bar_', 'baz_'), no limit. Expecting 6 items
      // ('abc', 'Xyz_', 'xyabz_', 'foo_', 'șz_', NULL, '<strong>')
      [['bar_', 'baz_'], 'NOT IN', 0, 7, ['abc', 'Xyz_', 'xyabz_', 'foo_', 'șz_', NULL, '<strong>'], 7],
      // Referenceables not null, no limit. Expecting 9 items ('abc', 'Xyz_',
      // 'xyabz_', 'foo_', 'bar_', 'baz_', 'șz_', NULL, '<strong>').
      //
      // Note: Even we set the name as NULL, when retrieving the label from the
      //   entity we'll get an empty string, meaning that this match operator
      //   will return TRUE every time.
      [NULL, 'IS NOT NULL', 0, 9, static::$labels, 9],
      // Referenceables null, no limit. Expecting 9 items ('abc', 'Xyz_',
      // 'xyabz_', 'foo_', 'bar_', 'baz_', 'șz_', NULL, '<strong>').
      //
      // Note: Even we set the name as NULL, when retrieving the label from the
      //   entity we'll get an empty string, meaning that this match operator
      //   will return FALSE every time.
      [NULL, 'IS NULL', 0, 9, static::$labels, 9],
      // Referenceables containing '<strong>' markup, no limit. Expecting 1 item
      // ('<strong>').
      ['<strong>', 'CONTAINS', 0, 1, ['<strong>'], 1],
      // Test an unsupported operator. We expect no items.
      ['abc', '*unsupported*', 0, 0, [], 0],
    ];
  }

}
