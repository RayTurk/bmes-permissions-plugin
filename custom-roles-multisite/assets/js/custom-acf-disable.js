// assets/js/custom-acf-disable.js
jQuery(document).ready(function ($) {
  // Add disabled class to fields without permission
  $(".acf-disabled-field").each(function () {
    $(this).find("input, textarea, select").prop("disabled", true);
  });

  // Handle tabs
  $(".acf-tab-button").click(function () {
    setTimeout(function () {
      $(".acf-disabled-field").each(function () {
        $(this).find("input, textarea, select").prop("disabled", true);
      });
    }, 100);
  });
});
