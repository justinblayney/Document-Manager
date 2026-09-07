<?php
/**
 * Darkstar File Manager
 *
 * Admin document management with nested folder support.
 */

defined('ABSPATH') || exit;


/**
 * Enqueue admin JS.
 */
add_action('admin_enqueue_scripts', function ($hook_suffix) {

    if ('admin_page_dsfm-view-user-docs' !== $hook_suffix) {
        return;
    }

    wp_enqueue_script(
        'dsfm-admin',
        plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
        [],
        '1.1.0',
        true
    );
});


/**
 * Add "View Documents" link to user row actions.
 */
add_filter(
    'user_row_actions',
    'dsfm_add_user_docs_link',
    10,
    2
);


function dsfm_add_user_docs_link($actions, $user)
{
    if (!current_user_can('manage_options')) {
        return $actions;
    }

    $url = add_query_arg(
        [
            'page'    => 'dsfm-view-user-docs',
            'user_id' => $user->ID,
        ],
        admin_url('users.php')
    );

    $actions['view_docs'] =
        '<a href="' .
        esc_url($url) .
        '">' .
        esc_html__(
            'View Documents',
            'darkstar-file-manager'
        ) .
        '</a>';

    return $actions;
}


/**
 * Register hidden admin page.
 */
add_action('admin_menu', function () {

    add_submenu_page(
        'users.php',
        __('User Documents', 'darkstar-file-manager'),
        __('User Documents', 'darkstar-file-manager'),
        'manage_options',
        'dsfm-view-user-docs',
        'dsfm_render_user_docs_page'
    );
});


/**
 * Hide submenu.
 */
add_action('admin_head', function () {

    echo '<style>
        #adminmenu a[href="users.php?page=dsfm-view-user-docs"] {
            display:none !important;
        }
    </style>';
});


/**
 * Render admin document page.
 */
function dsfm_render_user_docs_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(
            esc_html__(
                'Access denied.',
                'darkstar-file-manager'
            )
        );
    }


    $user_id =
        isset($_GET['user_id'])
            ? absint($_GET['user_id'])
            : 0;


    if (!$user_id) {

        echo '<div class="wrap">';

        echo '<h1>' .
            esc_html__(
                'No user selected.',
                'darkstar-file-manager'
            ) .
            '</h1>';

        echo '</div>';

        return;
    }


    $user_info = get_userdata($user_id);


    if (!$user_info) {

        echo '<div class="wrap">';

        echo '<h1>' .
            esc_html__(
                'User not found.',
                'darkstar-file-manager'
            ) .
            '</h1>';

        echo '</div>';

        return;
    }


    $user_root = dsfm_get_user_root($user_info);


    if ($user_root === '') {
        wp_die(
            esc_html__(
                'User directory not found.',
                'darkstar-file-manager'
            )
        );
    }


    /*
     * Create root if necessary.
     */
    if (!file_exists($user_root)) {

        wp_mkdir_p($user_root);

        dsfm_protect_upload_dir(DSFM_UPLOAD_ROOT);
        dsfm_protect_upload_dir($user_root);
    }


    /*
     * Current directory.
     */
    $relative_dir = '';

    if (isset($_GET['dsfm_dir'])) {

        $relative_dir =
            dsfm_normalize_relative_path(
                wp_unslash($_GET['dsfm_dir'])
            );
    }


    $current_dir =
        dsfm_resolve_user_directory(
            $user_root,
            $relative_dir
        );


    /*
     * Prevent navigation outside user root.
     */
    if (!$current_dir) {

        $relative_dir = '';
        $current_dir  = $user_root;
    }


    $meta_file =
        trailingslashit($user_root) .
        'file-metadata.json';


    $metadata =
        dsfm_get_metadata($user_root);


    /*
     * ---------------------------------------------------------
     * Create folder
     * ---------------------------------------------------------
     */

    if (
        isset($_POST['dsfm_admin_create_folder'])
    ) {

        if (
            !isset($_POST['dsfm_admin_folder_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['dsfm_admin_folder_nonce']
                    )
                ),
                'dsfm_admin_create_folder'
            )
        ) {

            echo '<div class="notice notice-error"><p>' .
                esc_html__(
                    'Security check failed.',
                    'darkstar-file-manager'
                ) .
                '</p></div>';

        } else {

            $folder_name =
                isset($_POST['dsfm_folder_name'])
                    ? sanitize_text_field(
                        wp_unslash(
                            $_POST['dsfm_folder_name']
                        )
                    )
                    : '';


            $result =
                dsfm_create_user_directory(
                    $user_root,
                    $relative_dir,
                    $folder_name
                );


if ($result['success']) {

    /*
     * Redirect after successful creation.
     *
     * This prevents the POST request from being processed twice
     * and forces WordPress to reload the directory listing.
     */
    wp_safe_redirect(
        dsfm_admin_directory_url(
            $user_id,
            $relative_dir
        )
    );

    exit;

} else {

                echo '<div class="notice notice-error"><p>' .
                    esc_html($result['error']) .
                    '</p></div>';
            }
        }
    }


    /*
     * ---------------------------------------------------------
     * Delete folder
     * ---------------------------------------------------------
     */

    if (
        isset($_POST['dsfm_admin_delete_folder'])
    ) {

        if (
            !isset($_POST['dsfm_admin_delete_folder_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['dsfm_admin_delete_folder_nonce']
                    )
                ),
                'dsfm_admin_delete_folder'
            )
        ) {

            echo '<div class="notice notice-error"><p>' .
                esc_html__(
                    'Security check failed.',
                    'darkstar-file-manager'
                ) .
                '</p></div>';

        } else {

            $folder =
                dsfm_normalize_relative_path(
                    wp_unslash(
                        $_POST['dsfm_admin_delete_folder']
                    )
                );


            $result =
                dsfm_delete_user_directory(
                    $user_root,
                    $folder
                );


            if ($result['success']) {

                $parent = dirname($folder);

                if ($parent === '.' || $parent === '/') {
                    $parent = '';
                }

                wp_safe_redirect(
                    dsfm_admin_directory_url(
                        $user_id,
                        $parent
                    )
                );

                exit;

            } else {

                echo '<div class="notice notice-error"><p>' .
                    esc_html($result['error']) .
                    '</p></div>';
            }
        }
    }


    /*
     * ---------------------------------------------------------
     * Delete individual file
     * ---------------------------------------------------------
     */

    if (
        isset($_POST['dsfm_admin_delete_file']) &&
        isset($_POST['dsfm_admin_delete_nonce'])
    ) {

        if (
            wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['dsfm_admin_delete_nonce']
                    )
                ),
                'dsfm_admin_delete_file'
            )
        ) {

            $relative_file =
                dsfm_normalize_relative_path(
                    wp_unslash(
                        $_POST['dsfm_admin_delete_file']
                    )
                );


            $resolved_path =
                dsfm_resolve_user_path(
                    $user_root,
                    $relative_file,
                    true
                );


            if (
                $resolved_path &&
                is_file($resolved_path)
            ) {

                wp_delete_file($resolved_path);


                if (isset($metadata[$relative_file])) {

                    unset($metadata[$relative_file]);

                } else {

                    $base = basename($relative_file);

                    if (isset($metadata[$base])) {
                        unset($metadata[$base]);
                    }
                }


                dsfm_save_metadata(
                    $user_root,
                    $metadata
                );


                echo '<div class="notice notice-success"><p>' .
                    esc_html__(
                        'File deleted successfully.',
                        'darkstar-file-manager'
                    ) .
                    '</p></div>';
            }
        }
    }


    /*
     * ---------------------------------------------------------
     * Bulk delete
     * ---------------------------------------------------------
     */

    if (
        isset($_POST['dsfm_bulk_delete']) &&
        isset($_POST['dsfm_files']) &&
        is_array($_POST['dsfm_files'])
    ) {

        if (
            isset($_POST['dsfm_bulk_delete_nonce']) &&
            wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['dsfm_bulk_delete_nonce']
                    )
                ),
                'dsfm_bulk_delete'
            )
        ) {

            $deleted_count = 0;


            foreach (
                wp_unslash($_POST['dsfm_files'])
                as $file
            ) {

                $file =
                    dsfm_normalize_relative_path($file);


                $resolved_path =
                    dsfm_resolve_user_path(
                        $user_root,
                        $file,
                        true
                    );


                if (
                    $resolved_path &&
                    is_file($resolved_path)
                ) {

                    wp_delete_file(
                        $resolved_path
                    );


                    if (isset($metadata[$file])) {
                        unset($metadata[$file]);
                    } else {

                        $base = basename($file);

                        if (isset($metadata[$base])) {
                            unset($metadata[$base]);
                        }
                    }


                    $deleted_count++;
                }
            }


            dsfm_save_metadata(
                $user_root,
                $metadata
            );


            if ($deleted_count > 0) {

                echo '<div class="notice notice-success"><p>';

                echo esc_html(
                    sprintf(
                        __(
                            '%d file(s) deleted successfully.',
                            'darkstar-file-manager'
                        ),
                        $deleted_count
                    )
                );

                echo '</p></div>';
            }
        }
    }


    /*
     * ---------------------------------------------------------
     * Admin upload
     * ---------------------------------------------------------
     */

    if (
        isset($_POST['dsfm_admin_upload']) &&
        isset($_FILES['dsfm_admin_file'])
    ) {

        if (
            isset($_POST['dsfm_admin_upload_nonce']) &&
            wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_POST['dsfm_admin_upload_nonce']
                    )
                ),
                'dsfm_admin_upload_file'
            )
        ) {

            $rate_key =
                'dsfm_uploads_' .
                get_current_user_id() .
                '_' .
                floor(time() / HOUR_IN_SECONDS);


            $upload_count =
                (int) get_transient($rate_key);


            if (
                $upload_count >=
                DSFM_MAX_UPLOADS_PER_HOUR
            ) {

                echo '<div class="notice notice-error"><p>' .
                    esc_html__(
                        'Upload rate limit exceeded. Please wait before uploading more files.',
                        'darkstar-file-manager'
                    ) .
                    '</p></div>';

            } else {

                $file_input = [
                    'name' =>
                        sanitize_file_name(
                            wp_unslash(
                                $_FILES['dsfm_admin_file']['name']
                            )
                        ),

                    'type' =>
                        isset(
                            $_FILES['dsfm_admin_file']['type']
                        )
                            ? sanitize_mime_type(
                                wp_unslash(
                                    $_FILES['dsfm_admin_file']['type']
                                )
                            )
                            : '',

                    'tmp_name' =>
                        $_FILES['dsfm_admin_file']['tmp_name'],

                    'error' =>
                        isset(
                            $_FILES['dsfm_admin_file']['error']
                        )
                            ? (int) $_FILES['dsfm_admin_file']['error']
                            : UPLOAD_ERR_NO_FILE,

                    'size' =>
                        isset(
                            $_FILES['dsfm_admin_file']['size']
                        )
                            ? (int) $_FILES['dsfm_admin_file']['size']
                            : 0,
                ];


                $validation =
                    dsfm_validate_upload(
                        $file_input
                    );


                if (!$validation['valid']) {

                    echo '<div class="notice notice-error"><p>' .
                        esc_html(
                            $validation['error']
                        ) .
                        '</p></div>';

                } else {

                    $timestamp = time();


                    if (!function_exists('wp_handle_upload')) {
                        require_once ABSPATH .
                            'wp-admin/includes/file.php';
                    }


                    /*
                     * Upload into current admin directory.
                     */
                    $dsfm_admin_dir_filter =
                        function ($dirs) use ($current_dir) {

                            $dirs['path']   = $current_dir;
                            $dirs['url']    = '';
                            $dirs['subdir'] = '';

                            return $dirs;
                        };


                    add_filter(
                        'upload_dir',
                        $dsfm_admin_dir_filter
                    );


                    $uploaded =
                        wp_handle_upload(
                            $file_input,
                            [
                                'test_form' => false,

                                'unique_filename_callback' =>
                                    function (
                                        $dir,
                                        $name,
                                        $ext
                                    ) use ($timestamp) {

                                        $base =
                                            pathinfo(
                                                $name,
                                                PATHINFO_FILENAME
                                            );

                                        return
                                            $timestamp .
                                            '-' .
                                            $base .
                                            $ext;
                                    },
                            ]
                        );


                    remove_filter(
                        'upload_dir',
                        $dsfm_admin_dir_filter
                    );


                    if (isset($uploaded['error'])) {

                        echo '<div class="notice notice-error"><p>' .
                            esc_html(
                                $uploaded['error']
                            ) .
                            '</p></div>';

                    } else {

                        $normalized_root =
                            rtrim(
                                realpath($user_root),
                                DIRECTORY_SEPARATOR
                            );


                        $normalized_file =
                            realpath(
                                $uploaded['file']
                            );


                        $relative_file =
                            ltrim(
                                str_replace(
                                    DIRECTORY_SEPARATOR,
                                    '/',
                                    substr(
                                        $normalized_file,
                                        strlen(
                                            $normalized_root
                                        )
                                    )
                                ),
                                '/'
                            );


                        $metadata[$relative_file] = [
                            'timestamp'   => $timestamp,
                            'uploaded_by' => 'admin',
                        ];


                        dsfm_save_metadata(
                            $user_root,
                            $metadata
                        );


                        set_transient(
                            $rate_key,
                            $upload_count + 1,
                            HOUR_IN_SECONDS
                        );


                        echo '<div class="notice notice-success"><p>' .
                            esc_html__(
                                'File uploaded successfully.',
                                'darkstar-file-manager'
                            ) .
                            '</p></div>';
                    }
                }
            }
        }
    }


    /*
     * ---------------------------------------------------------
     * Page
     * ---------------------------------------------------------
     */

    echo '<div class="wrap">';


    echo '<h1>';

    printf(
        esc_html__(
            'Documents for %s',
            'darkstar-file-manager'
        ),
        esc_html($user_info->user_login)
    );

    echo '</h1>';


    /*
     * Breadcrumb.
     */
    echo '<p><strong>' .
        esc_html__(
            'Location:',
            'darkstar-file-manager'
        ) .
        '</strong> ';


    echo '<a href="' .
        esc_url(
            dsfm_admin_directory_url(
                $user_id,
                ''
            )
        ) .
        '">Home</a>';


    if ($relative_dir !== '') {

        $parts =
            explode('/', $relative_dir);

        $accumulated = '';


        foreach ($parts as $part) {

            $accumulated =
                $accumulated === ''
                    ? $part
                    : $accumulated . '/' . $part;


            echo ' / ';

            echo '<a href="' .
                esc_url(
                    dsfm_admin_directory_url(
                        $user_id,
                        $accumulated
                    )
                ) .
                '">' .
                esc_html($part) .
                '</a>';
        }
    }


    echo '</p>';


    /*
     * Parent.
     */
    if ($relative_dir !== '') {

        $parent = dirname($relative_dir);

        if ($parent === '.' || $parent === '/') {
            $parent = '';
        }


        echo '<p>';

        echo '<a href="' .
            esc_url(
                dsfm_admin_directory_url(
                    $user_id,
                    $parent
                )
            ) .
            '">';

        echo '← ' .
            esc_html__(
                'Parent directory',
                'darkstar-file-manager'
            );

        echo '</a>';

        echo '</p>';
    }


    /*
     * Create folder.
     */
    echo '<div style="background:#fff;padding:15px;margin:20px 0;border:1px solid #ddd;border-radius:4px;">';

    echo '<h2>' .
        esc_html__(
            'Create Folder',
            'darkstar-file-manager'
        ) .
        '</h2>';


    echo '<form method="post" style="display:flex;gap:10px;align-items:center;">';


    echo '<input
        type="text"
        name="dsfm_folder_name"
        placeholder="' .
        esc_attr__(
            'Folder name',
            'darkstar-file-manager'
        ) .
        '"
        required>';


    wp_nonce_field(
        'dsfm_admin_create_folder',
        'dsfm_admin_folder_nonce'
    );


    echo '<button
        type="submit"
        name="dsfm_admin_create_folder"
        class="button">';

    echo esc_html__(
        'Create Folder',
        'darkstar-file-manager'
    );

    echo '</button>';


    echo '</form>';

    echo '</div>';


    /*
     * Upload.
     */
    echo '<div style="background:#fff;padding:15px;margin:20px 0;border:1px solid #ddd;border-radius:4px;">';


    echo '<h2>' .
        esc_html__(
            'Upload Document for Client',
            'darkstar-file-manager'
        ) .
        '</h2>';


    echo '<p>' .
        esc_html__(
            'The file will be uploaded to the current folder.',
            'darkstar-file-manager'
        ) .
        '</p>';


    echo '<form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;">';


    wp_nonce_field(
        'dsfm_admin_upload_file',
        'dsfm_admin_upload_nonce'
    );


    echo '<input type="file"
        name="dsfm_admin_file"
        required>';


    echo '<button
        type="submit"
        name="dsfm_admin_upload"
        class="button button-primary">';

    echo esc_html__(
        'Upload File',
        'darkstar-file-manager'
    );

    echo '</button>';


    echo '</form>';

    echo '</div>';


    /*
     * Current directory contents.
     */
    $items =
        dsfm_list_directory(
            $current_dir
        );


    if (empty($items)) {

        echo '<p>' .
            esc_html__(
                'This folder is empty.',
                'darkstar-file-manager'
            ) .
            '</p>';

        echo '</div>';

        return;
    }


    /*
     * Bulk delete form.
     */
    echo '<form method="post">';

    wp_nonce_field(
        'dsfm_bulk_delete',
        'dsfm_bulk_delete_nonce'
    );


    echo '<p>';

echo '<button
    type="submit"
    name="dsfm_bulk_delete"
    class="button button-secondary"
    onclick="' .
    esc_attr(
        'return confirm(' .
        wp_json_encode(
            __(
                'Delete selected files?',
                'darkstar-file-manager'
            )
        ) .
        ');'
    ) .
    '">';

    echo esc_html__(
        'Delete Selected',
        'darkstar-file-manager'
    );

    echo '</button>';

    echo '</p>';


    echo '<table class="widefat">';

    echo '<thead>';

    echo '<tr>';

    echo '<th style="width:30px;">
        <input type="checkbox" id="dsfm_select_all">
    </th>';

    echo '<th>' .
        esc_html__(
            'File / Folder',
            'darkstar-file-manager'
        ) .
        '</th>';

    echo '<th>' .
        esc_html__(
            'Date',
            'darkstar-file-manager'
        ) .
        '</th>';

    echo '<th>' .
        esc_html__(
            'Uploaded By',
            'darkstar-file-manager'
        ) .
        '</th>';

    echo '<th>' .
        esc_html__(
            'Delete',
            'darkstar-file-manager'
        ) .
        '</th>';

    echo '</tr>';

    echo '</thead>';

    echo '<tbody>';


    foreach ($items as $item) {

        /*
         * Folder.
         */
        if ($item['type'] === 'directory') {

            $folder_relative =
                $relative_dir === ''
                    ? $item['name']
                    : $relative_dir .
                        '/' .
                        $item['name'];


            echo '<tr>';

            echo '<td></td>';

            echo '<td>';

            echo '📁 ';

            echo '<a href="' .
                esc_url(
                    dsfm_admin_directory_url(
                        $user_id,
                        $folder_relative
                    )
                ) .
                '">';

            echo '<strong>' .
                esc_html($item['name']) .
                '</strong>';

            echo '</a>';

            echo '</td>';

            echo '<td></td>';

            echo '<td></td>';

            echo '<td>';

            echo '<form method="post" style="display:inline;">';

            echo '<input type="hidden"
                name="dsfm_admin_delete_folder"
                value="' .
                esc_attr($folder_relative) .
                '">';


            wp_nonce_field(
                'dsfm_admin_delete_folder',
                'dsfm_admin_delete_folder_nonce'
            );


echo '<button
    type="submit"
    class="button-link-delete"
    onclick="' .
    esc_attr(
        'return confirm(' .
        wp_json_encode(
            __(
                'Delete this empty folder?',
                'darkstar-file-manager'
            )
        ) .
        ');'
    ) .
    '">';

            echo esc_html__(
                'Delete',
                'darkstar-file-manager'
            );

            echo '</button>';

            echo '</form>';

            echo '</td>';

            echo '</tr>';

            continue;
        }


        /*
         * File.
         */
        $relative_file =
            $relative_dir === ''
                ? $item['name']
                : $relative_dir .
                    '/' .
                    $item['name'];


        $file_data =
            dsfm_get_file_metadata(
                $metadata,
                $relative_file
            );


        $timestamp =
            $file_data['timestamp'];


        $uploaded_by =
            $file_data['uploaded_by'];


        $download_url =
            wp_nonce_url(
                add_query_arg(
                    [
                        'dsfm_admin_download' =>
                            $relative_file,

                        'user_id' =>
                            $user_id,
                    ],
                    admin_url('users.php')
                ),
                'dsfm_admin_dl_' .
                    $relative_file
            );


        $display_name =
            preg_replace(
                '/^\d+-/',
                '',
                $item['name']
            );


        echo '<tr>';


        echo '<td>';

        echo '<input
            type="checkbox"
            name="dsfm_files[]"
            value="' .
            esc_attr($relative_file) .
            '"
            class="dsfm_file_checkbox">';

        echo '</td>';


        echo '<td>';

        echo '<a href="' .
            esc_url($download_url) .
            '">';

        echo '📄 ' .
            esc_html($display_name);

        echo '</a>';

        echo '</td>';


        echo '<td>';

        echo esc_html(
            $timestamp
                ? gmdate(
                    'M j Y',
                    $timestamp
                )
                : ''
        );

        echo '</td>';


        echo '<td>';

        echo esc_html(
            ucfirst($uploaded_by)
        );

        echo '</td>';


        echo '<td>';

        echo '<form method="post" style="display:inline;">';


        wp_nonce_field(
            'dsfm_admin_delete_file',
            'dsfm_admin_delete_nonce'
        );


        echo '<input
            type="hidden"
            name="dsfm_admin_delete_file"
            value="' .
            esc_attr($relative_file) .
            '">';


echo '<button
    type="submit"
    class="button button-link-delete"
    onclick="' .
    esc_attr(
        'return confirm(' .
        wp_json_encode(
            __(
                'Delete this file?',
                'darkstar-file-manager'
            )
        ) .
        ');'
    ) .
    '">';

        echo '&times;';

        echo '</button>';


        echo '</form>';

        echo '</td>';


        echo '</tr>';
    }


    echo '</tbody>';

    echo '</table>';

    echo '</form>';


    echo '</div>';
}


/**
 * Admin download handler.
 */
add_action('admin_init', function () {

    if (!current_user_can('manage_options')) {
        return;
    }


    if (
        empty($_GET['dsfm_admin_download']) ||
        empty($_GET['user_id'])
    ) {
        return;
    }


    $relative_file =
        dsfm_normalize_relative_path(
            wp_unslash(
                $_GET['dsfm_admin_download']
            )
        );


    if (
        !isset($_GET['_wpnonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(
                wp_unslash($_GET['_wpnonce'])
            ),
            'dsfm_admin_dl_' .
                $relative_file
        )
    ) {

        wp_die(
            esc_html__(
                'Security check failed.',
                'darkstar-file-manager'
            )
        );
    }


    $user_id =
        absint($_GET['user_id']);


    $user_info =
        get_userdata($user_id);


    if (!$user_info) {

        wp_die(
            esc_html__(
                'User not found.',
                'darkstar-file-manager'
            )
        );
    }


    $user_root =
        dsfm_get_user_root(
            $user_info
        );


    $real_path =
        dsfm_resolve_user_path(
            $user_root,
            $relative_file,
            true
        );


    if (
        !$real_path ||
        !is_file($real_path)
    ) {

        wp_die(
            esc_html__(
                'File not found.',
                'darkstar-file-manager'
            )
        );
    }


    $filename =
        preg_replace(
            '/^\d+-/',
            '',
            basename($relative_file)
        );


    $filename =
        str_replace(
            ['"', "\r", "\n"],
            '',
            $filename
        );


    header(
        'Content-Description: File Transfer'
    );

    header(
        'Content-Type: application/octet-stream'
    );

    header(
        'Content-Disposition: attachment; filename="' .
        $filename .
        '"'
    );

    header(
        'Content-Length: ' .
        filesize($real_path)
    );


    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    readfile($real_path);

    exit;
});
