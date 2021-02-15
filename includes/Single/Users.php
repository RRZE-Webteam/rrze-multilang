<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;

class Users
{
    protected $optionName;

    protected $siteOptionName;

    protected $options;

    protected $siteOptions;

    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->siteOptionName = Options::getSiteOptionName();
        $this->options = (object) Options::getOptions();
        $this->siteOptions = (object) Options::getSiteOptions();

        /* Toolbar (Admin Bar) */
        add_action('admin_bar_menu', [$this, 'adminBarInit'], 0, 1);
        add_action('wp_after_admin_bar_render', [$this, 'afterAdminBarRender'], 10, 0);
        add_action('admin_bar_menu', [$this, 'adminBarMenu'], 10, 1);

        add_action('admin_init', [$this, 'switchUserLocale'], 10, 0);
        add_filter('insert_user_meta', [$this, 'userMetaFilter'], 10, 3);
    }

    public function adminBarInit($wp_admin_bar)
    {
        switch_to_locale(self::getUserLocale());
    }

    public function afterAdminBarRender()
    {
        if (is_locale_switched()) {
            restore_current_locale();
        }
    }

    public function adminBarMenu($wp_admin_bar)
    {
        $currentLocale = self::getUserLocale();
        $defaultLanguage = Locale::getDefaultLocale();

        $availableLanguages = Locale::getAvailableLanguages([
            'selected_only' => false
        ]);
        $languages = Locale::getAvailableLanguages();

        if (!isset($languages[$defaultLanguage])) {
            $languages[$defaultLanguage] = $defaultLanguage;
        }

        if (isset($availableLanguages[$currentLocale])) {
            $currentLanguage = $availableLanguages[$currentLocale];
        } else {
            $currentLanguage = $currentLocale;
        }

        $wp_admin_bar->add_menu([
            'parent' => 'top-secondary',
            'id'     => 'rrze-multilang-user-locale',
            'title'  => sprintf(
                '<span class="ab-icon dashicons dashicons-translation"></span><span class="ab-label">%s</span>',
                esc_html($currentLanguage)
            ),
        ]);

        ksort($languages);
        foreach ($languages as $locale => $slug) {
            if ($locale == $currentLocale) {
                continue;
            }
            $url = add_query_arg(
                [
                    'action'      => 'rrze-multilang-switch-locale',
                    'locale'      => $locale,
                    'redirect_to' => urlencode($_SERVER['REQUEST_URI']),
                ],
                admin_url('profile.php')
            );

            if (isset($availableLanguages[$locale])) {
                $lang = $availableLanguages[$locale];
            } else {
                $lang = $locale;
            }
            $url = wp_nonce_url($url, 'rrze-multilang-switch-locale');

            $wp_admin_bar->add_menu([
                'parent' => 'rrze-multilang-user-locale',
                'id'     => 'rrze-multilang-user-locale-' . $locale,
                'title'  => $locale == $defaultLanguage ?
                    sprintf(
                        /* translators: %s: The user website default language. */
                        __('%s &mdash; Website Default', 'rrze-multilang'),
                        $lang
                    ) : $lang,
                'href'   => $url,
            ]);
        }
    }

    public function switchUserLocale()
    {
        if (
            empty($_REQUEST['action'])
            || 'rrze-multilang-switch-locale' != $_REQUEST['action']
        ) {
            return;
        }

        check_admin_referer('rrze-multilang-switch-locale');

        $locale = isset($_REQUEST['locale']) ? $_REQUEST['locale'] : '';
        $currentLocale = self::getUserLocale();

        if (
            !Locale::isAvailableLocale($locale)
            || $locale == $currentLocale
        ) {
            return;
        }

        $defaultLocale = Locale::getDefaultLocale();
        $locale = $locale == $defaultLocale ? '' : $locale;

        update_user_option(get_current_user_id(), 'locale', $locale, true);

        if (!empty($_REQUEST['redirect_to'])) {
            wp_safe_redirect($_REQUEST['redirect_to']);
            exit();
        }
    }

    public function userMetaFilter($meta, $user, $update)
    {
        if (user_can($user, 'rrze_multilang_access_all_locales')) {
            return $meta;
        }

        $locale = $meta['locale'];

        if (empty($locale)) {
            $locale = Locale::getDefaultLocale();
        }

        $accessible_locales = Locale::filterLocales(
            self::getUserAccessibleLocales($user->ID)
        );

        if (empty($accessible_locales)) {
            $locale = '';
        } elseif (!in_array($locale, $accessible_locales, true)) {
            $locale = $accessible_locales[0];
        }

        $meta['locale'] = $locale;

        return $meta;
    }

    public static function getUserLocale($userId = 0)
    {
        $defaultLocale = Locale::getDefaultLocale();

        if (!$userId = absint($userId)) {
            if (function_exists('wp_get_current_user')) {
                $currentUser = wp_get_current_user();

                if (!empty($currentUser->locale)) {
                    return $currentUser->locale;
                }
            }

            if (!$userId = get_current_user_id()) {
                return $defaultLocale;
            }
        }

        $locale = get_user_option('locale', $userId);

        if (Locale::isAvailableLocale($locale)) {
            return $locale;
        }

        return $defaultLocale;
    }

    public static function getUserAccessibleLocales($userId)
    {
        global $wpdb;

        $userId = absint($userId);

        if (user_can($userId, 'rrze_multilang_access_all_locales')) {
            $locales = Locale::availableLocales();

            return $locales;
        }

        $metaKey = $wpdb->get_blog_prefix() . 'accessible_locale';

        $locales = (array) get_user_meta($userId, $metaKey);

        $locales = Locale::filterLocales($locales);

        if (empty($locales)) {
            $locales = [Locale::getDefaultLocale()];
        }

        return $locales;
    }
}
