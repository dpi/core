<?php

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests the positioning of a content moderation field on entity forms.
 *
 * Tests whether the weight of the content moderation field in an entity form
 * display affects the positioning in field UI and entity forms.
 *
 * @group content_moderation
 */
class ModerationStateFormDisplayTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'field_ui',
    'content_moderation',
    'node',
  ];

  /**
   * Test the position of the moderation state widget.
   *
   * Tests when editing the moderation widget on a form display and when an
   * entity with the widget is displayed.
   *
   * @param bool $state_is_after
   *   Whether the moderation state widget should be weighted before or after
   *   another field.
   *
   * @dataProvider providerModerationWidgetWeight
   */
  public function testModerationWidgetWeight($state_is_after) {
    $entity_type = 'node';
    $bundle = 'news';

    $node_type = NodeType::create([
      'type' => $bundle,
    ]);
    $node_type->save();

    $user = $this->drupalCreateUser([
      'administer node form display',
    ]);
    $this->drupalLogin($user);

    $workflow = Workflow::load('editorial');
    $workflow->getTypePlugin()->addEntityTypeAndBundle($entity_type, $bundle);
    $workflow->save();

    $url = Url::fromRoute('entity.entity_form_display.' . $entity_type . '.default', [
      'node_type' => $bundle,
    ]);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    $title_needle = '/<td>Title<\/td>/';
    $state_needle = '/<td>Moderation state<\/td>/';

    $edit = [
      'fields[title][weight]' => 0,
      'fields[moderation_state][weight]' => $state_is_after ? 1 : -1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Despite the weight, it is visually being displayed in the correct order.
    $title_position = $this->getMatchPosition($title_needle);
    $state_position = $this->getMatchPosition($state_needle);
    $this->assertNotFalse($title_position, 'Title row found on page.');
    $this->assertNotFalse($state_position, 'State row found on page.');
    if ($state_is_after) {
      $this->assertTrue($title_position < $state_position, 'State row is after title row.');
    }
    else {
      $this->assertTrue($title_position > $state_position, 'State row is before title row.');
    }

    // Login as a content creator.
    $user = $this->drupalCreateUser([
      'create ' . $bundle . ' content',
      'use editorial transition create_new_draft',
      'use editorial transition publish',
      'use editorial transition archive',
    ]);
    $this->drupalLogin($user);

    $url = Url::fromRoute('node.add', ['node_type' => $bundle]);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);

    $title_needle = '/<input.*title\[0\]\[value\].*>/';
    $state_needle = '/<select.*moderation_state.*>.*<\/select>/';
    $title_position = $this->getMatchPosition($title_needle);
    $state_position = $this->getMatchPosition($state_needle);
    $this->assertNotFalse($title_position, 'Title widget found on page.');
    $this->assertNotFalse($state_position, 'State widget found on page.');
    if ($state_is_after) {
      $this->assertTrue($title_position < $state_position, 'State widget is after title widget.');
    }
    else {
      $this->assertTrue($title_position > $state_position, 'State widget is before title widget.');
    }
  }

  /**
   * Data provider for testModerationWidgetWeight().
   *
   * @return array
   *   The data sets.
   */
  public function providerModerationWidgetWeight() {
    return [
      'Moderation state is after title' => [TRUE],
      'Moderation state is before title' => [FALSE],
    ];
  }

  /**
   * Get the string position where the regex matches in the page response.
   *
   * @return int|false
   *   The position of the match or FALSE.
   */
  protected function getMatchPosition($regex) {
    $response = $this->getSession()->getPage()->getContent();
    $matches = [];
    preg_match($regex, $response, $matches, PREG_OFFSET_CAPTURE);
    return isset($matches[0][1]) ? $matches[0][1] : FALSE;
  }

}
