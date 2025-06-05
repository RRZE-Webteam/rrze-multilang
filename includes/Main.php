<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

class Main
{
    /**
     * @var \RRZE\Multilang\Options
     */
    protected $options;

    /**
     * @var \RRZE\Multilang\Settings
     */
    protected $settings;

    /**
     * Main constructor.
     */
    public function __construct()
    {
        $this->options = (object) Options::getOptions();
    }

    /**
     * Initialize the plugin.
     *
     * This method is called when the plugin is loaded.
     * It sets up the necessary hooks and initializes the modules based on the configuration.
     * 
     * @return void
     */
    public function loaded()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('admin_notices', [$this, 'adminNotices']);

        $this->settings = new Settings;

        // Verify if the plugin \RRZE\Workflow is activated and the network module is enabled.
        // If so, we do not load the multilang module.
        // This is to avoid conflicts with the \RRZE\Workflow plugin.
        // The \RRZE\Workflow plugin has its own multilang module.
        if (
            method_exists('\RRZE\Workflow\Helper', 'isPluginModuleActivated')
            && \RRZE\Workflow\Helper::isPluginModuleActivated('network')
        ) {
            return;
        }

        switch ($this->options->multilang_mode) {
            case 1:
                new \RRZE\Multilang\Single\Main;
                break;
            case 2:
                if (is_multisite()) {
                    new \RRZE\Multilang\Multiple\Main;
                }
                break;
            default:
                return;
        }
    }

    /**
     * Add a settings link to the plugin action links.
     * 
     * @param array $links
     * @return array
     */
    public function settingsLink($links)
    {
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=' . $this->settings->getMenuPage()),
            __('Settings', 'rrze-multilang')
        );
        array_unshift($links, $settingsLink);
        return $links;
    }

    /**
     * Display admin notices.
     * 
     * This method is responsible for showing any admin notices that have been set.
     * It uses the Functions class to display flash messages.
     * 
     * @return void
     */
    public function adminNotices()
    {
        Functions::showFlashAdminNotices();
    }
}
