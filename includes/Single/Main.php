<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use function RRZE\Multilang\plugin;
use RRZE\Multilang\Locale;

class Main
{
    public function __construct()
    {
        new Capabilities;
        new Rewrite;
        new Users;
        new Post;
        new Metabox;
        new Query;
        new Links;
        new Terms;
        new NavMenu;
        new RestApi;
        new Tools;

        /* Enqueue Frontend Style */
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);

        /* Enqueue Admin Scripts */
        add_action('admin_enqueue_scripts', [$this, 'adminEnqueueScripts']);

        /* Enqueue Block Editor Assets */
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets'], 10, 0);

        add_action('init', [$this, 'multilangInit'], 10, 0);
        add_filter('pre_determine_locale', [$this, 'preDetermineLocale'], 10, 1);
        add_filter('locale', [$this, 'locale'], 10, 1);
        add_filter('query_vars', [$this, 'queryVars'], 10, 1);

        /* Language Switcher Widget */
        add_action('widgets_init', [$this, 'widgetsInit'], 10, 0);

        /* Locale Option */
        add_filter('widget_display_callback', [$this, 'widgetDisplayCallback'], 10, 3);

        /* Language Switcher Shortcode */
        add_shortcode('rrze_multilang_switcher', ['RRZE\Multilang\Single\Switcher', 'languageSwitcher']);
    }

    public function widgetsInit()
    {
        register_widget('\RRZE\Multilang\Single\SwitcherWidget');
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

    public function multilangInit()
    {
        Locale::languages();
        Translation::import(determine_locale());
    }

    public function preDetermineLocale($locale)
    {
        if (!empty($_GET['filter_action'])) {
            return $locale;
        }

        if (
            isset($_GET['lang'])
            && $closest = Locale::getClosestLocale($_GET['lang'])
        ) {
            $locale = $closest;
        }

        return $locale;
    }

    public function locale($locale)
    {
        global $wp_rewrite, $wp_query;

        if (!did_action('plugins_loaded') || is_admin()) {
            return $locale;
        }

        static $staticMultilangLocale = '';

        if ($staticMultilangLocale) {
            return $staticMultilangLocale;
        }

        $defaultLocale = Locale::getDefaultLocale();

        if (!empty($wp_query->query_vars)) {
            $lang = get_query_var('lang');
            if (
                $lang
                && $closest = Locale::getClosestLocale($lang)
            ) {
                return $staticMultilangLocale = $closest;
            } else {
                return $staticMultilangLocale = $defaultLocale;
            }
        }

        if (
            isset($wp_rewrite)
            && $wp_rewrite->using_permalinks()
        ) {
            $url = is_ssl() ? 'https://' : 'http://';
            $url .= $_SERVER['HTTP_HOST'];
            $url .= $_SERVER['REQUEST_URI'];

            $home = set_url_scheme(get_option('home'));
            $home = trailingslashit($home);

            $availableLocales = Locale::availableLocales();
            $availableLocales = array_map(['RRZE\Multilang\Locale', 'langSlug'], $availableLocales);
            $availableLocales = implode('|', $availableLocales);

            $pattern = '#^'
                . preg_quote($home)
                . '(' . $availableLocales . ')'
                . '(/|$)#';

            if (
                preg_match($pattern, $url, $matches)
                && $closest = Locale::getClosestLocale($matches[1])
            ) {
                return $staticMultilangLocale = $closest;
            }
        }

        $lang = Locale::getLangFromUrl();

        if (
            $lang
            && $closest = Locale::getClosestLocale($lang)
        ) {
            return $staticMultilangLocale = $closest;
        }

        return $staticMultilangLocale = $defaultLocale;
    }

    public function queryVars($query_vars)
    {
        $query_vars[] = 'lang';

        return $query_vars;
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
            plugins_url('assets/js/single-admin.js', plugin()->getBasename()),
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
            'availableLanguages' => $availableLanguages,
            'defaultLocale' => Locale::getDefaultLocale(),
            'pagenow' => isset($_GET['page']) ? trim($_GET['page']) : '',
            'currentPost' => [],
            'localizablePostTypes' => Post::localizablePostTypes(),
        ];

        if (in_array($hookSuffix, ['post.php', 'post-new.php'])) {
            $userLocale = Users::getUserLocale();

            $currentPost = [
                'locale' => $userLocale,
                'lang' => Locale::langSlug($userLocale),
                'translations' => [],
            ];

            if ($post = get_post()) {
                $currentPost['postId'] = $post->ID;
                $postTypeObject = get_post_type_object($post->post_type);
                $editPostCap = $postTypeObject->cap->edit_post;

                if ($locale = get_post_meta($post->ID, '_rrze_multilang_single_locale', true)) {
                    $currentPost['locale'] = $locale;
                    $currentPost['lang'] = Locale::langSlug($locale);
                }

                $availableLocales = Locale::availableLocales([
                    'exclude' => [$currentPost['locale']],
                ]);

                foreach ($availableLocales as $locale) {
                    $currentPost['translations'][$locale] = [];

                    $translation = Post::getPostTranslation($post->ID, $locale);

                    if ($translation) {
                        $currentPost['translations'][$locale] = [
                            'postId' => $translation->ID,
                            'postTitle' => $translation->post_title,
                            'editLink' => current_user_can($editPostCap, $translation->ID)
                                ? get_edit_post_link($translation, 'raw')
                                : '',
                        ];
                    }
                }
            }

            $localArgs['currentPost'] = $currentPost;
        }

        wp_localize_script('rrze-multilang-admin', 'rrzeMultilang', $localArgs);
    }

    public function enqueueBlockEditorAssets()
    {
        $assetFile = include(plugin()->getPath('assets/block-editor/build/single') . 'index.asset.php');

        wp_enqueue_script(
            'rrze-multilang-block-editor-single',
            plugins_url('assets/block-editor/build/single/index.js', plugin()->getBasename()),
            $assetFile['dependencies'],
            plugin()->getVersion()
        );

        wp_set_script_translations(
            'rrze-multilang-block-editor-single',
            'rrze-multilang',
            plugin()->getPath('languages')
        );
    }
}
