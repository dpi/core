<?php

namespace Drupal\Tests\layout_builder\Functional;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests Layout Builder forms.
 *
 * @group LayoutBuilderFormTest
 */
class LayoutBuilderFormModeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'layout_builder',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Tests using 'Discard changes' button skips validation and ignores input.
   */
  public function testDiscardValidation() {
    $entityTypeId = 'entity_test';
    $bundle = 'entity_test';

    // Set up a field with a validation constraint.
    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'foo',
      'entity_type' => $entityTypeId,
      'type' => 'string',
    ]);
    $fieldStorage->save();

    FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => $bundle,
      // Expecting required value.
      'required' => TRUE,
    ])->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository */
    $displayRepository = \Drupal::service('entity_display.repository');

    // Enable layout builder custom layouts.
    $displayRepository->getViewDisplay($entityTypeId, $bundle, 'full')
      ->enable()
      ->setThirdPartySetting('layout_builder', 'enabled', TRUE)
      ->setThirdPartySetting('layout_builder', 'allow_custom', TRUE)
      ->save();

    // Add the form mode and show the field with a constraint.
    $formMode = 'layout_builder';
    EntityFormMode::create([
      'id' => sprintf('%s.%s', $entityTypeId, $formMode),
      'targetEntityType' => $entityTypeId,
    ])->save();
    $displayRepository->getFormDisplay($entityTypeId, $bundle, $formMode)
      ->setComponent('foo', [
        'type' => 'string_textfield',
      ])
      ->save();

    $user = $this->drupalCreateUser([
      'view test entity',
      'configure any layout',
      "configure all $entityTypeId $bundle layout overrides",
    ]);
    $this->drupalLogin($user);

    $entity = EntityTest::create();
    $entity->save();

    $urlParameters = ['entity_test' => $entity->id()];
    $url = Url::fromRoute('layout_builder.overrides.entity_test.view', $urlParameters);

    $session = $this->assertSession();

    // When submitting the form normally, a validation error should be shown.
    $this->drupalGet($url);
    $session->fieldExists('foo[0][value]');
    $session->elementAttributeContains('named', ['field', 'foo[0][value]'], 'required', 'required');
    $this->drupalPostForm(NULL, [], 'Save layout');
    $session->pageTextContains('foo field is required.');

    // When Discarding changes, a validation error will not be shown.
    // Reload the form for fresh state.
    $this->drupalGet($url);
    $this->drupalPostForm(NULL, [], 'Discard changes');
    $session->pageTextNotContains('foo field is required.');
    $session->addressEquals(Url::fromRoute('layout_builder.overrides.entity_test.discard_changes', $urlParameters)->toString());
  }

}
