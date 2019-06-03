<?php

namespace Drupal\Tests\link\Kernel;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\dblog\Controller\DbLogController;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\link\LinkItemInterface;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Tests the Field Formatter for the link field type.
 *
 * @group link
 */
class LinkFormatterTest extends FieldKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['link', 'dblog'];

  /**
   * @var string
   */
  protected $entityType;

  /**
   * @var string
   */
  protected $bundle;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * @var string
   */
  protected $label;

  /**
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('dblog', 'watchdog');

    // Create a generic link field for validation.
    $this->entityType = 'entity_test';
    $this->bundle = $this->entityType;
    $this->fieldName = 'test_link';
    $this->label = 'Test link';

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'type' => 'link',
    ]);
    $field_storage->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_storage' => $field_storage,
      'bundle' => $this->bundle,
      'label' => $this->label,
      'settings' => ['link_type' => LinkItemInterface::LINK_GENERIC],
    ])->save();

    $this->display = EntityViewDisplay::create([
      'targetEntityType' => $this->entityType,
      'bundle' => $this->bundle,
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($this->fieldName, [
      'type' => 'link',
      'settings' => [
        'trim_length' => 80,
        'url_only' => TRUE,
        'url_plain' => TRUE,
        'rel' => 0,
        'target' => 0,
      ]
    ]);
    $this->display->save();
  }

  /**
   * Renders fields of a given entity with a given display.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   *
   * @return string
   *   The rendered entity fields.
   */
  protected function renderEntityFields(FieldableEntityInterface $entity) {
    $content = $this->display->build($entity);
    return $this->render($content);
  }

  /**
   * Test building a URL with various URL formats.
   */
  public function testBuildUrl() {
    $malformedUrl = '__fubble_wubble__';
    $entityLabel = 'Link test number three bravo';
    $entity = EntityTest::create([
      'name' => $entityLabel,
    ]);
    $entity->set($this->fieldName, $malformedUrl);
    $entity->save();
    $this->renderEntityFields($entity);
    $this->assertNoText($malformedUrl);
    $this->assertText($this->label);

    $wellFormedUrl = 'http://example.com';
    $entity = EntityTest::create();
    $entity->set($this->fieldName, $wellFormedUrl);
    $entity->save();
    $this->renderEntityFields($entity);
    $this->assertText($wellFormedUrl);
    $this->assertText($this->label);

    $dblog_controller = DbLogController::create($this->container);
    $event = $dblog_controller->eventDetails(1);
    /** @var \Drupal\Core\StringTranslation\TranslatableMarkup $message */
    $message = $event['dblog_table']['#rows'][5][1];
    $arguments = $message->getArguments();
    $renderedMessage = $message->render();
    $this->assertContains('<em class="placeholder">Test link</em>', $renderedMessage);
    $this->assertContains('<em class="placeholder">Test entity</em>', $renderedMessage);
    $this->assertContains(sprintf('<em class="placeholder">%s</em>', $entityLabel), $renderedMessage);
    $this->assertContains($entity->id(), $renderedMessage);
    $this->assertEquals($arguments['%function'], 'Drupal\Core\Url::fromUri()');
    $this->assertEquals($arguments['%type'], 'InvalidArgumentException');
    $this->assertEquals($arguments['@message'], "The URI '{$malformedUrl}' is invalid. You must use a valid URI scheme.");
  }

}
