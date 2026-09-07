<?php
/**
 * Darkstar File Manager
 * Client portal functions.
 */

defined('ABSPATH') || exit;


/**
 * Client portal shortcode.
 *
 * Shortcode:
 * [dsfm_client_login]
 */
add_shortcode(
    'dsfm_client_login',
    function () {

        /*
         * Login form.
         */
        if (!is_user_logged_in()) {

            ob_start();

            wp_login_form(
                [
                    'redirect' => get_permalink(),
                    'action'   => wp_login_url(),
                ]
            );

            return ob_get_clean();
        }

        $user = wp_get_current_user();

        if (!$user || !$user->ID) {
            return '';
        }

        $user_root =
            dsfm_get_user_root($user);

        if ($user_root === '') {
            return '<p class="dsfm-error">' .
                esc_html__(
                    'Could not determine your user directory.',
                    'darkstar-file-manager'
                ) .
                '</p>';
        }

        /*
         * Create root if necessary.
         */
        if (!is_dir($user_root)) {

            wp_mkdir_p($user_root);

            if (
                function_exists(
                    'dsfm_protect_upload_dir'
                )
            ) {
                dsfm_protect_upload_dir(
                    DSFM_UPLOAD_ROOT
                );

                dsfm_protect_upload_dir(
                    $user_root
                );
            }
        }

        /*
         * Current directory.
         */
        $relative_dir = '';

        if (isset($_GET['dsfm_dir'])) {
            $relative_dir =
                dsfm_normalize_relative_path(
                    wp_unslash(
                        $_GET['dsfm_dir']
                    )
                );
        }

        $current_dir =
            dsfm_resolve_user_directory(
                $user_root,
                $relative_dir
            );

        /*
         * Invalid path -> root.
         */
        if (!$current_dir) {

            $relative_dir = '';
            $current_dir  = dsfm_resolve_user_directory(
                $user_root,
                ''
            );
        }

        if (!$current_dir) {
            return '<p class="dsfm-error">' .
                esc_html__(
                    'Could not open your file directory.',
                    'darkstar-file-manager'
                ) .
                '</p>';
        }

        $metadata =
            dsfm_get_metadata($user_root);

        $message = '';


        /*
         * =====================================================
         * CREATE FOLDER
         * =====================================================
         */
        if (
            'POST' ===
            strtoupper(
                isset($_SERVER['REQUEST_METHOD'])
                    ? $_SERVER['REQUEST_METHOD']
                    : ''
            ) &&
            isset($_POST['dsfm_create_folder'])
        ) {

            $nonce_valid =
                isset(
                    $_POST['dsfm_folder_nonce']
                ) &&
                wp_verify_nonce(
                    sanitize_text_field(
                        wp_unslash(
                            $_POST['dsfm_folder_nonce']
                        )
                    ),
                    'dsfm_create_folder'
                );

            if (!$nonce_valid) {

                $message =
                    '<p class="dsfm-error">' .
                    esc_html__(
                        'Security check failed.',
                        'darkstar-file-manager'
                    ) .
                    '</p>';

            } else {

                $folder_name =
                    isset(
                        $_POST['dsfm_folder_name']
                    )
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

                if (!empty($result['success'])) {

                    wp_safe_redirect(
                        dsfm_client_directory_url(
                            $relative_dir
                        )
                    );

                    exit;
                }

                $message =
                    '<p class="dsfm-error">' .
                    esc_html(
                        isset($result['error'])
                            ? $result['error']
                            : __(
                                'Could not create folder.',
                                'darkstar-file-manager'
                            )
                    ) .
                    '</p>';
            }
        }


        /*
         * =====================================================
         * DELETE FOLDER
         * =====================================================
         */
        if (
            'POST' ===
            strtoupper(
                isset($_SERVER['REQUEST_METHOD'])
                    ? $_SERVER['REQUEST_METHOD']
                    : ''
            ) &&
            isset($_POST['dsfm_delete_folder'])
        ) {

            $nonce_valid =
                isset(
                    $_POST['dsfm_delete_folder_nonce']
                ) &&
                wp_verify_nonce(
                    sanitize_text_field(
                        wp_unslash(
                            $_POST[
                                'dsfm_delete_folder_nonce'
                            ]
                        )
                    ),
                    'dsfm_delete_folder'
                );

            if (!$nonce_valid) {

                $message =
                    '<p class="dsfm-error">' .
                    esc_html__(
                        'Security check failed.',
                        'darkstar-file-manager'
                    ) .
                    '</p>';

            } else {

                $folder_to_delete =
                    dsfm_normalize_relative_path(
                        wp_unslash(
                            $_POST[
                                'dsfm_delete_folder'
                            ]
                        )
                    );

                $result =
                    dsfm_delete_user_directory(
                        $user_root,
                        $folder_to_delete
                    );

                if (!empty($result['success'])) {

                    $parent =
                        dirname(
                            $folder_to_delete
                        );

                    if (
                        $parent === '.' ||
                        $parent === '/'
                    ) {
                        $parent = '';
                    }

                    wp_safe_redirect(
                        dsfm_client_directory_url(
                            $parent
                        )
                    );

                    exit;
                }

                $message =
                    '<p class="dsfm-error">' .
                    esc_html(
                        isset($result['error'])
                            ? $result['error']
                            : __(
                                'Could not delete folder.',
                                'darkstar-file-manager'
                            )
                    ) .
                    '</p>';
            }
        }


        /*
         * =====================================================
         * DELETE FILE
         * =====================================================
         */
        if (
            'POST' ===
            strtoupper(
                isset($_SERVER['REQUEST_METHOD'])
                    ? $_SERVER['REQUEST_METHOD']
                    : ''
            ) &&
            !empty($_POST['dsfm_delete_file'])
        ) {

            $nonce_valid =
                isset(
                    $_POST['dsfm_delete_nonce']
                ) &&
                wp_verify_nonce(
                    sanitize_text_field(
                        wp_unslash(
                            $_POST['dsfm_delete_nonce']
                        )
                    ),
                    'dsfm_delete_file'
                );

            if (!$nonce_valid) {

                $message =
                    '<p class="dsfm-error">' .
                    esc_html__(
                        'Security check failed for deletion.',
                        'darkstar-file-manager'
                    ) .
                    '</p>';

            } else {

                $file_to_delete =
                    dsfm_normalize_relative_path(
                        wp_unslash(
                            $_POST['dsfm_delete_file']
                        )
                    );

                $file_path =
                    dsfm_resolve_user_path(
                        $user_root,
                        $file_to_delete,
                        true
                    );

                if (
                    $file_path &&
                    is_file($file_path) &&
                    !is_link($file_path)
                ) {

                    $file_data =
                        dsfm_get_file_metadata(
                            $metadata,
                            $file_to_delete
                        );

                    /*
                     * Client may only delete client files.
                     */
                    if (
                        $file_data['uploaded_by'] ===
                        'admin'
                    ) {

                        $message =
                            '<p class="dsfm-error">' .
                            esc_html__(
                                'This file cannot be deleted.',
                                'darkstar-file-manager'
                            ) .
                            '</p>';

                    } else {

                        wp_delete_file(
                            $file_path
                        );

                        if (
                            isset(
                                $metadata[
                                    $file_to_delete
                                ]
                            )
                        ) {

                            unset(
                                $metadata[
                                    $file_to_delete
                                ]
                            );

                            dsfm_save_metadata(
                                $user_root,
                                $metadata
                            );

                        } else {

                            $base =
                                basename(
                                    $file_to_delete
                                );

                            if (
                                isset(
                                    $metadata[$base]
                                )
                            ) {

                                unset(
                                    $metadata[$base]
                                );

                                dsfm_save_metadata(
                                    $user_root,
                                    $metadata
                                );
                            }
                        }

                        $message =
                            '<p class="dsfm-success">' .
                            esc_html__(
                                'File deleted successfully.',
                                'darkstar-file-manager'
                            ) .
                            '</p>';
                    }

                } else {

                    $message =
                        '<p class="dsfm-error">' .
                        esc_html__(
                            'File not found or cannot delete.',
                            'darkstar-file-manager'
                        ) .
                        '</p>';
                }
            }
        }


        /*
         * =====================================================
         * FILE UPLOAD
         * =====================================================
         */
        if (
            'POST' ===
            strtoupper(
                isset($_SERVER['REQUEST_METHOD'])
                    ? $_SERVER['REQUEST_METHOD']
                    : ''
            ) &&
            !empty(
                $_FILES['dsfm_file']['name']
            ) &&
            isset(
                $_FILES['dsfm_file']['tmp_name']
            )
        ) {

            $nonce_valid =
                isset(
                    $_POST['dsfm_upload_nonce']
                ) &&
                wp_verify_nonce(
                    sanitize_text_field(
                        wp_unslash(
                            $_POST['dsfm_upload_nonce']
                        )
                    ),
                    'dsfm_upload_file'
                );

            if (!$nonce_valid) {

                $message =
                    '<p class="dsfm-error">' .
                    esc_html__(
                        'Security check failed. Please try again.',
                        'darkstar-file-manager'
                    ) .
                    '</p>';

            } else {

                $rate_key =
                    'dsfm_uploads_' .
                    $user->ID .
                    '_' .
                    floor(
                        time() /
                        HOUR_IN_SECONDS
                    );

                $upload_count =
                    (int) get_transient(
                        $rate_key
                    );

                if (
                    $upload_count >=
                    DSFM_MAX_UPLOADS_PER_HOUR
                ) {

                    $message =
                        '<p class="dsfm-error">' .
                        esc_html__(
                            'Upload rate limit exceeded. Please wait before uploading more files.',
                            'darkstar-file-manager'
                        ) .
                        '</p>';

                } else {

                    $file_input = [
                        'name' =>
                            sanitize_file_name(
                                wp_unslash(
                                    $_FILES[
                                        'dsfm_file'
                                    ]['name']
                                )
                            ),

                        'type' =>
                            isset(
                                $_FILES[
                                    'dsfm_file'
                                ]['type']
                            )
                                ? sanitize_mime_type(
                                    wp_unslash(
                                        $_FILES[
                                            'dsfm_file'
                                        ]['type']
                                    )
                                )
                                : '',

                        'tmp_name' =>
                            $_FILES[
                                'dsfm_file'
                            ]['tmp_name'],

                        'error' =>
                            isset(
                                $_FILES[
                                    'dsfm_file'
                                ]['error']
                            )
                                ? (int) $_FILES[
                                    'dsfm_file'
                                ]['error']
                                : UPLOAD_ERR_NO_FILE,

                        'size' =>
                            isset(
                                $_FILES[
                                    'dsfm_file'
                                ]['size']
                            )
                                ? (int) $_FILES[
                                    'dsfm_file'
                                ]['size']
                                : 0,
                    ];

                    $validation =
                        dsfm_validate_upload(
                            $file_input
                        );

                    if (
                        empty(
                            $validation['valid']
                        )
                    ) {

                        $message =
                            '<p class="dsfm-error">' .
                            esc_html(
                                isset(
                                    $validation['error']
                                )
                                    ? $validation['error']
                                    : __(
                                        'Invalid upload.',
                                        'darkstar-file-manager'
                                    )
                            ) .
                            '</p>';

                    } else {

                        $timestamp = time();

                        if (
                            !function_exists(
                                'wp_handle_upload'
                            )
                        ) {
                            require_once ABSPATH .
                                'wp-admin/includes/file.php';
                        }

                        /*
                         * Force WordPress upload into
                         * the current user's directory.
                         */
                        $upload_filter =
                            function ($dirs)
                            use ($current_dir) {

                                $dirs['path'] =
                                    $current_dir;

                                $dirs['url'] = '';
                                $dirs['subdir'] = '';

                                return $dirs;
                            };

                        add_filter(
                            'upload_dir',
                            $upload_filter
                        );

                        $uploaded =
                            wp_handle_upload(
                                $file_input,
                                [
                                    'test_form' =>
                                        false,

                                    'unique_filename_callback' =>
                                        function (
                                            $dir,
                                            $name,
                                            $ext
                                        ) use (
                                            $timestamp
                                        ) {

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
                            $upload_filter
                        );

                        if (
                            isset(
                                $uploaded['error']
                            )
                        ) {

                            $message =
                                '<p class="dsfm-error">' .
                                esc_html(
                                    $uploaded['error']
                                ) .
                                '</p>';

                        } else {

                            $normalized_root =
                                realpath(
                                    $user_root
                                );

                            $normalized_file =
                                realpath(
                                    $uploaded['file']
                                );

                            if (
                                !$normalized_root ||
                                !$normalized_file
                            ) {

                                $message =
                                    '<p class="dsfm-error">' .
                                    esc_html__(
                                        'Uploaded file could not be verified.',
                                        'darkstar-file-manager'
                                    ) .
                                    '</p>';

                            } else {

                                $root_prefix =
                                    rtrim(
                                        $normalized_root,
                                        DIRECTORY_SEPARATOR
                                    ) .
                                    DIRECTORY_SEPARATOR;

                                if (
                                    strpos(
                                        $normalized_file,
                                        $root_prefix
                                    ) !== 0
                                ) {

                                    wp_delete_file(
                                        $normalized_file
                                    );

                                    $message =
                                        '<p class="dsfm-error">' .
                                        esc_html__(
                                            'Upload destination is invalid.',
                                            'darkstar-file-manager'
                                        ) .
                                        '</p>';

                                } else {

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

                                    set_transient(
                                        $rate_key,
                                        $upload_count + 1,
                                        HOUR_IN_SECONDS
                                    );

                                    $metadata[
                                        $relative_file
                                    ] = [
                                        'timestamp' =>
                                            $timestamp,

                                        'uploaded_by' =>
                                            'user',
                                    ];

                                    dsfm_save_metadata(
                                        $user_root,
                                        $metadata
                                    );

                                    $message =
                                        '<p class="dsfm-success">' .
                                        esc_html__(
                                            'File uploaded successfully.',
                                            'darkstar-file-manager'
                                        ) .
                                        '</p>';
                                }
                            }
                        }
                    }
                }
            }
        }


        /*
         * =====================================================
         * OUTPUT
         * =====================================================
         */
        ob_start();

        $logout_url =
            wp_logout_url(
                get_permalink()
            );

        printf(
            '<p>%s <a href="%s">%s</a></p>',
            esc_html__(
                'You are logged in.',
                'darkstar-file-manager'
            ),
            esc_url($logout_url),
            esc_html__(
                'Log out',
                'darkstar-file-manager'
            )
        );

        echo wp_kses_post($message);


        /*
         * =====================================================
         * BREADCRUMB
         * =====================================================
         */
        echo '<div class="dsfm-breadcrumb" style="margin:20px 0;">';

        echo '<strong>' .
            esc_html__(
                'Location:',
                'darkstar-file-manager'
            ) .
            '</strong> ';

        echo '<a href="' .
            esc_url(
                dsfm_client_directory_url('')
            ) .
            '">' .
            esc_html__(
                'Home',
                'darkstar-file-manager'
            ) .
            '</a>';

        $breadcrumb_parts =
            $relative_dir !== ''
                ? explode(
                    '/',
                    $relative_dir
                )
                : [];

        $accumulated = '';

        foreach (
            $breadcrumb_parts
            as $part
        ) {

            $accumulated =
                $accumulated === ''
                    ? $part
                    : $accumulated .
                        '/' .
                        $part;

            echo ' / ';

            echo '<a href="' .
                esc_url(
                    dsfm_client_directory_url(
                        $accumulated
                    )
                ) .
                '">' .
                esc_html($part) .
                '</a>';
        }

        echo '</div>';


        /*
         * =====================================================
         * PARENT DIRECTORY
         * =====================================================
         */
        if ($relative_dir !== '') {

            $parent =
                dirname($relative_dir);

            if (
                $parent === '.' ||
                $parent === '/'
            ) {
                $parent = '';
            }

            echo '<p>';

            echo '<a class="dsfm-parent-directory" href="' .
                esc_url(
                    dsfm_client_directory_url(
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
         * =====================================================
         * CREATE FOLDER
         * =====================================================
         */
        echo '<div class="dsfm-folder-create" style="margin:20px 0;">';

        echo '<form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';

        echo '<input type="text" name="dsfm_folder_name" placeholder="' .
            esc_attr__(
                'New folder name',
                'darkstar-file-manager'
            ) .
            '" required>';

        wp_nonce_field(
            'dsfm_create_folder',
            'dsfm_folder_nonce'
        );

        echo '<button type="submit" name="dsfm_create_folder" class="button">';

        echo esc_html__(
            'Create folder',
            'darkstar-file-manager'
        );

        echo '</button>';

        echo '</form>';

        echo '</div>';


        /*
         * =====================================================
         * DIRECTORY CONTENTS
         * =====================================================
         */
        $items =
            dsfm_list_directory(
                $current_dir
            );


        /*
         * Admin files.
         */
        $admin_files = [];

        foreach ($items as $item) {

            if ($item['type'] !== 'file') {
                continue;
            }

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

            if (
                $file_data['uploaded_by'] ===
                'admin'
            ) {
                $admin_files[] = [
                    'file' =>
                        $relative_file,

                    'name' =>
                        $item['name'],

                    'timestamp' =>
                        $file_data['timestamp'],
                ];
            }
        }


        /*
         * =====================================================
         * ADMIN DOCUMENTS
         * =====================================================
         */
        if (!empty($admin_files)) {

            echo '<div style="margin-bottom:30px;">';

            echo '<h3>' .
                esc_html__(
                    'Documents for you',
                    'darkstar-file-manager'
                ) .
                '</h3>';

            echo '<div class="file-viewer" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">';

            echo '<div style="text-align:left;"><strong>' .
                esc_html__(
                    'File Name',
                    'darkstar-file-manager'
                ) .
                '</strong></div>';

            echo '<div style="text-align:right;"><strong>' .
                esc_html__(
                    'Date Added',
                    'darkstar-file-manager'
                ) .
                '</strong></div>';

            foreach (
                $admin_files
                as $file_info
            ) {

                $file =
                    $file_info['file'];

                $timestamp =
                    $file_info['timestamp'];

                $url =
                    wp_nonce_url(
                        add_query_arg(
                            [
                                'dsfm_download' =>
                                    $file,
                            ],
                            home_url()
                        ),
                        'dsfm_download_' .
                        $file,
                        'dsfm_download_nonce'
                    );

                $display_name =
                    preg_replace(
                        '/^\d+-/',
                        '',
                        $file_info['name']
                    );

                echo '<div style="text-align:left;">';

                echo '<a href="' .
                    esc_url($url) .
                    '" target="_blank" rel="noopener noreferrer">';

                echo esc_html(
                    $display_name
                );

                echo '</a>';

                echo '</div>';

                echo '<div style="text-align:right;">' .
                    esc_html(
                        $timestamp
                            ? gmdate(
                                'M j Y',
                                $timestamp
                            )
                            : ''
                    ) .
                    '</div>';
            }

            echo '</div>';
            echo '</div>';
        }


        /*
         * =====================================================
         * UPLOAD
         * =====================================================
         */
        echo '<h3>' .
            esc_html__(
                'Your Uploaded Documents',
                'darkstar-file-manager'
            ) .
            '</h3>';

        echo '<form method="post" enctype="multipart/form-data" class="dsfm-upload-form">';

        echo '<label for="dsfm_file" class="dsfm-upload-label">';

        echo esc_html__(
            'Select a file to upload',
            'darkstar-file-manager'
        );

        echo '</label>';

        echo '<input type="file" name="dsfm_file" id="dsfm_file" required class="dsfm-upload-input" />';

        echo '<button type="submit" class="dsfm-upload-button">';

        echo esc_html__(
            'Upload File',
            'darkstar-file-manager'
        );

        echo '</button>';

        wp_nonce_field(
            'dsfm_upload_file',
            'dsfm_upload_nonce'
        );

        echo '</form>';


        /*
         * =====================================================
         * FILE / FOLDER TABLE
         * =====================================================
         */
        echo '<div class="file-viewer" style="display:grid;grid-template-columns:1fr auto auto;gap:10px;margin-top:20px;">';

        echo '<div style="text-align:left;"><strong>' .
            esc_html__(
                'Name',
                'darkstar-file-manager'
            ) .
            '</strong></div>';

        echo '<div style="text-align:right;"><strong>' .
            esc_html__(
                'Date Added',
                'darkstar-file-manager'
            ) .
            '</strong></div>';

        echo '<div style="text-align:right;"><strong>' .
            esc_html__(
                'Action',
                'darkstar-file-manager'
            ) .
            '</strong></div>';


        foreach ($items as $item) {

            /*
             * -------------------------------------------------
             * FOLDER
             * -------------------------------------------------
             */
            if (
                $item['type'] ===
                'directory'
            ) {

                $folder_relative =
                    $relative_dir === ''
                        ? $item['name']
                        : $relative_dir .
                            '/' .
                            $item['name'];

                $folder_url =
                    dsfm_client_directory_url(
                        $folder_relative
                    );

                echo '<div style="text-align:left;">';

                echo '📁 ';

                echo '<a href="' .
                    esc_url($folder_url) .
                    '">';

                echo esc_html(
                    $item['name']
                );

                echo '</a>';

                echo '</div>';

                echo '<div></div>';

                echo '<div style="text-align:right;">';


                /*
                 * Folder ZIP download.
                 */
                $folder_download_url =
                    wp_nonce_url(
                        add_query_arg(
                            [
                                'dsfm_download_folder' =>
                                    $folder_relative,
                            ],
                            home_url()
                        ),
                        'dsfm_download_folder_' .
                        $folder_relative,
                        'dsfm_download_folder_nonce'
                    );

                echo '<a href="' .
                    esc_url(
                        $folder_download_url
                    ) .
                    '" style="display:inline-block;padding:6px 10px;margin-right:8px;background:#2271b1;color:#fff;text-decoration:none;border-radius:3px;">';

                echo '⬇ ' .
                    esc_html__(
                        'Download folder',
                        'darkstar-file-manager'
                    );

                echo '</a>';


                /*
                 * Delete folder.
                 */
                echo '<form method="post" style="display:inline;margin:0;">';

                echo '<input type="hidden" name="dsfm_delete_folder" value="' .
                    esc_attr(
                        $folder_relative
                    ) .
                    '">';

                wp_nonce_field(
                    'dsfm_delete_folder',
                    'dsfm_delete_folder_nonce'
                );

                echo '<button type="submit" class="button-link" onclick="return confirm(\'' .
                    esc_js(
					__(
                        'Delete this empty folder?',
                        'darkstar-file-manager'
                    ) 
					).
                    '\');">';

                echo esc_html__(
                    'Delete',
                    'darkstar-file-manager'
                );

                echo '</button>';

                echo '</form>';

                echo '</div>';

                continue;
            }


            /*
             * -------------------------------------------------
             * FILE
             * -------------------------------------------------
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

            $url =
                wp_nonce_url(
                    add_query_arg(
                        [
                            'dsfm_download' =>
                                $relative_file,
                        ],
                        home_url()
                    ),
                    'dsfm_download_' .
                    $relative_file,
                    'dsfm_download_nonce'
                );

            $display_name =
                preg_replace(
                    '/^\d+-/',
                    '',
                    $item['name']
                );

            echo '<div style="text-align:left;">';

            echo '<a href="' .
                esc_url($url) .
                '" target="_blank" rel="noopener noreferrer">';

            echo '📄 ' .
                esc_html($display_name);

            echo '</a>';

            echo '</div>';

            echo '<div style="text-align:right;">';

            echo esc_html(
                $timestamp
                    ? gmdate(
                        'M j Y',
                        $timestamp
                    )
                    : ''
            );

            echo '</div>';


            /*
             * Client files can be deleted.
             * Admin files cannot.
             */
            if (
                $file_data['uploaded_by'] !==
                'admin'
            ) {

                echo '<div style="text-align:right;">';

                echo '<form method="post" style="margin:0;">';

                echo '<input type="hidden" name="dsfm_delete_file" value="' .
                    esc_attr(
                        $relative_file
                    ) .
                    '">';

                wp_nonce_field(
                    'dsfm_delete_file',
                    'dsfm_delete_nonce'
                );

                echo '<button type="submit" onclick="return confirm(\'' .
                    esc_js(
					__(
                        'Delete this file?',
                        'darkstar-file-manager'
                    )) .
                    '\');" aria-label="' .
                    esc_attr(
                        sprintf(
                            __(
                                'Delete %s',
                                'darkstar-file-manager'
                            ),
                            $display_name
                        )
                    ) .
                    '">';

                echo '&times;';

                echo '</button>';

                echo '</form>';

                echo '</div>';

            } else {

                echo '<div></div>';
            }
        }

        echo '</div>';

        return ob_get_clean();
    }
);


/**
 * =============================================================
 * CLIENT FOLDER DOWNLOAD
 * =============================================================
 *
 * Creates a ZIP of the requested folder.
 *
 * This handler is intentionally restricted to the
 * currently authenticated WordPress user's own root.
 */
add_action(
    'init',
    function () {

        if (
            !is_user_logged_in() ||
            empty(
                $_GET['dsfm_download_folder']
            )
        ) {
            return;
        }

        $relative_dir =
            dsfm_normalize_relative_path(
                wp_unslash(
                    $_GET[
                        'dsfm_download_folder'
                    ]
                )
            );

        /*
         * Nonce.
         */
        if (
            !isset(
                $_GET[
                    'dsfm_download_folder_nonce'
                ]
            ) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_GET[
                            'dsfm_download_folder_nonce'
                        ]
                    )
                ),
                'dsfm_download_folder_' .
                $relative_dir
            )
        ) {

            wp_die(
                esc_html__(
                    'Security check failed.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * Current user.
         */
        $user =
            wp_get_current_user();

        if (
            !$user ||
            !$user->ID
        ) {
            wp_die(
                esc_html__(
                    'User not found.',
                    'darkstar-file-manager'
                )
            );
        }

        $user_root =
            dsfm_get_user_root(
                $user
            );

        if ($user_root === '') {
            wp_die(
                esc_html__(
                    'User directory not found.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * Resolve selected folder.
         */
        $folder_path =
            dsfm_resolve_user_directory(
                $user_root,
                $relative_dir
            );

        if (
            !$folder_path ||
            !is_dir($folder_path) ||
            is_link($folder_path)
        ) {
            wp_die(
                esc_html__(
                    'Folder not found.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * ZipArchive is required.
         */
        if (
            !class_exists(
                'ZipArchive'
            )
        ) {
            wp_die(
                esc_html__(
                    'ZIP downloads are not available on this server.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * Temporary file.
         */
        $zip_file =
            wp_tempnam(
                'dsfm-folder'
            );

        if (!$zip_file) {
            wp_die(
                esc_html__(
                    'Could not create temporary ZIP file.',
                    'darkstar-file-manager'
                )
            );
        }

        $zip =
            new ZipArchive();

        $opened =
            $zip->open(
                $zip_file,
                ZipArchive::CREATE |
                ZipArchive::OVERWRITE
            );

        if ($opened !== true) {

            wp_delete_file(
                $zip_file
            );

            wp_die(
                esc_html__(
                    'Could not create ZIP archive.',
                    'darkstar-file-manager'
                )
            );
        }

        $folder_name =
            basename($folder_path);

        /*
         * Make the ZIP contain the folder itself.
         */
        $zip->addEmptyDir(
            $folder_name
        );

        try {

            $iterator =
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $folder_path,
                        FilesystemIterator::SKIP_DOTS |
                        FilesystemIterator::CURRENT_AS_FILEINFO
                    ),
                    RecursiveIteratorIterator::SELF_FIRST
                );

            foreach (
                $iterator as $item
            ) {

                /*
                 * Never follow symlinks.
                 */
                if ($item->isLink()) {
                    continue;
                }

                $full_path =
                    $item->getPathname();

                $relative_path =
                    substr(
                        $full_path,
                        strlen($folder_path) + 1
                    );

                $relative_path =
                    str_replace(
                        DIRECTORY_SEPARATOR,
                        '/',
                        $relative_path
                    );

                if (
                    $relative_path === '' ||
                    $relative_path === false
                ) {
                    continue;
                }

                /*
                 * Do not include internal metadata/protection
                 * files in the downloadable ZIP.
                 */
                $base =
                    basename($full_path);

                if (
                    $base === 'file-metadata.json' ||
                    $base === '.htaccess' ||
                    $base === 'index.php'
                ) {
                    continue;
                }

                $zip_path =
                    $folder_name .
                    '/' .
                    $relative_path;

                if ($item->isDir()) {

                    $zip->addEmptyDir(
                        $zip_path
                    );

                } elseif ($item->isFile()) {

                    $zip->addFile(
                        $full_path,
                        $zip_path
                    );
                }
            }

        } catch (Throwable $e) {

            $zip->close();

            wp_delete_file(
                $zip_file
            );

            wp_die(
                esc_html__(
                    'Could not read the folder contents.',
                    'darkstar-file-manager'
                )
            );
        }

        if (!$zip->close()) {

            wp_delete_file(
                $zip_file
            );

            wp_die(
                esc_html__(
                    'Could not finalize ZIP archive.',
                    'darkstar-file-manager'
                )
            );
        }

        $download_name =
            sanitize_file_name(
                $folder_name
            ) .
            '.zip';

        $filesize =
            filesize($zip_file);

        if ($filesize === false) {

            wp_delete_file(
                $zip_file
            );

            wp_die(
                esc_html__(
                    'Could not determine ZIP file size.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * Send ZIP.
         */
        nocache_headers();

        header(
            'Content-Type: application/zip'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            str_replace(
                ['"', "\r", "\n"],
                '',
                $download_name
            ) .
            '"'
        );

        header(
            'Content-Length: ' .
            (string) $filesize
        );

        header(
            'Content-Transfer-Encoding: binary'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($zip_file);

        wp_delete_file(
            $zip_file
        );

        exit;
    }
);


/**
 * =============================================================
 * CLIENT FILE DOWNLOAD
 * =============================================================
 */
add_action(
    'init',
    function () {

        if (
            !is_user_logged_in() ||
            empty(
                $_GET['dsfm_download']
            )
        ) {
            return;
        }

        $relative_file =
            dsfm_normalize_relative_path(
                wp_unslash(
                    $_GET['dsfm_download']
                )
            );

        /*
         * Nonce.
         */
        if (
            !isset(
                $_GET[
                    'dsfm_download_nonce'
                ]
            ) ||
            !wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash(
                        $_GET[
                            'dsfm_download_nonce'
                        ]
                    )
                ),
                'dsfm_download_' .
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

        $user =
            wp_get_current_user();

        if (
            !$user ||
            !$user->ID
        ) {
            wp_die(
                esc_html__(
                    'User not found.',
                    'darkstar-file-manager'
                )
            );
        }

        $user_root =
            dsfm_get_user_root(
                $user
            );

        if ($user_root === '') {
            wp_die(
                esc_html__(
                    'User directory not found.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * Resolve strictly inside the user's root.
         */
        $real_path =
            dsfm_resolve_user_path(
                $user_root,
                $relative_file,
                true
            );

        if (
            !$real_path ||
            !is_file($real_path) ||
            is_link($real_path)
        ) {
            wp_die(
                esc_html__(
                    'File not found.',
                    'darkstar-file-manager'
                )
            );
        }

        /*
         * Remove internal timestamp prefix.
         */
        $display_name =
            preg_replace(
                '/^\d+-/',
                '',
                basename(
                    $relative_file
                )
            );

        if (
            !is_string($display_name) ||
            $display_name === ''
        ) {
            $display_name =
                basename(
                    $relative_file
                );
        }

        $display_name =
            str_replace(
                ['"', "\r", "\n"],
                '',
                $display_name
            );

        $filesize =
            filesize($real_path);

        if ($filesize === false) {
            wp_die(
                esc_html__(
                    'Could not determine file size.',
                    'darkstar-file-manager'
                )
            );
        }

        nocache_headers();

        header(
            'Content-Description: File Transfer'
        );

        header(
            'Content-Type: application/octet-stream'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $display_name .
            '"'
        );

        header(
            'Content-Length: ' .
            (string) $filesize
        );

        header(
            'Cache-Control: must-revalidate'
        );

        header(
            'Pragma: public'
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        readfile($real_path);

        exit;
    }
);
