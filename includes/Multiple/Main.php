<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use function RRZE\Multilang\plugin;
use RRZE\Multilang\Options;

class Main
{
    protected $options;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();

        if ($this->options->connection_type == 1) {
            new Metabox;
        }
        
        new Post;
        new Media;
        new Terms;
        new RestApi;

        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        /* Language Switcher Widget */
        add_action('widgets_init', [$this, 'widgetsInit'], 10, 0);

        /* Locale Option */
        add_filter('widget_display_callback', [$this, 'widgetDisplayCallback'], 10, 3);

        /* Language Switcher Shortcode */
        add_shortcode('rrze_multilang_switcher', ['\RRZE\Multilang\Multiple\Switcher::languageSwitcher']);        
    }

    public function widgetsInit()
    {
        register_widget('\RRZE\Multilang\Multiple\SwitcherWidget');
    }

    public function widgetDisplayCallback($instance, $widget, $args)
    {
        if (isset($instance['rrze_multilang_locales'])) {
            $locale = get_locale();

            if (!in_array($locale, (array) $instance['rrze_multilang_locales'])) {
                $instance = false;
            }
        }

        return $instance;
    }

    public function adminEnqueueScripts($hookSuffix)
    {
        wp_enqueue_script(
            'rrze-multilang-admin',
            plugins_url('assets/js/multiple-admin.js', plugin()->getBasename()),
            ['jquery'],
            plugin()->getVersion(),
            true
        );

        $localArgs = [
            'apiSettings' => [
                'root' => esc_url_raw(rest_url('rrze-multilang/v1')),
                'namespace' => 'rrze-multilang/v1',
                'nonce' => (wp_installing() && !is_multisite())
                    ? '' : wp_create_nonce('wp_rest'),
            ]
        ];

        if (in_array($hookSuffix, ['post.php', 'post-new.php'])) {
            $currentPost = [];
            if ($post = get_post()) {
                $currentPost['postId'] = $post->ID;
            }
            $localArgs['currentPost'] = $currentPost;
        }

        wp_localize_script('rrze-multilang-admin', 'rrzeMultilang', $localArgs);
    }    
}