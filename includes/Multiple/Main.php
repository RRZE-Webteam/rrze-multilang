<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use function RRZE\Multilang\plugin;
use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;

class Main
{
    protected $options;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();

        if ($this->options->connection_type == 0) {
            return;
        } elseif ($this->options->connection_type == 1) {
            new Metabox;

            /* Enqueue Admin Scripts */
            add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

            /* Enqueue Block Editor Assets */
            add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'], 10, 0);
        }

        new Post;
        new Media;
        new Terms;
        new RestApi;

        /* Enqueue Frontend Style */
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);

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

    public function enqueueScripts()
    {
        wp_register_style(
            'rrze-multilang-frontend',
            plugins_url('assets/css/default.css', plugin()->getBasename()),
            [],
            plugin()->getVersion()
        );
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

        $availableLanguages = Locale::getAvailableLanguages([
            'orderby' => 'value',
        ]);

        $localArgs = [
            'l10n' => [
                /* translators: accessibility text */
                'targetBlank' => __('Opens in a new window.', 'rrze-multilang'),
                'saveAlert' => __('The changes you made will be lost if you navigate away from this page.', 'rrze-multilang'),
            ],
            'apiSettings' => [
                'root' => esc_url_raw(rest_url('rrze-multilang/v1')),
                'namespace' => 'rrze-multilang/v1',
                'nonce' => (wp_installing() && !is_multisite())
                    ? '' : wp_create_nonce('wp_rest'),
            ],
            'currentPost' => [],
            'localizablePostTypes' => Post::localizablePostTypes(),
        ];

        if (in_array($hookSuffix, ['post.php', 'post-new.php'])) {
            $currentPost = [
                'secondarySitesToLink' => [],
                'secondarySitesToCopy' => []
            ];
            if ($post = get_post()) {
                //$postTypeObject = get_post_type_object($post->post_type);
                //$editPostCap = $postTypeObject->cap->edit_post;

                $currentPost['postId'] = $post->ID;

                $currentPost['secondarySitesToLink'] = Sites::getSecondaryToLink($post);
                $currentPost['secondarySitesToCopy'] = Sites::getSecondaryToCopy($post);
            }
            $localArgs['currentPost'] = $currentPost;
        }

        wp_localize_script('rrze-multilang-admin', 'rrzeMultilang', $localArgs);
    }

    public function enqueueBlockEditorAssets()
    {
        $assetFile = include(plugin()->getPath('assets/block-editor/build/multiple') . 'index.asset.php');

        wp_enqueue_script(
            'rrze-multilang-block-editor-multiple',
            plugins_url('assets/block-editor/build/multiple/index.js', plugin()->getBasename()),
            $assetFile['dependencies'],
            plugin()->getVersion()
        );

        wp_set_script_translations(
            'rrze-multilang-block-editor-multiple',
            'rrze-multilang',
            plugin()->getPath('languages')
        );
    }
}
