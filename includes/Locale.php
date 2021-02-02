<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

use RRZE\Multilang\Single\Users;

class Locale
{
    public static function availableLocales($args = '')
    {
        $defaults = [
            'exclude' => [],
            'selected_only' => true,
            'current_user_can_access' => false,
        ];

        $args = wp_parse_args($args, $defaults);

        $defaultLocale = self::getDefaultLocale();

        static $installedLocales = [];

        if (empty($installedLocales)) {
            $installedLocales = get_available_languages();
            $installedLocales[] = $defaultLocale;
            $installedLocales[] = 'en_US';
        }

        $availableLocales = array_unique(array_filter($installedLocales));

        if (
            $args['current_user_can_access']
            && !current_user_can('rrze_multilang_access_all_locales')
        ) {
            $userAccessibleLocales = Users::getUserAccessibleLocales(
                get_current_user_id()
            );

            $availableLocales = array_intersect(
                $availableLocales,
                $userAccessibleLocales
            );
        }

        if ($args['selected_only']) {
            $options = (object) Options::getOptions();
            $selectedLanguages = $options->languages;
            $selectedLanguages[$defaultLocale] = $defaultLocale;
            $availableLocales = array_intersect(
                $availableLocales,
                $selectedLanguages
            );
        }

        if (!empty($args['exclude'])) {
            $availableLocales = array_diff(
                $availableLocales,
                (array) $args['exclude']
            );
        }

        return array_unique(array_filter($availableLocales));
    }

    public static function getDefaultLocale()
    {
        static $locale;

        if (defined('WPLANG')) {
            $locale = \WPLANG;
        }

        if (is_multisite()) {
            if (
                wp_installing()
                || false === $ms_locale = get_option('WPLANG')
            ) {
                $ms_locale = get_site_option('WPLANG');
            }

            if ($ms_locale !== false) {
                $locale = $ms_locale;
            }
        } else {
            $db_locale = get_option('WPLANG');

            if ($db_locale !== false) {
                $locale = $db_locale;
            }
        }

        if (!empty($locale)) {
            return $locale;
        }

        return 'en_US';
    }

    public static function isDefaultLocale($locale)
    {
        $defaultLocale = self::getDefaultLocale();

        return !empty($locale) && $locale == $defaultLocale;
    }

    public static function filterLocales($locales, $filter = 'available')
    {
        return array_intersect((array) $locales, self::availableLocales());
    }

    public static function isAvailableLocale($locale)
    {
        if (empty($locale)) {
            return false;
        }

        static $availableLocales = [];

        if (empty($availableLocales)) {
            $availableLocales = self::availableLocales();
        }

        return in_array($locale, $availableLocales);
    }

    public static function languageTag($locale)
    {
        $tag = preg_replace('/[^0-9a-zA-Z]+/', '-', $locale);
        $tag = trim($tag, '-');

        return apply_filters('rrze_multilang_language_tag', $tag, $locale);
    }

    public static function langSlug($locale)
    {
        $tag = self::languageTag($locale);
        $slug = $tag;

        if (false !== $pos = strpos($tag, '-')) {
            $slug = substr($tag, 0, $pos);
        }

        $variations = preg_grep(
            '/^' . $slug . '/',
            self::availableLocales()
        );

        if (1 < count($variations)) {
            $slug = $tag;
        }

        return apply_filters('rrze_multilang_lang_slug', $slug, $locale);
    }

    public static function getLanguage(string $locale)
    {
        $defaultLocale = self::getDefaultLocale();
        $availableLanguages = self::getAvailableLanguages([
            'selected_only' => false
        ]);
        if (isset($availableLanguages[$locale])) {
            $language = $availableLanguages[$locale];
        } else {
            $language = $defaultLocale;
        }
        return $language;
    }

    public static function languages()
    {
        static $languages = [];
        static $textdomainLoaded = false;

        if ($languages && $textdomainLoaded && !is_locale_switched()) {
            return apply_filters('rrze_multilang_languages', $languages);
        }

        $languages = self::getAvailableLanguages();

        $textdomainLoaded = is_textdomain_loaded('rrze_multilang') && !is_locale_switched();

        asort($languages, SORT_STRING);

        return apply_filters('rrze_multilang_languages', $languages);
    }

    public static function isLocaleIso639($locale)
    {
        $tag = self::languageTag($locale);

        if (false === strpos($tag, '-')) {
            return true;
        }

        $slug = self::langSlug($locale);

        return strlen($slug) < strlen($tag);
    }

    public static function getShortName($origName)
    {
        $shortName = $origName;

        $exceptions = [
            '中文',
            'Français',
            'Português',
            'Español',
        ];

        foreach ($exceptions as $lang) {
            if (false !== strpos($origName, $lang)) {
                $shortName = $lang;
                break;
            }
        }

        if (preg_match('/^([^()]+)/', $shortName, $matches)) {
            $shortName = $matches[1];
        }

        $shortName = apply_filters('rrze-multilang_get_short_name', $shortName, $origName);

        return trim($shortName);
    }

    public static function getAvailableTranslations()
    {
        if (!function_exists('wp_get_available_translations')) {
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        }
        $translations = wp_get_available_translations();
        $english = [
            'en_US' => [
                'language' => 'en_US',
                'english_name' => 'English (United States)',
                'native_name' => 'English (United States)',
                'iso' => [
                    1 => 'en',
                    2 => 'eng',
                    3 => 'eng'
                ],
                'strings' => [
                    'continue' => 'Continue'
                ]
            ]
        ];
        return array_merge($translations, $english);
    }

    public static function getAvailableLanguages($args = '')
    {
        $defaults = [
            'exclude' => [],
            'orderby' => 'key',
            'order' => 'ASC',
            'short_name' => true,
            'selected_only' => true,
            'current_user_can_access' => false
        ];

        $args = wp_parse_args($args, $defaults);

        $availableLanguages = [];
        $languages = self::availableLocales($args);
        $translations = self::getAvailableTranslations();
        if (in_array('en_US', $languages)) {
            $availableLanguages['en_US'] = 'English (United States)';
        }
        foreach ($languages as $locale) {
            if (isset($translations[$locale])) {
                $translation = $translations[$locale];
                $availableLanguages[$locale] = $translation['native_name'];
            }
        }

        if ('value' == $args['orderby']) {
            natcasesort($availableLanguages);

            if ('DESC' == $args['order']) {
                $availableLanguages = array_reverse($availableLanguages);
            }
        } else {
            if ('DESC' == $args['order']) {
                krsort($availableLanguages);
            } else {
                ksort($availableLanguages);
            }
        }

        $availableLanguages = apply_filters('rrze_multilang_available_languages', $availableLanguages, $args);

        return $availableLanguages;
    }

    public static function getClosestLocale($var)
    {
        $var = strtolower($var);
        $locale_pattern = '/^([a-z]{2,3})(?:[_-]([a-z]{2})(?:[_-]([a-z0-9]+))?)?$/';

        if (!preg_match($locale_pattern, $var, $matches)) {
            return false;
        }

        $language_code = $matches[1];
        $region_code = isset($matches[2]) ? $matches[2] : '';
        $variant_code = isset($matches[3]) ? $matches[3] : '';

        $locales = self::availableLocales();

        if ($variant_code && $region_code) {
            $locale = $language_code
                . '_' . strtoupper($region_code)
                . '_' . $variant_code;

            if (false !== array_search($locale, $locales)) {
                return $locale;
            }
        }

        if ($region_code) {
            $locale = $language_code
                . '_' . strtoupper($region_code);

            if (false !== array_search($locale, $locales)) {
                return $locale;
            }
        }

        $locale = $language_code;

        if (false !== array_search($locale, $locales)) {
            return $locale;
        }

        if ($matches = preg_grep("/^{$locale}_/", $locales)) {
            return array_shift($matches);
        }

        return false;
    }

    public static function url($url = null, $lang = null)
    {
        if (!$lang) {
            $lang = determine_locale();
        }

        $args = [
            'using_permalinks' => (bool) get_option('permalink_structure'),
        ];

        return self::getUrlWithLang($url, $lang, $args);
    }

    public static function getLangFromUrl($url = '')
    {
        if (!$url) {
            $url = is_ssl() ? 'https://' : 'http://';
            $url .= $_SERVER['HTTP_HOST'];
            $url .= $_SERVER['REQUEST_URI'];
        }

        if ($frag = strstr($url, '#')) {
            $url = substr($url, 0, -strlen($frag));
        }

        $home = set_url_scheme(get_option('home'));
        $home = trailingslashit($home);

        $availableLanguages = array_map(
            [__CLASS__, 'langSlug'],
            self::availableLocales()
        );

        $regex = '#^'
            . preg_quote($home)
            . '(' . implode('|', $availableLanguages) . ')'
            . '/#';

        if (preg_match($regex, trailingslashit($url), $matches)) {
            return $matches[1];
        }

        if ($query = wp_parse_url($url, PHP_URL_QUERY)) {
            parse_str($query, $query_vars);

            if (
                isset($query_vars['lang'])
                && in_array($query_vars['lang'], $availableLanguages)
            ) {
                return $query_vars['lang'];
            }
        }

        return false;
    }

    public static function getUrlWithLang($url = null, $lang = null, $args = '')
    {
        $defaults = [
            'using_permalinks' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        if (!$url) {
            if (!$url = redirect_canonical($url, false)) {
                $url = is_ssl() ? 'https://' : 'http://';
                $url .= $_SERVER['HTTP_HOST'];
                $url .= $_SERVER['REQUEST_URI'];
            }

            if ($frag = strstr($url, '#')) {
                $url = substr($url, 0, -strlen($frag));
            }

            if ($query = wp_parse_url($url, PHP_URL_QUERY)) {
                parse_str($query, $query_vars);

                foreach (array_keys($query_vars) as $qv) {
                    if (!get_query_var($qv)) {
                        $url = remove_query_arg($qv, $url);
                    }
                }
            }
        }

        $defaultLocale = self::getDefaultLocale();

        if (!$lang) {
            $lang = $defaultLocale;
        }

        $useImplicitLang = apply_filters('rrze_multilang_use_implicit_lang', true);

        $langSlug = ($useImplicitLang && $lang == $defaultLocale)
            ? ''
            : self::langSlug($lang);

        $home = set_url_scheme(get_option('home'));
        $home = trailingslashit($home);

        $url = remove_query_arg('lang', $url);

        if (!$args['using_permalinks']) {
            if ($langSlug) {
                $url = add_query_arg(array('lang' => $langSlug), $url);
            }

            return $url;
        }

        $availableLanguages = array_map([__CLASS__, 'langSlug'], self::availableLocales());

        $tailSlashed = ('/' == substr($url, -1));

        $url = preg_replace(
            '#^' . preg_quote($home) . '((' . implode('|', $availableLanguages) . ')/)?#',
            $home . ($langSlug ? trailingslashit($langSlug) : ''),
            trailingslashit($url)
        );

        if (!$tailSlashed) {
            $url = untrailingslashit($url);
        }

        return $url;
    }

    public static function getLangRegex()
    {
        $langs = array_map([__CLASS__, 'langSlug'], self::availableLocales());
        $langs = array_filter($langs);

        if (empty($langs)) {
            return '';
        }

        return '(' . implode('|', $langs) . ')';
    }

    public static function getLanguageNativeName($locale)
    {
        if ('en_US' == $locale) {
            return 'English (United States)';
        }

        static $availableTranslations = [];

        if (empty($availableTranslations)) {
            $availableTranslations = self::getAvailableTranslations();
        }

        if (isset($availableTranslations[$locale]['native_name'])) {
            return $availableTranslations[$locale]['native_name'];
        }

        return false;
    }
}
