<?php
defined('ABSPATH') || exit;

// Add "View Documents" link to user row actions for admins
add_filter('user_row_actions', 'cdm_add_user_docs_link', 10, 2);
function cdm_add_user_docs_link($actions, $user)
{
    if (!current_user_can('manage_options')) return $actions;

    $url = add_query_arg([
        'page' => 'cdm-view-user-docs',
        'user_id' => $user->ID
    ], admin_url('users.php'));

    $actions['view_docs'] = '<a href="' . esc_url($url) . '">' . esc_html(__('View Documents', 'document-manager')) . '</a>';
    return $actions;
}


// Adds page but does not display a menu item
add_action('admin_menu', function () {
    add_submenu_page(
        null, // No parent slug, so it won't appear in any menu
        __('Client Documents', 'document-manager'),
        __('Client Documents', 'document-manager'),
        'manage_options',
        'cdm-view-user-docs',
        'cdm_render_user_docs_page'
    );
});


function cdm_render_user_docs_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Access denied.', 'document-manager'));
    }

    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$user_id) {
        echo '<div class="wrap"><h1>' . esc_html(__('No user selected.', 'document-manager')) . '</h1></div>';
        return;
    }

    $user_info = get_userdata($user_id);
    if (!$user_info) {
        echo '<div class="wrap"><h1>' . esc_html(__('User not found.', 'document-manager')) . '</h1></div>';
        return;
    }

    $username = sanitize_file_name($user_info->user_login);
    $user_dir = trailingslashit(CDM_UPLOAD_ROOT) . $username;
    $meta_file = $user_dir . '/file-metadata.json';

    // Handle individual file deletion
    if (isset($_POST['cdm_admin_delete_file']) && isset($_POST['cdm_admin_delete_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cdm_admin_delete_nonce'])), 'cdm_admin_delete_file')) {
        $file_to_delete = basename(sanitize_file_name(wp_unslash($_POST['cdm_admin_delete_file'])));
        $file_path = $user_dir . '/' . $file_to_delete;

        if (file_exists($file_path)) {
            wp_delete_file($file_path);
            $metadata = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
            if (isset($metadata[$file_to_delete])) {
                unset($metadata[$file_to_delete]);
                file_put_contents($meta_file, json_encode($metadata));
            }
            echo '<div class="updated"><p>' . esc_html(__('File deleted successfully.', 'document-manager')) . '</p></div>';
        }
    }

    // Handle bulk deletion
    if (isset($_POST['cdm_bulk_delete']) && isset($_POST['cdm_files']) && is_array($_POST['cdm_files']) && isset($_POST['cdm_bulk_delete_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cdm_bulk_delete_nonce'])), 'cdm_bulk_delete')) {
        $metadata = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
        $deleted_count = 0;

        foreach (array_map('sanitize_file_name', wp_unslash($_POST['cdm_files'])) as $file) {
            $file = basename($file);
            $file_path = $user_dir . '/' . $file;

            if (file_exists($file_path)) {
                wp_delete_file($file_path);
                if (isset($metadata[$file])) {
                    unset($metadata[$file]);
                }
                $deleted_count++;
            }
        }

        if ($deleted_count > 0) {
            file_put_contents($meta_file, json_encode($metadata));
            /* translators: %d is the number of files deleted */
            echo '<div class="updated"><p>' . esc_html(sprintf(__('%d file(s) deleted successfully.', 'document-manager'), $deleted_count)) . '</p></div>';
        }
    }

    // Handle admin file upload
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES array validated via cdm_validate_upload() which checks type, size, and MIME
    if (isset($_POST['cdm_admin_upload']) && isset($_FILES['cdm_admin_file']) && isset($_POST['cdm_admin_upload_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cdm_admin_upload_nonce'])), 'cdm_admin_upload_file')) {
        if (!empty($_FILES['cdm_admin_file']['name']) && isset($_FILES['cdm_admin_file']['tmp_name'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES validated via cdm_validate_upload()
            $rate_key     = 'cdm_uploads_' . get_current_user_id() . '_' . floor(time() / 3600);
            $upload_count = (int) get_transient($rate_key);
            if ($upload_count >= CDM_MAX_UPLOADS_PER_HOUR) {
                echo '<div class="error"><p>' . esc_html(__('Upload rate limit exceeded. Please wait before uploading more files.', 'document-manager')) . '</p></div>';
            } else {
                // Validate file
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES validated via cdm_validate_upload() which checks type, size, and MIME
                $validation = cdm_validate_upload($_FILES['cdm_admin_file']);
                if (!$validation['valid']) {
                    echo '<div class="error"><p>' . esc_html($validation['error']) . '</p></div>';
                } else {
                    if (!file_exists($user_dir)) {
                        wp_mkdir_p($user_dir);
                        cdm_protect_upload_dir(CDM_UPLOAD_ROOT);
                    }

                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- filename sanitized via basename() on same line
                    $filename  = basename($_FILES['cdm_admin_file']['name']);
                    $timestamp = time();
                    $safe_name = $timestamp . "-" . $filename;
                    $target    = $user_dir . "/" . $safe_name;

                    // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- move_uploaded_file required for custom path outside web root; tmp_name is a server-generated path, not user input
                    if (move_uploaded_file($_FILES['cdm_admin_file']['tmp_name'], $target)) {
                        set_transient($rate_key, $upload_count + 1, HOUR_IN_SECONDS);
                        $metadata             = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
                        $metadata[$safe_name] = [
                            'timestamp'   => $timestamp,
                            'uploaded_by' => 'admin',
                        ];
                        file_put_contents($meta_file, json_encode($metadata));
                        echo '<div class="updated"><p>' . esc_html(__('File uploaded successfully.', 'document-manager')) . '</p></div>';
                    } else {
                        echo '<div class="error"><p>' . esc_html(__('Error uploading file. Please check folder permissions.', 'document-manager')) . '</p></div>';
                    }
                }
            }
        }
    }

    /* translators: %s is the client's username */
    echo '<div class="wrap"><h1>' . esc_html(sprintf(__('Documents for %s', 'document-manager'), $user_info->user_login)) . '</h1>';

    // Admin upload form
    echo '<div style="background:#fff;padding:15px;margin:20px 0;border:1px solid #ddd;border-radius:4px;">';
    echo '<h2>' . esc_html(__('Upload Document for Client', 'document-manager')) . '</h2>';
    echo '<form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;">';
    wp_nonce_field('cdm_admin_upload_file', 'cdm_admin_upload_nonce');
    echo '<input type="file" name="cdm_admin_file" required>';
    echo '<button type="submit" name="cdm_admin_upload" class="button button-primary">' . esc_html(__('Upload File', 'document-manager')) . '</button>';
    echo '</form></div>';

    if (!file_exists($user_dir)) {
        echo '<p>' . esc_html(__('No documents found.', 'document-manager')) . '</p></div>';
        return;
    }

    $metadata = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    if (empty($metadata)) {
        echo '<p>' . esc_html(__('No documents found.', 'document-manager')) . '</p></div>';
        return;
    }

    echo '<form method="post">';
    wp_nonce_field('cdm_bulk_delete', 'cdm_bulk_delete_nonce');
    echo '<p><button type="submit" name="cdm_bulk_delete" class="button button-secondary" onclick="return confirm(\'' . esc_js(__('Delete selected files?', 'document-manager')) . '\');">' . esc_html(__('Delete Selected', 'document-manager')) . '</button></p>';
    echo '<table class="widefat"><thead><tr><th style="width:30px;"><input type="checkbox" id="cdm_select_all"></th><th>' . esc_html(__('File', 'document-manager')) . '</th><th>' . esc_html(__('Date', 'document-manager')) . '</th><th>' . esc_html(__('Uploaded By', 'document-manager')) . '</th><th>' . esc_html(__('Delete', 'document-manager')) . '</th></tr></thead><tbody>';

    foreach ($metadata as $file => $file_data) {
        // Handle both old format (timestamp only) and new format (array with timestamp and uploaded_by)
        $timestamp = is_array($file_data) ? $file_data['timestamp'] : $file_data;
        $uploaded_by = is_array($file_data) && isset($file_data['uploaded_by']) ? $file_data['uploaded_by'] : 'client';

        $download_url = wp_nonce_url(
            add_query_arg([
                'cdm_admin_download' => $file,
                'user_id'            => $user_id,
            ], admin_url('users.php')),
            'cdm_admin_dl_' . $file
        );

        echo '<tr>';
        echo '<td><input type="checkbox" name="cdm_files[]" value="' . esc_attr($file) . '" class="cdm_file_checkbox"></td>';
        echo '<td><a href="' . esc_url($download_url) . '">' . esc_html($file) . '</a></td>';
        echo '<td>' . esc_html(gmdate('M j Y', $timestamp)) . '</td>';
        echo '<td>' . esc_html(ucfirst($uploaded_by)) . '</td>';
        echo '<td><form method="post" style="display:inline;">';
        wp_nonce_field('cdm_admin_delete_file', 'cdm_admin_delete_nonce');
        echo '<input type="hidden" name="cdm_admin_delete_file" value="' . esc_attr($file) . '">';
        echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'' . esc_js(__('Delete this file?', 'document-manager')) . '\');" style="color:#b32d2e;">&times;</button>';
        echo '</form></td>';
        echo '</tr>';
    }

    echo '</tbody></table></form>';

    // Add JavaScript for "Select All" functionality
    echo '<script>
    document.getElementById("cdm_select_all").addEventListener("change", function() {
        var checkboxes = document.querySelectorAll(".cdm_file_checkbox");
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = this.checked;
        }, this);
    });
    </script>';

    echo '</div>';
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below after filename is extracted
    if (empty($_GET['cdm_admin_download']) || empty($_GET['user_id'])) return;

    $filename = basename(sanitize_file_name(wp_unslash($_GET['cdm_admin_download'])));

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'cdm_admin_dl_' . $filename)) {
        wp_die(esc_html__('Security check failed.', 'document-manager'));
    }

    $user_id   = intval($_GET['user_id']);
    $user_info = get_userdata($user_id);

    if (!$user_info) {
        wp_die(esc_html__('User not found.', 'document-manager'));
    }

    $file = trailingslashit(CDM_UPLOAD_ROOT) . sanitize_file_name($user_info->user_login) . "/$filename";

    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($file));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streaming large files; WP_Filesystem::get_contents() loads entire file into memory
        readfile($file);
        exit;
    } else {
        wp_die(esc_html__('File not found.', 'document-manager'));
    }
});
