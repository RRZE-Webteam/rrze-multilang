<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use function RRZE\Multilang\plugin;
use RRZE\Multilang\Options;

class Main
{
    protected $options;

    private $localizeArgs = [];

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
            add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        }

        new Post;
        new Media;
        new Terms;
        new RestApi;

        /* Enqueue Frontend Style */
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);

        /* Language Switcher Widget */
        add_action('widgets_init', [$this, 'widgetsInit']);
        add_filter('rrze_multilang_widget_enabled', '__return_true');

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
        $assetFile = include plugin()->getPath('build') . 'frontend.asset.php';

        wp_register_style(
            'rrze-multilang-frontend',
            plugins_url('build/frontend.css', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion(),
        );
    }

    public function adminEnqueueScripts($hook)
    {
        if (
            $hook !== 'post.php' &&
            $hook !== 'post-new.php' &&
            !in_array(get_post_type(), Post::localizablePostTypes())
        ) {
            return;
        }

        $assetFile = include plugin()->getPath('build') . 'classic-editor-multiple.asset.php';

        wp_enqueue_script(
            'rrze-multilang-classic-editor-multiple',
            plugins_url('build/classic-editor-multiple.js', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion(),
            true
        );

        if (empty($this->localizeArgs)) $this->setLocalizeArgs();
        wp_localize_script('rrze-multilang-classic-editor-multiple', 'rrzeMultilang', $this->localizeArgs);
    }

    public function enqueueBlockEditorAssets()
    {
        $assetFile = include plugin()->getPath('build') . 'block-editor-multiple.asset.php';

        wp_enqueue_style(
            'rrze-multilang-block-editor-multiple',
            plugins_url('build/block-editor-multiple.css', plugin()->getBasename()),
            [],
            $assetFile['version'] ?? plugin()->getVersion(),
        );

        wp_enqueue_script(
            'rrze-multilang-block-editor-multiple',
            plugins_url('build/block-editor-multiple.js', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion(),
        );

        if (empty($this->localizeArgs)) $this->setLocalizeArgs();
        wp_localize_script('rrze-multilang-block-editor-multiple', 'rrzeMultilang', $this->localizeArgs);

        wp_set_script_translations(
            'rrze-multilang-block-editor-multiple',
            'rrze-multilang',
            plugin()->getPath('languages')
        );
    }

    private function setLocalizeArgs()
    {
        $this->localizeArgs = [
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

        $this->localizeArgs['currentPost'] = $currentPost;
    }
}
