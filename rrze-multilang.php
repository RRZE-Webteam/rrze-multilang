<?php

/*
Plugin Name:     RRZE Multilang
Plugin URI:      https://github.com/RRZE-Webteam/rrze-multilang
Description:     Multilanguage plugin for WordPress.
Version:         0.1.0
Author:          RRZE-Webteam
Author URI:      https://blogs.fau.de/webworking/
License:         GNU General Public License v2
License URI:     http://www.gnu.org/licenses/gpl-2.0.html
Domain Path:     /languages
Text Domain:     rrze-multilang
*/

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

// Autoloader (PSR-4)
spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $base_dir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

const RRZE_PHP_VERSION = '7.4';
const RRZE_WP_VERSION = '5.5';

register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Loads a plugin’s translated strings.
 */
function loadTextdomain()
{
    load_plugin_textdomain('rrze-multilang', false, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
}

/**
 * [systemRequirements description]
 * @return string [description]
 */
function systemRequirements(): string
{
    loadTextdomain();

    $error = '';
    if (version_compare(PHP_VERSION, RRZE_PHP_VERSION, '<')) {
        $error = sprintf(__('The server is running PHP version %s. The Plugin requires at least PHP version %s.', 'rrze-multilang'), PHP_VERSION, RRZE_PHP_VERSION);
    } elseif (version_compare($GLOBALS['wp_version'], RRZE_WP_VERSION, '<')) {
        $error = sprintf(__('The server is running WordPress version %s. The Plugin requires at least WordPress version %s.', 'rrze-multilang'), $GLOBALS['wp_version'], RRZE_WP_VERSION);
    }
    return $error;
}

/**
 * [activation description]
 */
function activation()
{
    if ($error = systemRequirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(sprintf(__('Plugins: %s: %s', 'rrze-log'), plugin_basename(__FILE__), $error));
    }
}

/**
 * [deactivation description]
 */
function deactivation()
{
    Options::deleteOption();
    Options::deleteCurrentBlogConnections();
    flush_rewrite_rules();
}

/**
 * [plugin description]
 * @return object
 */
function plugin(): object
{
    static $instance;
    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }
    return $instance;
}

/**
 * [loaded description]
 * @return void
 */
function loaded()
{
    add_action('init', __NAMESPACE__ . '\loadTextdomain');
    plugin()->onLoaded();

    if ($error = systemRequirements()) {
        add_action('admin_init', function () use ($error) {
            if (current_user_can('activate_plugins')) {
                $pluginData = get_plugin_data(plugin()->getFile());
                $pluginName = $pluginData['Name'];
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';
                add_action($tag, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' . __('Plugins: %s: %s', 'rrze-multilang') . '</p></div>',
                        esc_html($pluginName),
                        esc_html($error)
                    );
                });
            }
        });
        return;
    }

    $main = new Main;
    $main->onLoaded();
}