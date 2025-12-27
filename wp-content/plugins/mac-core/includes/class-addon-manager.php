<?php
/**
 * MAC Core Add-on Manager
 * Manages add-ons integration with MAC Core
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('MAC_Addon_Manager')) {
    class MAC_Addon_Manager
{
    private static $addons = [];
    private static $instance = null;

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register an add-on with MAC Core
     */
    public static function register_addon($addon)
    {
        if (is_object($addon) && method_exists($addon, 'get_plugin_slug')) {
            $slug = $addon->get_plugin_slug();
            self::$addons[$slug] = $addon;
        } elseif (is_object($addon) && defined(get_class($addon) . '::PLUGIN_SLUG')) {
            $slug = $addon::PLUGIN_SLUG;
            self::$addons[$slug] = $addon;
        }
    }

    /**
     * Get registered add-ons
     */
    public static function get_addons()
    {
        return self::$addons;
    }

    /**
     * Get specific add-on
     */
    public static function get_addon($slug)
    {
        return self::$addons[$slug] ?? null;
    }

    /**
     * Check if add-on is registered
     */
    public static function is_addon_registered($slug)
    {
        return isset(self::$addons[$slug]);
    }

    /**
     * Get add-on info
     */
    public static function get_addon_info($slug)
    {
        $addon = self::get_addon($slug);
        if (!$addon) {
            return null;
        }

        return [
            'slug' => $slug,
            'class' => get_class($addon),
            'version' => defined(get_class($addon) . '::VERSION') ? $addon::VERSION : '1.0.0',
            'active' => true
        ];
    }
}
}
