<?php

namespace Drupal\views\Plugin\views\filter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Filter to handle dates stored as a timestamp.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("date")
 */
class Date extends NumericFilter {

  protected function defineOptions() {
    $options = parent::defineOptions();

    // value is already set up properly, we're just adding our new field to it.
    $options['value']['contains']['type']['default'] = 'date';

    // We have to remove all the placeholder-related options, since those are
    // invalid for HTML5 date elements.
    unset($options['expose']['contains']['placeholder']);
    unset($options['expose']['contains']['min_placeholder']);
    unset($options['expose']['contains']['max_placeholder']);

    return $options;
  }

  /**
   * Add a type selector to the value form
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    if (!$form_state->get('exposed')) {
      $form['value']['type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Value type'),
        '#options' => [
          'date' => $this->t('A date in any machine readable format. CCYY-MM-DD HH:MM:SS is preferred.'),
          'offset' => $this->t('An offset from the current time such as "@example1" or "@example2"', ['@example1' => '+1 day', '@example2' => '-2 hours -30 minutes']),
        ],
        '#default_value' => !empty($this->value['type']) ? $this->value['type'] : 'date',
      ];
    }
    parent::valueForm($form, $form_state);
  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    if (!empty($this->options['exposed']) && $form_state->isValueEmpty(['options', 'expose', 'required'])) {
      // Who cares what the value is if it's exposed and non-required.
      return;
    }

    $this->validateValidTime($form['value'], $form_state, $form_state->getValue(['options', 'operator']), $form_state->getValue(['options', 'value']));
  }

  public function validateExposed(&$form, FormStateInterface $form_state) {
    if (empty($this->options['exposed'])) {
      return;
    }

    if (empty($this->options['expose']['required'])) {
      // Who cares what the value is if it's exposed and non-required.
      return;
    }

    $value = &$form_state->getValue($this->options['expose']['identifier']);
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id'])) {
      $operator = &$form_state->getValue($this->options['expose']['operator_id']);
    }
    else {
      $operator = $this->operator;
    }

    $this->validateValidTime($this->options['expose']['identifier'], $form_state, $operator, $value);

  }

  /**
   * Validate that the time values convert to something usable.
   */
  public function validateValidTime(&$form, FormStateInterface $form_state, $operator, $value) {
    $operators = $this->operators();

    if ($operators[$operator]['values'] == 1) {
      $convert = strtotime($value['value']);
      if (!empty($form['value']) && ($convert == -1 || $convert === FALSE)) {
        $form_state->setError($form['value'], $this->t('Invalid date format.'));
      }
    }
    elseif ($operators[$operator]['values'] == 2) {
      $min = strtotime($value['min']);
      if ($min == -1 || $min === FALSE) {
        $form_state->setError($form['min'], $this->t('Invalid date format.'));
      }
      $max = strtotime($value['max']);
      if ($max == -1 || $max === FALSE) {
        $form_state->setError($form['max'], $this->t('Invalid date format.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function hasValidGroupedValue(array $group) {
    if (!is_array($group['value']) || empty($group['value'])) {
      return FALSE;
    }

    // Special case when validating grouped date filters because the
    // $group['value'] array contains the type of filter (date or offset) and
    // therefore the number of items the comparison has to be done against is
    // one greater.
    $operators = $this->operators();
    $expected = $operators[$group['operator']]['values'] + 1;
    $actual = count(array_filter($group['value'], 'static::arrayFilterZero'));

    return $actual == $expected;
  }

  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Store this because it will get overwritten.
    $type = NULL;
    if ($this->isAGroup()) {
      if (is_array($this->group_info)) {
        $type = $this->group_info['type'];
      }
    }
    else {
      $type = $this->value['type'];
    }
    $rc = parent::acceptExposedInput($input);

    // Restore what got overwritten by the parent.
    if (!is_null($type)) {
      $this->value['type'] = $type;
    }

    // Don't filter if value(s) are empty.
    $operators = $this->operators();
    if (!empty($this->options['expose']['use_operator']) && !empty($this->options['expose']['operator_id'])) {
      $operator = $input[$this->options['expose']['operator_id']];
    }
    else {
      $operator = $this->operator;
    }

    if ($operators[$operator]['values'] == 1) {
      // When the operator is either <, <=, =, !=, >=, > or regular_expression
      // the input contains only one value.
      if ($this->value['value'] == '') {
        return FALSE;
      }
    }
    elseif ($operators[$operator]['values'] == 2) {
      // When the operator is either between or not between the input contains
      // two values.
      if ($this->value['min'] == '' || $this->value['max'] == '') {
        return FALSE;
      }
    }

    return $rc;
  }

  protected function opBetween($field) {
    $a = intval(strtotime($this->value['min'], 0));
    $b = intval(strtotime($this->value['max'], 0));

    if ($this->value['type'] == 'offset') {
      // Keep sign.
      $a = '***CURRENT_TIME***' . sprintf('%+d', $a);
      // Keep sign.
      $b = '***CURRENT_TIME***' . sprintf('%+d', $b);
    }
    // This is safe because we are manually scrubbing the values.
    // It is necessary to do it this way because $a and $b are formulas when using an offset.
    $operator = strtoupper($this->operator);
    $this->query->addWhereExpression($this->options['group'], "$field $operator $a AND $b");
  }

  protected function opSimple($field) {
    $value = intval(strtotime($this->value['value'], 0));
    if (!empty($this->value['type']) && $this->value['type'] == 'offset') {
      // Keep sign.
      $value = '***CURRENT_TIME***' . sprintf('%+d', $value);
    }
    // This is safe because we are manually scrubbing the value.
    // It is necessary to do it this way because $value is a formula when using an offset.
    $this->query->addWhereExpression($this->options['group'], "$field $this->operator $value");
  }

  /**
   * Override parent method to remove the placeholder options.
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    $form['expose']['placeholder']['#access'] = FALSE;
    $form['expose']['min_placeholder']['#access'] = FALSE;
    $form['expose']['max_placeholder']['#access'] = FALSE;
  }

  /**
   * Override parent method to change input type.
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);

    // Change the appropriate form elements to a 'datetime' if the exposed
    // filter is configured for 'date' input.
    if ($this->value['type'] === 'date') {
      // What elements are visible and where they live in the form structure is
      // really complicated. Cases to consider:
      // - Operation is fixed and requires no elements (empty / not empty).
      // - Operation is fixed and requires a single element (=, !=, >=, etc).
      // - Operation is fixed and requires 2 elements (between / not between).
      // - Operation is exposed but limited to one or more of the above.
      // - Operation is exposed and unlimited (we have value, max and min).
      // Instead of trying to code for all of this separately, we see what form
      // elements we have and where they live, and set them to datetime.
      // Recursively search through the form element and any children, looking
      // for anything of type 'textfield', and convert it to 'datetime'.
      // @see \Drupal\views\Plugin\views\filter\NumericFilter::valueForm()
      $field_identifier = $this->options['expose']['identifier'];
      if (isset($form[$field_identifier])) {
        $this->convertTextElementToDatetime($form[$field_identifier]);
      }

      if (in_array($this->operator, ['between', 'not between'], TRUE)) {
        // Check the element input matches the form structure.
        $input = $form_state->getUserInput();
        if (isset($input[$field_identifier], $input[$field_identifier]['min']) && !is_array($input[$field_identifier]['min']) && $value = $input[$field_identifier]['min']) {
          $date = new DrupalDateTime($value);
          $input[$field_identifier]['min'] = [
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('H:i:s'),
          ];
        }
        if (isset($input[$field_identifier], $input[$field_identifier]['max']) && !is_array($input[$field_identifier]['max']) && $value = $input[$field_identifier]['max']) {
          $date = new DrupalDateTime($value);
          $input[$field_identifier]['max'] = [
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('H:i:s'),
          ];
        }
        $form_state->setUserInput($input);
      }
      else {
        // Check the element input matches the form structure.
        $input = $form_state->getUserInput();
        if (isset($input[$field_identifier]) && !is_array($input[$field_identifier]) && $value = $input[$field_identifier]) {
          $date = new DrupalDateTime($value);
          $input[$field_identifier] = [
            'date' => $date->format('Y-m-d'),
            'time' => $date->format('H:i:s'),
          ];
        }
        $form_state->setUserInput($input);
      }
    }
  }

  /**
   * Finds elements in the exposed form and converts from textfield to datetime.
   *
   * Recursively searches all children of the element to handle nested forms.
   *
   * @param array $element
   *   The form element to convert (if appropriate).
   */
  protected function convertTextElementToDatetime(&$element) {
    if (isset($element['#type']) && $element['#type'] === 'textfield') {
      $element['#type'] = 'datetime';
    }
    foreach (Element::children($element) as $child) {
      $this->convertTextElementToDatetime($element[$child]);
    }
  }

  /**
   * Override parent method to remove 'regular_expression' as an option.
   *
   * Since we're operating on date fields, and have a date (and maybe time)
   * picker as the widget (not a text field), a 'Regular expression' operation
   * makes no sense.
   */
  public function operators() {
    $operators = parent::operators();
    unset($operators['regular_expression']);
    return $operators;
  }

}
