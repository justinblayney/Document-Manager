<?php
/**
 * Darkstar File Manager
 * Filesystem helpers for nested folders.
 */

defined('ABSPATH') || exit;


/**
 * Get the root directory for a specific WordPress user.
 *
 * @param int|WP_User $user User ID or WP_User.
 * @return string
 */
function dsfm_get_user_root($user)
{
    if (is_numeric($user)) {
        $user = get_userdata((int) $user);
    }

    if (!$user || empty($user->user_login)) {
        return '';
    }

    $username = sanitize_file_name($user->user_login);

    if ($username === '') {
        return '';
    }

    return trailingslashit(DSFM_UPLOAD_ROOT) . $username;
}


/**
 * Normalize a relative path.
 *
 * Never returns an absolute filesystem path.
 *
 * @param string $path Relative path.
 * @return string
 */
function dsfm_normalize_relative_path($path)
{
    $path = (string) $path;

    $path = str_replace('\\', '/', $path);
    $path = trim($path);
    $path = trim($path, '/');

    if ($path === '') {
        return '';
    }

    $parts = explode('/', $path);
    $safe  = [];

    foreach ($parts as $part) {

        $part = trim($part);

        if ($part === '') {
            continue;
        }

        if ($part === '.' || $part === '..') {
            continue;
        }

        if (strpos($part, "\0") !== false) {
            continue;
        }

        if (preg_match('/[\x00-\x1F\x7F]/u', $part)) {
            continue;
        }

        $safe[] = $part;
    }

    return implode('/', $safe);
}


/**
 * Resolve a relative path inside a user's root.
 *
 * @param string $user_root
 * @param string $relative_path
 * @param bool   $must_exist
 * @return string|false
 */
function dsfm_resolve_user_path(
    $user_root,
    $relative_path = '',
    $must_exist = true
) {
    $user_root = rtrim($user_root, '/\\');

    if ($user_root === '') {
        return false;
    }

    $real_user_root = realpath($user_root);

    if (!$real_user_root) {
        return false;
    }

    $relative_path = dsfm_normalize_relative_path($relative_path);

    if ($relative_path === '') {
        return $real_user_root;
    }

    $candidate = $real_user_root .
        DIRECTORY_SEPARATOR .
        str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $relative_path
        );

    if ($must_exist) {

        $real_candidate = realpath($candidate);

        if (!$real_candidate) {
            return false;
        }

    } else {

        $parent = dirname($candidate);
        $real_parent = realpath($parent);

        if (!$real_parent) {
            return false;
        }

        $real_candidate =
            $real_parent .
            DIRECTORY_SEPARATOR .
            basename($candidate);
    }

    $root_prefix =
        rtrim($real_user_root, DIRECTORY_SEPARATOR) .
        DIRECTORY_SEPARATOR;

    if (
        $real_candidate !== $real_user_root &&
        strpos($real_candidate, $root_prefix) !== 0
    ) {
        return false;
    }

    return $real_candidate;
}


/**
 * Resolve a directory inside a user's root.
 *
 * @param string $user_root
 * @param string $relative_dir
 * @return string|false
 */
function dsfm_resolve_user_directory(
    $user_root,
    $relative_dir = ''
) {
    $path = dsfm_resolve_user_path(
        $user_root,
        $relative_dir,
        true
    );

    if (!$path || !is_dir($path)) {
        return false;
    }

    return $path;
}


/**
 * List files and directories.
 *
 * @param string $directory
 * @return array
 */
function dsfm_list_directory($directory)
{
    $items = [];

    if (!is_dir($directory)) {
        return $items;
    }

    $entries = scandir($directory);

    if ($entries === false) {
        return $items;
    }

    foreach ($entries as $entry) {

        if ($entry === '.' || $entry === '..') {
            continue;
        }

        /*
         * Internal files are never displayed.
         */
        if (
            $entry === 'file-metadata.json' ||
            $entry === '.htaccess' ||
            $entry === 'index.php'
        ) {
            continue;
        }

        $path =
            trailingslashit($directory) .
            $entry;

        /*
         * Never expose symbolic links.
         */
        if (is_link($path)) {
            continue;
        }

        if (is_dir($path)) {

            $items[] = [
                'type' => 'directory',
                'name' => $entry,
                'path' => $entry,
            ];

        } elseif (is_file($path)) {

            $items[] = [
                'type' => 'file',
                'name' => $entry,
                'path' => $entry,
            ];
        }
    }

    usort(
        $items,
        function ($a, $b) {

            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'directory'
                    ? -1
                    : 1;
            }

            return strnatcasecmp(
                $a['name'],
                $b['name']
            );
        }
    );

    return $items;
}


/**
 * Get metadata.
 *
 * @param string $user_root
 * @return array
 */
function dsfm_get_metadata($user_root)
{
    $meta_file =
        trailingslashit($user_root) .
        'file-metadata.json';

    if (!file_exists($meta_file)) {
        return [];
    }

    $contents = file_get_contents($meta_file);

    if ($contents === false || $contents === '') {
        return [];
    }

    $metadata = json_decode(
        $contents,
        true
    );

    return is_array($metadata)
        ? $metadata
        : [];
}


/**
 * Save metadata.
 *
 * @param string $user_root
 * @param array  $metadata
 * @return bool
 */
function dsfm_save_metadata(
    $user_root,
    $metadata
) {
    $meta_file =
        trailingslashit($user_root) .
        'file-metadata.json';

    $json = wp_json_encode(
        $metadata,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    return false !== file_put_contents(
        $meta_file,
        $json,
        LOCK_EX
    );
}


/**
 * Get metadata for a file.
 *
 * Supports old flat metadata.
 *
 * @param array  $metadata
 * @param string $relative_path
 * @return array
 */
function dsfm_get_file_metadata(
    $metadata,
    $relative_path
) {
    if (isset($metadata[$relative_path])) {

        $data = $metadata[$relative_path];

        if (is_array($data)) {
            return [
                'timestamp' =>
                    isset($data['timestamp'])
                        ? (int) $data['timestamp']
                        : 0,

                'uploaded_by' =>
                    isset($data['uploaded_by'])
                        ? sanitize_key($data['uploaded_by'])
                        : 'client',
            ];
        }

        return [
            'timestamp'   => (int) $data,
            'uploaded_by' => 'client',
        ];
    }

    /*
     * Backwards compatibility.
     */
    $basename = basename($relative_path);

    if (isset($metadata[$basename])) {

        $data = $metadata[$basename];

        if (is_array($data)) {
            return [
                'timestamp' =>
                    isset($data['timestamp'])
                        ? (int) $data['timestamp']
                        : 0,

                'uploaded_by' =>
                    isset($data['uploaded_by'])
                        ? sanitize_key($data['uploaded_by'])
                        : 'client',
            ];
        }

        return [
            'timestamp'   => (int) $data,
            'uploaded_by' => 'client',
        ];
    }

    return [
        'timestamp'   => 0,
        'uploaded_by' => 'client',
    ];
}


/**
 * Create a directory.
 *
 * @param string $user_root
 * @param string $current_dir
 * @param string $folder_name
 * @return array
 */
function dsfm_create_user_directory(
    $user_root,
    $current_dir,
    $folder_name
) {
    $folder_name = sanitize_file_name(
        $folder_name
    );

    if (
        $folder_name === '' ||
        $folder_name === '.' ||
        $folder_name === '..'
    ) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Invalid folder name.',
                    'darkstar-file-manager'
                ),
        ];
    }

    if (
        strpos($folder_name, '/') !== false ||
        strpos($folder_name, '\\') !== false
    ) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Invalid folder name.',
                    'darkstar-file-manager'
                ),
        ];
    }

    $current_path =
        dsfm_resolve_user_directory(
            $user_root,
            $current_dir
        );

    if (!$current_path) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Directory not found.',
                    'darkstar-file-manager'
                ),
        ];
    }

    $new_path =
        $current_path .
        DIRECTORY_SEPARATOR .
        $folder_name;

    if (
        file_exists($new_path) ||
        is_link($new_path)
    ) {
        return [
            'success' => false,
            'error' =>
                __(
                    'A file or folder with this name already exists.',
                    'darkstar-file-manager'
                ),
        ];
    }

    /*
     * Only one directory is created.
     */
    if (!mkdir($new_path, 0755)) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Could not create the folder.',
                    'darkstar-file-manager'
                ),
        ];
    }

    if (!is_dir($new_path)) {
        return [
            'success' => false,
            'error' =>
                __(
                    'The folder could not be created.',
                    'darkstar-file-manager'
                ),
        ];
    }

    /*
     * Protect the newly-created directory when
     * the plugin provides the helper.
     */
    if (
        function_exists('dsfm_protect_upload_dir')
    ) {
        dsfm_protect_upload_dir($new_path);
    }

    return [
        'success' => true,
        'path'    => $new_path,
    ];
}


/**
 * Delete an empty directory.
 *
 * @param string $user_root
 * @param string $relative_dir
 * @return array
 */
function dsfm_delete_user_directory(
    $user_root,
    $relative_dir
) {
    $relative_dir =
        dsfm_normalize_relative_path(
            $relative_dir
        );

    if ($relative_dir === '') {
        return [
            'success' => false,
            'error' =>
                __(
                    'The root directory cannot be deleted.',
                    'darkstar-file-manager'
                ),
        ];
    }

    $directory =
        dsfm_resolve_user_directory(
            $user_root,
            $relative_dir
        );

    if (!$directory) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Directory not found.',
                    'darkstar-file-manager'
                ),
        ];
    }

    /*
     * Never delete symbolic links.
     */
    if (is_link($directory)) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Invalid directory.',
                    'darkstar-file-manager'
                ),
        ];
    }

    $contents = scandir($directory);

    if ($contents === false) {
        return [
            'success' => false,
            'error' =>
                __(
                    'Could not read the directory.',
                    'darkstar-file-manager'
                ),
        ];
    }

    foreach ($contents as $item) {

        if (
            $item === '.' ||
            $item === '..'
        ) {
            continue;
        }

        if (
            $item !== '.htaccess' &&
            $item !== 'index.php'
        ) {
            return [
                'success' => false,
                'error' =>
                    __(
                        'The folder is not empty. Please delete its contents first.',
                        'darkstar-file-manager'
                    ),
            ];
        }
    }

    foreach (
        ['.htaccess', 'index.php']
        as $protected_file
    ) {

        $file =
            $directory .
            DIRECTORY_SEPARATOR .
            $protected_file;

        if (is_file($file)) {
            wp_delete_file($file);
        }
    }

    if (@rmdir($directory)) {
        return [
            'success' => true,
        ];
    }

    return [
        'success' => false,
        'error' =>
            __(
                'Could not delete the folder.',
                'darkstar-file-manager'
            ),
    ];
}


/**
 * Build client directory URL.
 *
 * @param string $relative_dir
 * @return string
 */
function dsfm_client_directory_url(
    $relative_dir = ''
) {
    $url = get_permalink();

    $relative_dir =
        dsfm_normalize_relative_path(
            $relative_dir
        );

    if ($relative_dir !== '') {
        $url = add_query_arg(
            [
                'dsfm_dir' => $relative_dir,
            ],
            $url
        );
    }

    return $url;
}


/**
 * Build admin directory URL.
 *
 * @param int    $user_id
 * @param string $relative_dir
 * @return string
 */
function dsfm_admin_directory_url(
    $user_id,
    $relative_dir = ''
) {
    $args = [
        'page'    => 'dsfm-view-user-docs',
        'user_id' => (int) $user_id,
    ];

    $relative_dir =
        dsfm_normalize_relative_path(
            $relative_dir
        );

    if ($relative_dir !== '') {
        $args['dsfm_dir'] = $relative_dir;
    }

    return add_query_arg(
        $args,
        admin_url('users.php')
    );
}
