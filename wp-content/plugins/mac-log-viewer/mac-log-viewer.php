<?php
/**
 * Plugin Name: MAC Log Viewer
 * Plugin URI: https://macusaone.com
 * Description: View and manage PHP error logs with syntax highlighting.
 * Version: 1.0.0
 * Author: MAC USA One
 * Author URI: https://macusaone.com
 * License: GPL v2 or later
 * Text Domain: mac-log-viewer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MAC_LOG_VIEWER_VERSION', '1.0.0');
define('MAC_LOG_VIEWER_PLUGIN_FILE', __FILE__);
define('MAC_LOG_VIEWER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAC_LOG_VIEWER_PLUGIN_URL', plugin_dir_url(__FILE__));

class MAC_Log_Viewer
{
    const PLUGIN_SLUG = 'mac-log-viewer';
    const VERSION = '1.0.0';

    public function __construct()
    {
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_mac_log_viewer_refresh', [$this, 'ajax_refresh_log']);
    }

    public function init()
    {
        // Load dependencies
        if (defined('MAC_CORE_PATH')) {
            $addon_manager_path = MAC_CORE_PATH . 'includes/class-addon-manager.php';
            if (file_exists($addon_manager_path)) {
                require_once $addon_manager_path;
                if (class_exists('MAC_Addon_Manager') && method_exists('MAC_Addon_Manager', 'register_addon')) {
                    MAC_Addon_Manager::register_addon($this);
                }
            }
        }
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Error Log Viewer',
            'Error Log Viewer',
            'manage_options',
            'mac-error-log-viewer',
            [$this, 'error_log_page'],
            'dashicons-media-text',
            80
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook === 'toplevel_page_mac-error-log-viewer') {
            wp_enqueue_style('mac-log-viewer-admin', MAC_LOG_VIEWER_PLUGIN_URL . 'assets/css/admin.css', [], MAC_LOG_VIEWER_VERSION);
            wp_enqueue_script('mac-log-viewer-admin', MAC_LOG_VIEWER_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], MAC_LOG_VIEWER_VERSION, true);
        }
    }

    public function error_log_page()
    {
        // Nếu plugin Error Log Monitor có class autodetect thì dùng
        $log_file = null;
        if (class_exists('Elm_PhpErrorLog')) {
            $log_reader = Elm_PhpErrorLog::autodetect();
            if (!is_wp_error($log_reader)) {
                $log_file = $log_reader->getFilename();
            }
        }

        // Nếu không có thì fallback mặc định
        if (empty($log_file)) {
            if (defined('WP_DEBUG_LOG')) {
                if (WP_DEBUG_LOG === true) {
                    $log_file = WP_CONTENT_DIR . '/debug.log';
                } elseif (is_string(WP_DEBUG_LOG)) {
                    $log_file = WP_DEBUG_LOG;
                }
            } else {
                $log_file = WP_CONTENT_DIR . '/debug.log';
            }
        }

        echo '<div class="wrap"><h1>Error Log Viewer</h1>';

        // Nút clear log
        if (isset($_POST['clear_log']) && check_admin_referer('clear_log_action')) {
            if (file_exists($log_file)) {
                file_put_contents($log_file, '');
                echo '<div class="updated notice"><p>Log file has been cleared.</p></div>';
            }
        }

        echo '<form method="post">';
        wp_nonce_field('clear_log_action');
        echo '<p><input type="submit" name="clear_log" class="button button-secondary" value="Clear Log"></p>';
        echo '</form>';
        
        // Add nonce for AJAX
        echo '<input type="hidden" id="mac_log_viewer_nonce" value="' . wp_create_nonce('mac_log_viewer_nonce') . '">';

        // Hiển thị log
        if ($log_file && file_exists($log_file)) {
            $content = file_get_contents($log_file);

            if (!empty($content)) {
                echo '<pre class="mac-log-viewer-content">'
                    . $this->format_log_content($content) .
                    '</pre>';
            } else {
                echo '<p><em>Log file is empty.</em></p>';
            }
        } else {
            echo '<p><em>Log file not found at: ' . esc_html($log_file) . '</em></p>';
        }

        echo '</div>';
    }

    /**
     * Format log content for better readability
     */
    private function format_log_content($content)
    {
        $lines = explode("\n", $content);
        $formatted = '';

        foreach ($lines as $line) {
            // Bỏ qua dòng có PHP Notice
            if (stripos($line, 'PHP Notice:') !== false) {
                continue;
            }

            $line = esc_html($line);

            // Highlight [date]
            $line = preg_replace('/\[(.*?)\]/', '<span class="log-date">[$1]</span>', $line);

            // Highlight Fatal error
            $line = preg_replace('/PHP Fatal error:/', '<span class="log-fatal">PHP Fatal error:</span>', $line);

            // Highlight Warning
            $line = preg_replace('/PHP Warning:/', '<span class="log-warning">PHP Warning:</span>', $line);

            $formatted .= $line . "\n";
        }

        return $formatted;
    }

    /**
     * AJAX handler for refreshing log content
     */
    public function ajax_refresh_log()
    {
        check_ajax_referer('mac_log_viewer_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $log_file = $this->get_log_file();
        
        if ($log_file && file_exists($log_file)) {
            $content = file_get_contents($log_file);
            $formatted_content = $this->format_log_content($content);
            
            wp_send_json_success([
                'content' => $formatted_content,
                'file_size' => filesize($log_file),
                'last_modified' => filemtime($log_file)
            ]);
        } else {
            wp_send_json_error('Log file not found');
        }
    }

    /**
     * Get log file path
     */
    private function get_log_file()
    {
        $log_file = null;
        
        if (class_exists('Elm_PhpErrorLog')) {
            $log_reader = Elm_PhpErrorLog::autodetect();
            if (!is_wp_error($log_reader)) {
                $log_file = $log_reader->getFilename();
            }
        }

        if (empty($log_file)) {
            if (defined('WP_DEBUG_LOG')) {
                if (WP_DEBUG_LOG === true) {
                    $log_file = WP_CONTENT_DIR . '/debug.log';
                } elseif (is_string(WP_DEBUG_LOG)) {
                    $log_file = WP_DEBUG_LOG;
                }
            } else {
                $log_file = WP_CONTENT_DIR . '/debug.log';
            }
        }

        return $log_file;
    }
}

// Initialize the plugin
new MAC_Log_Viewer();

class Elm_PhpErrorLog
{
    private $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Get an instance of this class that represents the PHP error log.
     * The log filename is detected automatically.
     *
     * @static
     * @return Elm_PhpErrorLog|WP_Error An instance of this log reader, or WP_Error if error logging is not configured properly.
     */
    public static function autodetect()
    {
        $logErrors = strtolower(strval(ini_get('log_errors')));
        $errorLoggingEnabled = !empty($logErrors) && !in_array($logErrors, array('off', '0', 'false', 'no'));
        $logFile = ini_get('error_log');

        //Check for common problems that could prevent us from displaying the error log.
        if (!$errorLoggingEnabled) {
            return new WP_Error(
                'log_errors_off',
                __('Error logging is disabled.', 'mac-log-viewer')
            );
        } else if (empty($logFile)) {
            return new WP_Error(
                'error_log_not_set',
                __('Error log filename is not set.', 'mac-log-viewer')
            );
        } else if ((strpos($logFile, '/') === false) && (strpos($logFile, '\\') === false)) {
            return new WP_Error(
                'error_log_uses_relative_path',
                sprintf(
                    __('The current error_log value <code>%s</code> is not supported. Please change it to an absolute path.', 'mac-log-viewer'),
                    esc_html($logFile)
                )
            );
        } else if (!is_readable($logFile)) {
            if (file_exists($logFile)) {
                return new WP_Error(
                    'error_log_not_accessible',
                    sprintf(
                        __('The log file <code>%s</code> exists, but is not accessible. Please check file permissions.', 'mac-log-viewer'),
                        esc_html($logFile)
                    )
                );
            } else {
                return new WP_Error(
                    'error_log_not_found',
                    sprintf(
                        __('The log file <code>%s</code> does not exist or is inaccessible.', 'mac-log-viewer'),
                        esc_html($logFile)
                    )
                );
            }
        }

        return new self($logFile);
    }

    /**
     * Get an iterator over log entries in reverse order (i.e. starting from the end of the file).
     *
     * @param int|null $maxLines If set, the iterator will stop after reading this many lines. NULL = no line limit.
     * @param int|null $fromOffset Start reading from this byte offset. NULL = read from the end of the file.
     * @param int $toOffset Stop reading at this byte offset. Default is 0, i.e. the beginning of the file.
     * @return array|WP_Error Array of log lines or WP_Error if failed.
     */
    public function getIterator($maxLines = null, $fromOffset = null, $toOffset = 0)
    {
        if (!file_exists($this->filename) || !is_readable($this->filename)) {
            return new WP_Error('error_log_not_readable', __('Log file is not readable.', 'mac-log-viewer'));
        }

        try {
            $content = file_get_contents($this->filename);
            if ($content === false) {
                return new WP_Error('error_log_read_failed', __('Failed to read log file.', 'mac-log-viewer'));
            }

            $lines = explode("\n", $content);
            $lines = array_filter($lines); // Remove empty lines
            $lines = array_reverse($lines); // Reverse order

            if ($maxLines !== null && $maxLines > 0) {
                $lines = array_slice($lines, 0, $maxLines);
            }

            return $lines;
        } catch (Exception $exception) {
            return new WP_Error('error_log_fopen_failed', $exception->getMessage());
        }
    }

    /**
     * Clear the log.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function clear()
    {
        if (!file_exists($this->filename)) {
            return new WP_Error('error_log_not_found', __('Log file does not exist.', 'mac-log-viewer'));
        }

        if (!is_writable($this->filename)) {
            return new WP_Error('error_log_not_writable', __('Log file is not writable.', 'mac-log-viewer'));
        }

        $handle = @fopen($this->filename, 'w');
        if ($handle === false) {
            return new WP_Error('error_log_clear_failed', __('Failed to clear log file.', 'mac-log-viewer'));
        }

        fclose($handle);
        return true;
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getFileSize()
    {
        if (!file_exists($this->filename)) {
            return 0;
        }
        return filesize($this->getFilename());
    }

    public function getModificationTime()
    {
        if (!file_exists($this->filename)) {
            return 0;
        }
        return filemtime($this->filename);
    }
}
