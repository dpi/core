<?php

namespace Drupal\FunctionalJavascriptTests\EntityReference;

use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Tests the output of entity reference autocomplete widgets.
 *
 * @group entity_reference
 */
class EntityReferenceAutocompleteWidgetTest extends JavascriptTestBase {

  use ContentTypeCreationTrait;
  use EntityReferenceTestTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'entity_test', 'entity_reference_selection_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create a Content type and two test nodes.
    $this->createContentType(['type' => 'page']);
    $this->createNode(['title' => 'Test page']);
    $this->createNode(['title' => 'Page test']);

    $user = $this->drupalCreateUser([
      'access content',
      'create page content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that the default autocomplete widget return the correct results.
   */
  public function testEntityReferenceAutocompleteWidget() {
    // Create an entity reference field and use the default 'CONTAINS' match
    // operator.
    $field_name = 'field_test';
    $this->createEntityReferenceField('node', 'page', $field_name, $field_name, 'node', 'default', ['target_bundles' => ['page']]);
    entity_get_form_display('node', 'page', 'default')
      ->setComponent($field_name, [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
        ],
      ])
      ->save();

    // Visit the node add page.
    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $autocomplete_field = $page->findField($field_name . '[0][target_id]');
    $autocomplete_field->setValue('Test');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();

    $results = $page->findAll('css', '.ui-autocomplete li');

    $this->assertCount(2, $results);
    $assert_session->pageTextContains('Test page');
    $assert_session->pageTextContains('Page test');

    // Now switch the autocomplete widget to the 'STARTS_WITH' match operator.
    entity_get_form_display('node', 'page', 'default')
      ->setComponent($field_name, [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'STARTS_WITH',
        ],
      ])
      ->save();

    $this->drupalGet('node/add/page');
    $page = $this->getSession()->getPage();

    $autocomplete_field = $page->findField($field_name . '[0][target_id]');
    $autocomplete_field->setValue('Test');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();

    $results = $page->findAll('css', '.ui-autocomplete li');

    $this->assertCount(1, $results);
    $assert_session->pageTextContains('Test page');
    $assert_session->pageTextNotContains('Page test');
  }

  /**
   * Tests that the autocomplete widget knows about the entity its attached to.
   *
   * Ensures that the entity the autocomplete widget stores the entity it is
   * rendered on, and is available in the autocomplete results AJAX request.
   */
  public function testEntityReferenceAutocompleteWidgetAttachedEntity() {
    $user = $this->drupalCreateUser([
      'administer entity_test content',
    ]);
    $this->drupalLogin($user);

    $field_name = 'field_test';
    $this->createEntityReferenceField('entity_test', 'entity_test', $field_name, $field_name, 'entity_test', 'entity_test_all_except_host', ['target_bundles' => ['entity_test']]);
    $form_display = EntityFormDisplay::load('entity_test.entity_test.default');
    $form_display->setComponent($field_name, [
      'type' => 'entity_reference_autocomplete',
      'settings' => [
        'match_operator' => 'CONTAINS',
      ],
    ]);
    $form_display->save();

    $host = EntityTest::create(['name' => 'dark green']);
    $host->save();
    $other = EntityTest::create(['name' => 'dark blue']);
    $other->save();

    $this->drupalGet($host->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);

    // Trigger the autocomplete.
    $page = $this->getSession()->getPage();
    $autocomplete_field = $page->findField($field_name . '[0][target_id]');
    $autocomplete_field->setValue('dark');
    $this->getSession()->getDriver()->keyDown($autocomplete_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();

    // Check the autocomplete results.
    $results = $page->findAll('css', '.ui-autocomplete li');
    $this->assertCount(1, $results);
    $this->assertSession()->elementTextNotContains('css', '.ui-autocomplete li', 'dark green');
    $this->assertSession()->elementTextContains('css', '.ui-autocomplete li', 'dark blue');
  }

}
