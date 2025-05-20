<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use function RRZE\Multilang\plugin;
use RRZE\Multilang\Locale;

class Main
{
    private $localizeArgs = [];

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
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);

        add_action('init', [$this, 'multilangInit']);
        add_filter('pre_determine_locale', [$this, 'preDetermineLocale']);
        add_filter('locale', [$this, 'locale']);
        add_filter('query_vars', [$this, 'queryVars']);

        /* Language Switcher Widget */
        add_action('widgets_init', [$this, 'widgetsInit']);
        add_filter('rrze_multilang_widget_enabled', '__return_true');

        /* Locale Option */
        add_filter('widget_display_callback', [$this, 'widgetDisplayCallback'], 10, 3);

        /* Language Switcher Shortcode */
        add_shortcode('rrze_multilang_switcher', ['\RRZE\Multilang\Single\Switcher', 'languageSwitcher']);
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

            $pattern = '#^'
                . preg_quote($home)
                . '(?:' . preg_quote(trailingslashit($wp_rewrite->index)) . ')?'
                . Locale::getLangRegex()
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

        $assetFile = include plugin()->getPath('build') . 'classic-editor-single.asset.php';

        wp_enqueue_script(
            'rrze-multilang-classic-editor-single',
            plugins_url('build/classic-editor-single.js', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion(),
            true
        );

        if (empty($this->localizeArgs)) $this->setLocalizeArgs();
        wp_localize_script('rrze-multilang-classic-editor-single', 'rrzeMultilang', $this->localizeArgs);
    }

    public function enqueueBlockEditorAssets()
    {
        $assetFile = include plugin()->getPath('build') . 'block-editor-single.asset.php';

        wp_enqueue_script(
            '292882e06552f8bb23727601be8ff691',
            plugins_url('build/block-editor-single.js', plugin()->getBasename()),
            $assetFile['dependencies'] ?? [],
            $assetFile['version'] ?? plugin()->getVersion()
        );

        if (empty($this->localizeArgs)) $this->setLocalizeArgs();
        wp_localize_script('292882e06552f8bb23727601be8ff691', 'rrzeMultilang', $this->localizeArgs);

        wp_set_script_translations(
            '292882e06552f8bb23727601be8ff691',
            'rrze-multilang',
            plugin()->getPath('languages')
        );
    }

    private function setLocalizeArgs()
    {
        $availableLanguages = Locale::getAvailableLanguages([
            'orderby' => 'value',
        ]);

        $localizablePostTypes = Post::localizablePostTypes();

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
            'availableLanguages' => $availableLanguages,
            'defaultLocale' => Locale::getDefaultLocale(),
            'pagenow' => trim($_GET['page'] ?? ''),
            'currentPost' => [],
            'localizablePostTypes' => $localizablePostTypes,
        ];

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

        $this->localizeArgs['currentPost'] = $currentPost;
    }
}
