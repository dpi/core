/**
* DO NOT EDIT THIS FILE.
* All changes should be applied to ./modules/content_translation/content_translation.admin.es6.js
* See the following change record for more information,
* https://www.drupal.org/node/2873849
* @preserve
**/

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.contentTranslationDependentOptions = {
    attach: function attach(context) {
      var $context = $(context);
      var options = drupalSettings.contentTranslationDependentOptions;
      var $fields;
      var dependent_columns;

      function fieldsChangeHandler($fields, dependent_columns) {
        return function (e) {
          Drupal.behaviors.contentTranslationDependentOptions.check($fields, dependent_columns, $(e.target));
        };
      }

      if (options && options.dependent_selectors) {
        for (var field in options.dependent_selectors) {
          if (options.dependent_selectors.hasOwnProperty(field)) {
            $fields = $context.find('input[name^="' + field + '"]');
            dependent_columns = options.dependent_selectors[field];

            $fields.on('change', fieldsChangeHandler($fields, dependent_columns));
            Drupal.behaviors.contentTranslationDependentOptions.check($fields, dependent_columns);
          }
        }
      }
    },
    check: function check($fields, dependent_columns, $changed) {
      var $element = $changed;
      var column;

      function filterFieldsList(index, field) {
        return $(field).val() === column;
      }

      for (var index in dependent_columns) {
        if (dependent_columns.hasOwnProperty(index)) {
          column = dependent_columns[index];

          if (!$changed) {
            $element = $fields.filter(filterFieldsList);
          }

          if ($element.is('input[value="' + column + '"]:checked')) {
            $fields.prop('checked', true).not($element).prop('disabled', true);
          } else {
            $fields.prop('disabled', false);
          }
        }
      }
    }
  };

  Drupal.behaviors.contentTranslation = {
    attach: function attach(context) {
      $(context).find('table .bundle-settings .translatable :input').once('translation-entity-admin-hide').each(function () {
        var $input = $(this);
        var $bundleSettings = $input.closest('.bundle-settings');
        if (!$input.is(':checked')) {
          $bundleSettings.nextUntil('.bundle-settings').hide();
        } else {
          $bundleSettings.nextUntil('.bundle-settings', '.field-settings').find('.translatable :input:not(:checked)').closest('.field-settings').nextUntil(':not(.column-settings)').hide();
        }
      });

      $('body').once('translation-entity-admin-bind').on('click', 'table .bundle-settings .translatable :input', function (e) {
        var $target = $(e.target);
        var $bundleSettings = $target.closest('.bundle-settings');
        var $settings = $bundleSettings.nextUntil('.bundle-settings');
        var $fieldSettings = $settings.filter('.field-settings');
        if ($target.is(':checked')) {
          $bundleSettings.find('.operations :input[name$="[language_alterable]"]').prop('checked', true);
          $fieldSettings.find('.translatable :input').prop('checked', true);
          $settings.show();
        } else {
          $settings.hide();
        }
      }).on('click', 'table .field-settings .translatable :input', function (e) {
        var $target = $(e.target);
        var $fieldSettings = $target.closest('.field-settings');
        var $columnSettings = $fieldSettings.nextUntil('.field-settings, .bundle-settings');
        if ($target.is(':checked')) {
          $columnSettings.show();
        } else {
          $columnSettings.hide();
        }
      });
    }
  };
})(jQuery, Drupal, drupalSettings);