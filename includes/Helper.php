<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Single\Post;

class Helper
{
    /**
     * Get frontend locale information.
     *
     * @return array
     */
    public static function getLocaleInfo()
    {
        global $wp_query;
        if (!isset($wp_query)) {
            return null;
        }

        $options = (object) Options::getOptions();

        $locale = get_locale();

        $availableLanguages = Locale::getAvailableLanguages();

        $translations = [];
        $isSingular = false;

        if (
            is_singular()
            || !empty($wp_query->is_posts_page)
        ) {
            $translations = Post::getPostTranslations(get_queried_object_id());
            $isSingular = true;
        }

        $links = [];

        foreach ($availableLanguages as $code => $name) {
            $nativeName = Locale::getLanguageNativeName($code);

            if (Locale::isLocaleIso639($code)) {
                $nativeName = Locale::getShortName($nativeName);
            }

            $link = [
                'locale' => $code,
                'lang' => Locale::languageTag($code),
                'title' => $name,
                'native_name' => trim($nativeName),
                'lang_tag' => '',
                'href' => ''
            ];

            $defaultPage = $options->default_page ? get_permalink($options->default_page) : false;

            if ($isSingular) {
                if ($locale === $code) {
                    $link['href'] = get_permalink(get_queried_object_id());
                } elseif (
                    !empty($translations[$code])
                    && 'publish' == get_post_status($translations[$code])
                ) {
                    $link['href'] = get_permalink($translations[$code]);
                } elseif ($defaultPage) {
                    $link['href'] = $defaultPage;
                }
            } else {
                $link['href'] = Locale::url(null, $code);
            }

            $link['lang_tag'] = $link['href'] ? Locale::getLangFromUrl($link['href']) : '';

            $links[] = $link;
        }

        return $links;
    }

    /**
     * Debug
     *
     * @param $input
     * @param string $level
     * @return void
     */
    public static function debug($input, string $level = 'i')
    {
        if (!WP_DEBUG) {
            return;
        }
        if (in_array(strtolower((string) WP_DEBUG_LOG), ['true', '1'], true)) {
            $logPath = WP_CONTENT_DIR . '/debug.log';
        } elseif (is_string(WP_DEBUG_LOG)) {
            $logPath = WP_DEBUG_LOG;
        } else {
            return;
        }
        if (is_array($input) || is_object($input)) {
            $input = print_r($input, true);
        }
        switch (strtolower($level)) {
            case 'e':
            case 'error':
                $level = 'Error';
                break;
            case 'i':
            case 'info':
                $level = 'Info';
                break;
            case 'd':
            case 'debug':
                $level = 'Debug';
                break;
            default:
                $level = 'Info';
        }
        error_log(
            date("[d-M-Y H:i:s \U\T\C]")
                . " WP $level: "
                . basename(__FILE__) . ' '
                . $input
                . PHP_EOL,
            3,
            $logPath
        );
    }
}
