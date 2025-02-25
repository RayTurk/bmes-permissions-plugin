<?php

/**
 * Plugin Name: Custom Roles and Permissions for Multisite
 * Description: Adds custom roles with a visual permissions manager for all ACF fields, including working tabs and enforced non-editable fields.
 * Version: 7.5
 * Author: FSM - Ray Turk
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

class CustomRolesPermissions
{
  private $custom_roles = ['fsm_restricted', 'corporate', 'franchisee', 'franchise_business_consultant'];

  public function __construct()
  {
    add_action('init', [$this, 'create_custom_user_roles']);
    add_action('admin_menu', [$this, 'restrict_admin_menu'], 999);
    add_action('admin_menu', [$this, 'add_settings_page']);
    add_action('admin_init', [$this, 'hide_admin_notifications']);
    add_filter('acf/load_field', [$this, 'enforce_acf_field_permissions']);
    add_filter('acf/update_value', [$this, 'prevent_unauthorized_acf_save'], 10, 3);
    add_filter('show_admin_bar', [$this, 'hide_admin_bar_for_fbc']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_custom_acf_js']);
    add_action('plugins_loaded', [$this, 'add_dashboard_capabilities']);
    add_action('admin_init', [$this, 'remove_add_new_page_for_franchisee']);

    register_activation_hook(__FILE__, [$this, 'plugin_activation']);
    add_action('plugins_loaded', [$this, 'check_version']);
  }

  public function is_admin_or_super_admin()
  {
    return current_user_can('administrator') || is_super_admin();
  }

  public function create_custom_user_roles()
  {
    $roles_capabilities = [
      'fsm_restricted' => get_role('administrator')->capabilities,
      'corporate' => array_merge(
        get_role('editor')->capabilities,
        $this->get_estate_sales_capabilities()
      ),
      'franchisee' => array_merge(
        get_role('editor')->capabilities,
        $this->get_estate_sales_capabilities(),
        $this->get_franchisee_page_capabilities()
      ),
      'franchise_business_consultant' => array_merge(
        get_role('editor')->capabilities,
        $this->get_estate_sales_capabilities(),
        $this->get_gravity_forms_capabilities()
      )
    ];

    foreach ($roles_capabilities as $role => $capabilities) {
      add_role($role, ucwords(str_replace('_', ' ', $role)), $capabilities);
    }
  }

  // New method to define Franchisee page capabilities
  private function get_franchisee_page_capabilities()
  {
    return [
      'edit_pages' => true,
      'edit_published_pages' => true,
      'publish_pages' => true,
      'delete_pages' => true,
      'delete_published_pages' => true,
      'edit_private_pages' => true,
      'read_private_pages' => true,
    ];
  }

  private function get_estate_sales_capabilities()
  {
    return [
      'read_estate-sale' => true,
      'read_private_estate-sales' => true,
      'edit_estate-sale' => true,
      'edit_estate-sales' => true,
      'edit_others_estate-sales' => true,
      'edit_published_estate-sales' => true,
      'publish_estate-sales' => true,
      'delete_estate-sales' => true,
      'delete_others_estate-sales' => true,
      'delete_private_estate-sales' => true,
      'delete_published_estate-sales' => true,
    ];
  }

  private function get_gravity_forms_capabilities()
  {
    return [
      'gravityforms_view_entries' => true,
      'gravityforms_edit_entries' => true,
      'gravityforms_delete_entries' => true,
      'gravityforms_edit_forms' => true,
      'gravityforms_delete_forms' => true,
      'gravityforms_create_form' => true,
      'gravityforms_view_settings' => true,
      'gravityforms_edit_settings' => true,
      'gravityforms_export_entries' => true,
      'gravityforms_view_updates' => true,
      'gravityforms_view_addons' => true,
      'gform_full_access' => true,
    ];
  }

  public function restrict_admin_menu()
  {
    if ($this->is_admin_or_super_admin()) return;

    global $menu, $submenu;

    if (current_user_can('franchise_business_consultant')) {
      $allowed_menus = [
        'index.php',
        'edit.php',
        'edit.php?post_type=estate-sale',
        'gf_edit_forms',
        'looker-studio-dashboard',
        'scribe-ai-dashboard',
      ];
      $this->filter_menu_items($menu, $allowed_menus);
      $this->filter_submenu_items($submenu, $allowed_menus);
    }

    if (current_user_can('franchisee') && count(wp_get_current_user()->roles) === 1) {
      $this->hide_specific_menu_items(['edit.php?post_type=popup', 'profile.php', 'tools.php']);
      $this->remove_profile_from_users_submenu();
      $this->remove_add_new_page_for_franchisee();
    }
  }

  private function filter_menu_items(&$menu, $allowed_menus)
  {
    $menu = array_filter($menu, function ($item) use ($allowed_menus) {
      return in_array($item[2], $allowed_menus);
    });
  }

  private function filter_submenu_items(&$submenu, $allowed_menus)
  {
    foreach ($submenu as $parent => $items) {
      if (!in_array($parent, $allowed_menus)) {
        unset($submenu[$parent]);
      }
    }
  }

  private function hide_specific_menu_items($items_to_hide)
  {
    foreach ($items_to_hide as $item) {
      remove_menu_page($item);
    }
  }

  private function remove_profile_from_users_submenu()
  {
    global $submenu;
    if (isset($submenu['users.php'])) {
      $submenu['users.php'] = array_filter($submenu['users.php'], function ($item) {
        return $item[2] !== 'profile.php';
      });
    }
  }

  public function add_settings_page()
  {
    add_options_page(
      'Custom Roles and Permissions',
      'Custom Roles and Permissions',
      'manage_options',
      'custom-roles-permissions',
      [$this, 'render_settings_page']
    );
  }

  public function render_settings_page()
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    if (isset($_POST['submit'])) {
      update_option('custom_roles_permissions', $_POST['permissions']);
      echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $permissions = get_option('custom_roles_permissions', []);
    $roles = ['fsm_restricted', 'corporate', 'franchisee'];
    $field_groups = $this->get_all_acf_fields();

    include plugin_dir_path(__FILE__) . 'templates/settings-page.php';
  }

  private function get_all_acf_fields()
  {
    $field_groups = [];
    if (function_exists('acf_get_field_groups')) {
      foreach (acf_get_field_groups() as $group) {
        $fields = acf_get_fields($group);
        if ($fields) {
          $field_groups[] = [
            'title' => $group['title'],
            'fields' => $fields
          ];
        }
      }
    }
    return $field_groups;
  }

  public function display_nested_fields($fields, $roles, $permissions, $group_index, $indent = 0)
  {
    $seen_fields = [];

    foreach ($fields as $field) {
      $field_key = $field['key'];

      if (in_array($field_key, $seen_fields)) {
        continue;
      }

      $seen_fields[] = $field_key;

      if ($field['type'] === 'group') {
        echo '<tr class="acf-group-header">';
        echo '<td colspan="' . (count($roles) + 1) . '" style="font-weight: bold;">' . esc_html($field['label']) . '</td>';
        echo '</tr>';

        if (!empty($field['sub_fields'])) {
          $this->display_nested_fields($field['sub_fields'], $roles, $permissions, $group_index, $indent + 1);
        }
      } elseif ($field['type'] === 'tab') {
        echo '<tr class="acf-tab-header">';
        echo '<td colspan="' . (count($roles) + 1) . '"><strong>' . esc_html($field['label']) . '</strong></td>';
        echo '</tr>';
      } else {
        echo '<tr' . ($indent > 0 ? ' class="nested-field"' : '') . '>';
        echo '<td>' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $indent) . esc_html($field['label']) . ' <small>(' . $field['type'] . ')</small></td>';
        foreach ($roles as $role) {
          echo '<td class="text-center">';
          echo '<input type="checkbox" name="permissions[' . $field_key . '][' . $role . ']" ';
          checked(isset($permissions[$field_key][$role]));
          echo '>';
          echo '</td>';
        }
        echo '</tr>';

        if (!empty($field['sub_fields'])) {
          $this->display_nested_fields($field['sub_fields'], $roles, $permissions, $group_index, $indent + 1);
        }

        if ($field['type'] == 'flexible_content' && !empty($field['layouts'])) {
          foreach ($field['layouts'] as $layout) {
            echo '<tr class="nested-field">';
            echo '<td>' . str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $indent + 1) . esc_html($layout['label']) . ' <small>(layout)</small></td>';
            echo str_repeat('<td></td>', count($roles));
            echo '</tr>';
            if (!empty($layout['sub_fields'])) {
              $this->display_nested_fields($layout['sub_fields'], $roles, $permissions, $group_index, $indent + 2);
            }
          }
        }
      }
    }
  }

  public function enforce_acf_field_permissions($field)
  {
    // Early return for special cases
    if (
      $field['name'] === 'iframe_embed_code' ||
      !is_admin() ||
      $this->is_admin_or_super_admin() ||
      current_user_can('franchise_business_consultant')
    ) {
      return $field;
    }

    $permissions = get_option('custom_roles_permissions', []);
    $user_roles = wp_get_current_user()->roles;

    // Check if the field has specific permissions set
    if (isset($permissions[$field['key']])) {
      $has_permission = false;
      foreach ($user_roles as $role) {
        if (isset($permissions[$field['key']][$role]) && $permissions[$field['key']][$role]) {
          $has_permission = true;
          break;
        }
      }

      // If user doesn't have permission, modify the field
      if (!$has_permission) {
        // Apply disabled class to the wrapper for targeting in JS
        $field['wrapper']['class'] .= ' acf-disabled-field';

        // Set both readonly and disabled properties
        $field['readonly'] = true;
        $field['disabled'] = true;

        // For image fields, disable the uploader
        if ($field['type'] === 'image') {
          // Force the uploader to be disabled
          add_filter('acf/prepare_field/key=' . $field['key'], function ($field) {
            $field['disabled'] = true;
            return $field;
          });
        }

        // For repeater fields, disable the add/remove buttons
        if ($field['type'] === 'repeater') {
          add_filter('acf/prepare_field/key=' . $field['key'], function ($field) {
            $field['disabled'] = true;
            // Prevent adding new rows
            $field['max'] = $field['min'] = count($field['value'] ?? []);
            return $field;
          });
        }

        // For wysiwyg fields, ensure they're properly disabled
        if ($field['type'] === 'wysiwyg') {
          add_filter('acf/prepare_field/key=' . $field['key'], function ($field) {
            $field['readonly'] = true;
            $field['disabled'] = true;
            $field['toolbar'] = 'basic'; // Simplify toolbar for read-only
            return $field;
          });
        }

        // For link/button fields, ensure they're properly disabled
        if ($field['type'] === 'link' || $field['type'] === 'button') {
          add_filter('acf/prepare_field/key=' . $field['key'], function ($field) {
            $field['readonly'] = true;
            $field['disabled'] = true;

            // Add additional class for targeting in JS
            $field['wrapper']['class'] .= ' link-button-disabled';

            // Disable the link picker by removing edit functionality
            add_filter('acf/prepare_field/type=link', function ($prepared_field) use ($field) {
              if ($prepared_field['key'] === $field['key']) {
                $prepared_field['readonly'] = true;
                $prepared_field['disabled'] = true;
              }
              return $prepared_field;
            });

            return $field;
          });
        }

        // Add a note about the field being read-only
        $field['instructions'] .= ' <span style="color:#d63638">You do not have permission to edit this field.</span>';
      }
    }

    return $field;
  }

  public function prevent_unauthorized_acf_save($value, $post_id, $field)
  {
    if (!is_admin() || $this->is_admin_or_super_admin() || current_user_can('franchise_business_consultant')) {
      return $value;
    }

    $permissions = get_option('custom_roles_permissions', []);
    $user_roles = wp_get_current_user()->roles;

    // Check if the field itself has specific permissions
    if (isset($permissions[$field['key']])) {
      $has_permission = false;
      foreach ($user_roles as $role) {
        if (isset($permissions[$field['key']][$role]) && $permissions[$field['key']][$role]) {
          $has_permission = true;
          break;
        }
      }

      if (!$has_permission) {
        // If user doesn't have permission, return the existing value
        $current_value = get_field($field['name'], $post_id);
        return ($current_value !== null && $current_value !== false) ? $current_value : $value;
      }
    }

    // Special handling for repeater fields
    if ($field['type'] === 'repeater' && !empty($value) && is_array($value)) {
      $existing_value = get_field($field['name'], $post_id);

      // If we have sub fields and permissions set for them
      if (!empty($field['sub_fields'])) {
        foreach ($field['sub_fields'] as $sub_field) {
          // Check if permissions exist for this sub field
          if (isset($permissions[$sub_field['key']])) {
            $has_sub_permission = false;
            foreach ($user_roles as $role) {
              if (isset($permissions[$sub_field['key']][$role]) && $permissions[$sub_field['key']][$role]) {
                $has_sub_permission = true;
                break;
              }
            }

            // If user doesn't have permission for this sub field
            if (!$has_sub_permission && is_array($existing_value)) {
              // For each row in the repeater
              foreach ($value as $row_index => $row) {
                // If we have an existing value for this row
                if (isset($existing_value[$row_index][$sub_field['name']])) {
                  // Keep the original value for this field
                  $value[$row_index][$sub_field['name']] = $existing_value[$row_index][$sub_field['name']];
                }
              }
            }
          }
        }
      }
    }

    // If we get here, either the field has no specific permissions set,
    // or the user has permission to edit it
    return $value;
  }

  public function hide_admin_notifications()
  {
    if (!$this->is_admin_or_super_admin()) {
      remove_action('admin_notices', 'update_nag', 3);
      remove_action('admin_notices', 'maintenance_nag', 10);
      remove_all_actions('after_plugin_row');
      remove_all_actions('after_theme_row');
      remove_action('admin_notices', '_maybe_update_core');
      remove_action('admin_notices', 'update_nag', 3);
      add_filter('update_footer', '__return_empty_string', 11);
      add_action('admin_menu', function () {
        remove_submenu_page('index.php', 'about.php');
      });
      add_action('wp_dashboard_setup', function () {
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
      });
    }
  }

  public function hide_admin_bar_for_fbc($show)
  {
    return current_user_can('franchise_business_consultant') ? false : $show;
  }

  public function enqueue_custom_acf_js()
  {
    wp_enqueue_script('custom-acf-disable', plugin_dir_url(__FILE__) . 'assets/js/custom-acf-disable.js', ['jquery', 'acf-input'], '1.1', true);
  }

  public function add_dashboard_capabilities()
  {
    foreach ($this->custom_roles as $role_name) {
      $role = get_role($role_name);
      if ($role) {
        $role->add_cap('read_looker_studio_dashboard');
        $role->add_cap('read_help_documents_dashboard');
      }
    }
  }


  public function remove_add_new_page_for_franchisee()
  {
    if (current_user_can('franchisee') && !$this->is_admin_or_super_admin()) {
      // Remove the "Add New" submenu
      remove_submenu_page('edit.php?post_type=page', 'post-new.php?post_type=page');

      // Hide "Add New" button and remove row actions
      add_action('admin_head', function () {
        echo '<style>
                  body.post-type-page .page-title-action { display:none; }
                  body.post-type-page .trash,
                  body.post-type-page .duplicate { display:none !important; }
              </style>';
      });

      // Remove trash and duplicate capabilities
      add_filter('map_meta_cap', function ($caps, $cap, $user_id, $args) {
        if (in_array($cap, ['delete_page', 'delete_post'])) {
          return ['do_not_allow'];
        }
        return $caps;
      }, 10, 4);

      // Disable duplicate post plugin functionality for pages if it's active
      add_filter('duplicate_post_enabled_post_types', function ($post_types) {
        if (($key = array_search('page', $post_types)) !== false) {
          unset($post_types[$key]);
        }
        return $post_types;
      });
    }
  }

  public function plugin_activation()
  {
    $this->create_custom_user_roles();
    $this->add_dashboard_capabilities();
  }

  public function check_version()
  {
    if (get_option('custom_roles_version') != '7.5') {
      $this->create_custom_user_roles();
      $this->add_dashboard_capabilities();
      update_option('custom_roles_version', '7.5');
    }
  }
}

new CustomRolesPermissions();
