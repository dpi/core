<?php

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Ensure that #states works properly on form elements of #type 'datetime'.
 *
 * @group datetime
 */
class DatetimeStatesTest extends WebDriverTestBase {

  /**
   * The user object to test with.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime_states_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'stark')
      ->save();

    // Create a user.
    $account = $this->drupalCreateUser();

    // Activate user by logging in.
    $this->drupalLogin($account);
  }

  /**
   * Tests #states functionality on datetime form elements.
   */
  public function testDatetimeStates() {
    $this->drupalGet(Url::fromRoute('datetime_states_test.example'));

    // Make sure the initial state makes sense.
    $page = $this->getSession()->getPage();

    $toggle_invisible = $page->findField('toggle_invisible');
    $this->assertNotEmpty($toggle_invisible);
    $this->assertTrue($toggle_invisible->isVisible());
    $this->assertNoFieldChecked('edit-toggle-invisible');

    $toggle_disabled = $page->findField('toggle_disabled');
    $this->assertNotEmpty($toggle_disabled);
    $this->assertTrue($toggle_disabled->isVisible());
    $this->assertNoFieldChecked('edit-toggle-disabled');

    $toggle_required = $page->findField('toggle_required');
    $this->assertNotEmpty($toggle_required);
    $this->assertTrue($toggle_required->isVisible());
    $this->assertNoFieldChecked('edit-toggle-required');

    $datetime_label = $this->assertSession()->elementExists('css', 'label[for="edit-datetime"]');
    $this->assertNotEmpty($datetime_label);
    $this->assertTrue($datetime_label->isVisible(), 'Datetime label is visible');
    $this->assert($datetime_label->getText() === 'Datetime', 'Datetime label is correct');
    $this->assertFalse($datetime_label->hasClass('js-form-required'), 'Datetime label is not marked as required.');

    $datetime_description = $this->assertSession()->elementExists('css', 'div[id="edit-datetime--description"]');
    $this->assertTrue($datetime_description->isVisible(), 'Datetime description is visible');
    $this->assert($datetime_description->getText() === 'A datetime form element.', 'Datetime description is correct');

    $date_field = $page->findField('datetime[date]');
    $this->assertNotEmpty($date_field);
    $this->assertTrue($date_field->isVisible(), 'Date is visible');
    $this->assertFalse($date_field->hasAttribute('disabled'), 'Date is enabled');
    $this->assertFalse($date_field->hasAttribute('required'), 'Date is optional');

    $time_field = $page->findField('datetime[time]');
    $this->assertNotEmpty($time_field);
    $this->assertTrue($time_field->isVisible(), 'Time is visible');
    $this->assertFalse($time_field->hasAttribute('disabled'), 'Time is enabled');
    $this->assertFalse($time_field->hasAttribute('required'), 'Time is optional');

    // Now, check the 'Invisible' toggle and see what happens.
    $toggle_invisible->check();
    $this->assertFalse($date_field->isVisible(), 'Date is invisible');
    $this->assertFalse($time_field->isVisible(), 'Time is invisible');
    $this->assertFalse($datetime_label->isVisible(), 'Datetime label is invisible');
    $this->assertFalse($datetime_description->isVisible(), 'Datetime label is invisible');

    // Make it visible again.
    $toggle_invisible->uncheck();
    $this->assertTrue($date_field->isVisible(), 'Date is visible');
    $this->assertTrue($time_field->isVisible(), 'Time is visible');
    $this->assertTrue($datetime_label->isVisible(), 'Datetime label is visible');
    $this->assertTrue($datetime_description->isVisible(), 'Datetime description is visible');

    // Make it disabled.
    $toggle_disabled->check();
    $this->assertTrue($date_field->hasAttribute('disabled'), 'Date is disabled');
    $this->assertTrue($time_field->hasAttribute('disabled'), 'Time is disabled');
    // Put it back.
    $toggle_disabled->uncheck();
    $this->assertFalse($date_field->hasAttribute('disabled'), 'Date is enabled');
    $this->assertFalse($time_field->hasAttribute('disabled'), 'Time is enabled');

    // Make it required.
    $toggle_required->check();
    $this->assertTrue($date_field->hasAttribute('required'), 'Date is required');
    $this->assertTrue($time_field->hasAttribute('required'), 'Time is required');
    $this->assertTrue($datetime_label->hasClass('js-form-required'), 'Datetime label is marked as required');
  }

}
