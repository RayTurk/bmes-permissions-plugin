<?php
// templates/settings-page.php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap custom-roles-permissions">
    <h1>Custom Roles and Permissions</h1>

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper">
        <?php foreach ($field_groups as $group_index => $group): ?>
            <a href="#tab-<?php echo esc_attr($group_index); ?>"
               class="nav-tab <?php echo $group_index === 0 ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($group['title']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="">
        <?php wp_nonce_field('custom_roles_permissions_nonce', 'custom_roles_permissions'); ?>

        <?php foreach ($field_groups as $group_index => $group): ?>
            <div id="tab-<?php echo esc_attr($group_index); ?>"
                 class="tab-content <?php echo $group_index === 0 ? 'active' : ''; ?>">

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="field-column">Field</th>
                            <?php foreach ($roles as $role): ?>
                                <th class="role-column">
                                    <div class="role-header">
                                        <label class="selectall-label">
                                            <input type="checkbox"
                                                   class="role-select-all"
                                                   data-role="<?php echo esc_attr($role); ?>"
                                                   data-group="<?php echo esc_attr($group_index); ?>">
                                            <?php echo esc_html(ucwords(str_replace('_', ' ', $role))); ?>
                                        </label>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $this->display_nested_fields($group['fields'], $roles, $permissions, $group_index); ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <?php submit_button('Save Permissions'); ?>
    </form>
</div>

<style>
.custom-roles-permissions .tab-content {
    display: none;
    padding: 20px 0;
}

.custom-roles-permissions .tab-content.active {
    display: block;
}

.custom-roles-permissions .field-column {
    width: 30%;
}

.custom-roles-permissions .role-column {
    width: <?php echo (70 / count($roles)); ?>%;
    text-align: center;
}

.custom-roles-permissions .role-header {
    text-align: center;
    padding: 8px 0;
}

.custom-roles-permissions .selectall-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
}

.custom-roles-permissions td {
    vertical-align: middle;
}

.custom-roles-permissions .acf-group-header {
    background-color: #f0f0f1;
}

.custom-roles-permissions .acf-tab-header {
    background-color: #f9f9f9;
}

.custom-roles-permissions input[type="checkbox"] {
    margin: 0;
}

.custom-roles-permissions .nav-tab-wrapper {
    margin-bottom: 20px;
}

.custom-roles-permissions .nested-field {
    padding-left: 20px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab functionality
    $('.nav-tab').click(function(e) {
        e.preventDefault();

        // Update active tab
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show corresponding content
        $('.tab-content').removeClass('active');
        $($(this).attr('href')).addClass('active');
    });

    // Role select all functionality
    $('.role-select-all').change(function() {
        var role = $(this).data('role');
        var group = $(this).data('group');
        var isChecked = $(this).prop('checked');

        // Select all checkboxes for this role in the current group
        $('#tab-' + group + ' input[type="checkbox"][name*="[' + role + ']"]').prop('checked', isChecked);
    });

    // Update "select all" checkbox state based on individual checkboxes
    function updateSelectAllState(role, group) {
        var total = $('#tab-' + group + ' input[type="checkbox"][name*="[' + role + ']"]').length;
        var checked = $('#tab-' + group + ' input[type="checkbox"][name*="[' + role + ']"]:checked').length;

        var selectAllCheckbox = $('.role-select-all[data-role="' + role + '"][data-group="' + group + '"]');
        selectAllCheckbox.prop('checked', total === checked);
        selectAllCheckbox.prop('indeterminate', checked > 0 && checked < total);
    }

    // Update select all state when individual checkboxes change
    $('input[type="checkbox"]').not('.role-select-all').change(function() {
        var roleMatch = $(this).attr('name').match(/\[(.*?)\]$/);
        if (roleMatch) {
            var role = roleMatch[1];
            var group = $(this).closest('.tab-content').attr('id').replace('tab-', '');
            updateSelectAllState(role, group);
        }
    });

    // Initial state of select all checkboxes
    $('.tab-content').each(function() {
        var group = $(this).attr('id').replace('tab-', '');
        $('.role-select-all[data-group="' + group + '"]').each(function() {
            updateSelectAllState($(this).data('role'), group);
        });
    });

    // Save active tab
    var activeTab = localStorage.getItem('activePermissionsTab');
    if (activeTab) {
        $('.nav-tab[href="' + activeTab + '"]').click();
    }

    $('.nav-tab').click(function() {
        localStorage.setItem('activePermissionsTab', $(this).attr('href'));
    });
});
</script>