<?php

if (is_dir('/configs')) {
    define('PAGELYBIN', '/configs');
} else {
    define('PAGELYBIN', '/pagely');
}

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
} else {
    // If this looks like WP CLI, then check if `--path` is being passed
    $mwpPotentialSitePath = null;
    if (!empty($argv) && is_array($argv)) {
        if ($argv[0] == '/usr/local/bin/wp') {
            // Loop argv and look for --path
            foreach ($argv as $arg) {
                if (strpos($arg, '--path=') === 0) {
                    $mwpPotentialSitePath = str_replace('--path=', '', $arg);
                    break;
                }
            }
        }
    }
    // If we didn't find a path then use the CWD
    if ($mwpPotentialSitePath === null) {
        $mwpPotentialSitePath = getcwd();
    }
    // Resolve the path and check if it matches our regex
    $mwpPotentialSitePath = realpath($mwpPotentialSitePath);
    if ($mwpPotentialSitePath !== false && is_dir($mwpPotentialSitePath) && preg_match('@^(/sites/[^/]+)@', $mwpPotentialSitePath, $mwpSiteMatches)) {
        // If a config file exists at <sitepath>/pagely/config.php, then include it, otherwise do nothing
        $mwpSiteConfigPath = $mwpSiteMatches[1] . '/pagely/config.php';
        if (file_exists($mwpSiteConfigPath)) {
            include $mwpSiteConfigPath;
        }
    }
    unset($mwpPotentialSitePath, $mwpSiteMatches, $mwpSiteConfigPath);
}
