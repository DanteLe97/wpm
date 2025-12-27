<?php
namespace MAC_Core;

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

class Uninstaller {
    public static function uninstall() {
        // No operation on uninstall per product policy: keep all data/options.
        // Intentionally left blank.
    }
}
