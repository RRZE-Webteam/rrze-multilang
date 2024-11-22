<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

class Main
{
    protected $options;

    protected $settings;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();
    }

    public function loaded()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('admin_notices', [$this, 'adminNotices']);

        $this->settings = new Settings;

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

        // error_log(print_r(get_option('rrze_multilang_postmeta'), true));

        // error_log(print_r(get_site_option('rrze_multilang_sitemeta'), true));
    }

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

    public function adminNotices()
    {
        Functions::showFlashAdminNotices();
    }
}
