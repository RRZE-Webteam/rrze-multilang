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

    public function onLoaded()
    {
        add_filter('plugin_action_links_' . plugin()->getBaseName(), [$this, 'settingsLink']);

        add_action('admin_notices', [$this, 'adminNotices']);

        // CMS Workflow Plugin
        add_filter('cms_workflow_register_module_network', '__return_false');
        add_filter('cms_workflow_register_module_translation', '__return_false');

        $this->settings = new Settings;

        switch ($this->options->multilang_mode) {
            case 1:
                new \RRZE\Multilang\Single\Main;
                break;
            case 2:
                new \RRZE\Multilang\Multiple\Main;
            default:
                return;
        }
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
