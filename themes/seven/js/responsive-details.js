/**
* DO NOT EDIT THIS FILE.
* All changes should be applied to ./themes/seven/js/responsive-details.es6.js
* See the following change record for more information,
* https://www.drupal.org/node/2873849
* @preserve
**/

(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.responsiveDetails = {
    attach: function attach(context) {
      var $details = $(context).find('details').once('responsive-details');

      if (!$details.length) {
        return;
      }

      function detailsToggle(matches) {
        if (matches) {
          $details.attr('open', true);
          $summaries.attr('aria-expanded', true);
          $summaries.on('click.details-open', false);
        } else {
          var $notPressed = $details.find('> summary[aria-pressed!=true]').attr('aria-expanded', false);
          $notPressed.parent('details').attr('open', false);

          $summaries.off('.details-open');
        }
      }

      function handleDetailsMQ(event) {
        detailsToggle(event.matches);
      }

      var $summaries = $details.find('> summary');
      var mql = window.matchMedia('(min-width:48em)');
      mql.addListener(handleDetailsMQ);
      detailsToggle(mql.matches);
    }
  };
})(jQuery, Drupal);