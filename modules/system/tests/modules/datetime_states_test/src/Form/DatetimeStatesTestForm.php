<?php

namespace Drupal\datetime_states_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for testing #states on datetime elements.
 *
 * Includes toggle checkboxes for 'Invisible', 'Disabled' and 'Required', and a
 * datetime field with #states configured appropriately for each checkbox.
 */
class DatetimeStatesTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'datetime_states_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['toggle_invisible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Invisible'),
    ];
    $form['toggle_disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disabled'),
    ];
    $form['toggle_required'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
    ];

    $form['datetime'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Datetime'),
      '#description' => $this->t('A datetime form element.'),
      '#states' => [
        'invisible' => [
          ':input[name="toggle_invisible"]' => ['checked' => TRUE],
        ],
        'disabled' => [
          ':input[name="toggle_disabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="toggle_required"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setErrorByName('datetime', $this->t('This test form should not be submitted.'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Sample form only, nothing to submit.
  }

}
