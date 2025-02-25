// assets/js/custom-acf-disable.js
jQuery(document).ready(function ($) {
  // Helper function to disable fields and hide edit icons
  function disableRestrictedFields() {
    // Add disabled class to fields without permission
    $(".acf-disabled-field").each(function () {
      // Get all inputs, textareas, and selects within the field
      var $inputs = $(this).find("input, textarea, select");

      // Disable all input elements
      $inputs.prop("disabled", true);

      // For text inputs and textareas, also make them readonly to ensure they can't be modified
      $(this).find("input[type='text'], input[type='number'], input[type='email'], input[type='password'], input[type='url'], input[type='tel'], textarea")
        .prop("readonly", true)
        .css({
          "background-color": "#f5f5f5",
          "cursor": "not-allowed",
          "user-select": "none",
          "color": "#777"
        })
        .on("click focus mousedown mouseup keydown keypress input", function (e) {
          e.preventDefault();
          e.stopPropagation();
          $(this).blur();
          return false;
        });

      // Hide image field edit/delete icons
      $(this).find(".acf-image-uploader .acf-actions, .acf-image-uploader .acf-icon").hide();

      // Style image field to show it's disabled
      $(this).find(".acf-image-uploader").css("opacity", "0.7");

      // Handle fields within repeaters
      $(this).find(".acf-repeater .acf-row-handle .acf-icon").hide();
      $(this).find(".acf-repeater .acf-actions").hide();

      // Add special handling for WYSIWYG fields
      $(this).find(".wp-editor-wrap").addClass("disabled-editor");
      $(this).find(".wp-editor-area").prop("readonly", true);
      $(this).find(".wp-editor-container").css("pointer-events", "none");

      // Handle tinyMCE if it's loaded
      if (typeof tinymce !== 'undefined') {
        $(this).find('.wp-editor-area').each(function () {
          var editorID = $(this).attr('id');
          if (editorID && tinymce.get(editorID)) {
            tinymce.get(editorID).setMode('readonly');
          }
        });
      }

      // Handle link/button fields
      $(this).find(".acf-link, .acf-button").each(function () {
        // Remove click events and add disabled appearance
        $(this).find("a, button, .acf-button-edit, .acf-link-edit, .acf-icon").off().css({
          "pointer-events": "none",
          "opacity": "0.7",
          "cursor": "default"
        }).removeAttr("href");

        // Hide all edit/remove buttons
        $(this).find(".acf-actions, .acf-link-edit, .acf-button-edit, .acf-icon.-pencil, .acf-icon.-cancel").hide();

        // Add a disabled class to reinforce the styling
        $(this).addClass("field-disabled");
      });
    });

    // Handle nested fields within repeaters
    $(".acf-repeater .acf-row").each(function () {
      // Find disabled fields within repeaters
      $(this).find(".acf-disabled-field").each(function () {
        // Handle all input types
        var $inputs = $(this).find("input, textarea, select");
        $inputs.prop("disabled", true);

        // Handle text inputs specifically
        $(this).find("input[type='text'], input[type='number'], input[type='email'], input[type='password'], input[type='url'], input[type='tel'], textarea")
          .prop("readonly", true)
          .css({
            "background-color": "#f5f5f5",
            "cursor": "not-allowed",
            "color": "#777"
          })
          .on("click focus mousedown mouseup keydown keypress input", function (e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).blur();
            return false;
          });

        // Hide action buttons
        $(this).find(".acf-actions, .acf-icon").hide();
      });

      // Handle link/button fields inside repeaters
      $(this).find(".acf-disabled-field .acf-link, .acf-disabled-field .acf-button").each(function () {
        $(this).find("a, button, .acf-button-edit, .acf-link-edit, .acf-icon").off().css({
          "pointer-events": "none",
          "opacity": "0.7",
          "cursor": "default"
        }).removeAttr("href");
        $(this).find(".acf-actions, .acf-link-edit, .acf-button-edit, .acf-icon.-pencil, .acf-icon.-cancel").hide();
        $(this).addClass("field-disabled");
      });
    });
  }

  // Initial field disabling
  disableRestrictedFields();

  // Handle tabs and delayed loading
  $(".acf-tab-button").click(function () {
    setTimeout(disableRestrictedFields, 100);
  });

  // Add special handling for dynamically loaded fields or AJAX content
  $(document).on('acf/setup_fields', function () {
    disableRestrictedFields();
  });

  // Run after DOM changes
  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.addedNodes.length) {
        setTimeout(disableRestrictedFields, 100);
      }
    });
  });

  // Observe changes to ACF fields container
  const acfFields = document.querySelector('.acf-fields');
  if (acfFields) {
    observer.observe(acfFields, { childList: true, subtree: true });
  }

  // Add custom CSS to further reinforce disabled state
  $("<style>")
    .prop("type", "text/css")
    .html(`
      .acf-disabled-field .acf-input {
        opacity: 0.85;
        pointer-events: none;
      }
      .acf-disabled-field input,
      .acf-disabled-field textarea,
      .acf-disabled-field select {
        border-color: #ddd !important;
        color: #777 !important;
        cursor: not-allowed !important;
      }
      .acf-disabled-field .wp-editor-container {
        border-color: #ddd !important;
        pointer-events: none;
      }
      .disabled-editor .wp-editor-tools {
        opacity: 0.5;
        pointer-events: none;
      }
      .acf-repeater .acf-disabled-field .acf-row-handle .acf-icon,
      .acf-repeater .acf-disabled-field .acf-actions {
        display: none !important;
      }
      .acf-disabled-field .acf-link .link-title,
      .acf-disabled-field .acf-button-input {
        color: #777 !important;
        border-color: #ddd !important;
        cursor: default !important;
        pointer-events: none !important;
      }
      .acf-disabled-field .acf-link-wrap,
      .acf-disabled-field .acf-button-group {
        border-color: #ddd !important;
      }
      .acf-disabled-field .acf-link .acf-icon,
      .acf-disabled-field .acf-button .acf-icon {
        display: none !important;
      }
      .field-disabled .acf-button-edit,
      .field-disabled .acf-link-edit,
      .field-disabled svg {
        display: none !important;
      }
    `)
    .appendTo("head");
});