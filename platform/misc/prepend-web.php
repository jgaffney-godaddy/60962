<?php

// Cleans up the environment accounting for the chroot, also defines Pagely constants

// detect chroot and if so fix DOCUMENT_ROOT
if (__DIR__ == '/pagely' || __DIR__ == '/godaddy' || __DIR__ == '/platform/misc') {
    if (is_link('/httpdocs')) {
        // In this scenario we are always double symlinked and the final symlink is absolute, so realpath is better than readlink
        $httpdocsReal = realpath('/httpdocs');
        if ($httpdocsReal !== false) {
            $_SERVER['DOCUMENT_ROOT'] = $httpdocsReal . '/';
        }
    } else {
        $_SERVER['DOCUMENT_ROOT'] = '/httpdocs/';
    }
}

if (is_dir('/configs')) {
    define('PAGELYBIN', '/configs');
} else {
    define('PAGELYBIN', '/pagely');
}

// fix is_ssl detection
if (isset($_SERVER['HTTP_HTTPS'])) {
    $_SERVER['HTTPS'] = $_SERVER['HTTP_HTTPS'];
}

// wordpress config
define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISABLE_WP_CRON', true);
define('AUTOSAVE_INTERVAL', 300);

if (!defined('WP_CRON_LOCK_TIMEOUT')) {
    define('WP_CRON_LOCK_TIMEOUT', 120);
}

// force disable timthumb webshot
define('WEBSHOT_ENABLED', false);

// PHP basic auth compat
if (!empty($_SERVER['REMOTE_AUTHORIZATION'])) {
    $d = base64_decode($_SERVER['REMOTE_AUTHORIZATION']);
    if ($d !== false) {
        [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']] = explode(':', $d);
    }
}

// Authorization header compat
if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

// Set file permissions using umask
define('FS_CHMOD_DIR', (0777 & ~umask()));
define('FS_CHMOD_FILE', (0666 & ~umask()));

// Remove cruft headers that confuse some plugins like ithemes better security
// REMOTE_ADDR has the correct ip
unset($_SERVER['HTTP_X_CLUSTER_CLIENT']);
unset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']);
unset($_SERVER['HTTP_X_FORWARDED_FOR']);

// fix the problem with returning the free disk space for the whole ceph storage
// it breaks some of the major WP migration plugins because the value is too large
if (version_compare(phpversion(), '8.0.0', '>=')) {
    // php version is high enough
    if (!function_exists('disk_free_space')) {
        function disk_free_space($directory)
        {
            // always return 30GB
            return 32212254720;
        }
    }
    // do the same thing for the alias function
    if (!function_exists('diskfreespace')) {
        function diskfreespace($directory)
        {
            return disk_free_space($directory);
        }
    }
}

// Before we include anything from the user, include our creds
if (file_exists(PAGELYBIN . '/config.php')) {
    include PAGELYBIN . '/config.php';
}

$user_setup = '/user/setup.php';
if (file_exists($user_setup)) {
    include $user_setup;
}

// Prepend logic related to htaccess file support in nginx-unit
if (getenv('HTACCESS_MERGE_CONFIG_DIR') !== false) {
    // The htaccess golang program in mgmt/golang/pkg/htaccess writes out a prepend file by name prepend-rewrites.php
    define('HTACCESS_PREPEND_FILE', getenv('HTACCESS_MERGE_CONFIG_DIR') . '/prepend-rewrites.php');
    if (file_exists(HTACCESS_PREPEND_FILE)) {
        include HTACCESS_PREPEND_FILE;
    }
}
