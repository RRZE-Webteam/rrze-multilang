<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Single\Post;

class Helper
{
    /**
     * Check if the plugin is in single multilang mode.
     *
     * @return bool
     */
    public static function isSingleMultilangMode()
    {
        $options = (object) Options::getOptions();
        return $options->multilang_mode == 1;
    }

    /**
     * Check if the post has translation.
     */
    public static function hasTranslation($postId = 0)
    {
        $postType = get_post_type($postId);

        if (!Post::isLocalizablePostType($postType)) {
            return false;
        }

        $locale = get_post_meta($postId, '_rrze_multilang_single_locale', true);

        if (empty($locale)) {
            return false;
        }

        if ($locale != get_locale()) {
            return false;
        }

        return true;
    }

    /**
     * Get post translations.
     *
     * @param integer $postId
     * @return array|bool
     */
    public static function getPostTranslations($postId = 0)
    {
        return Post::getPostTranslations($postId);
    }

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
                'href' => '',
                'default' => $options->default_page ? get_permalink($options->default_page) : ''
            ];

            if ($isSingular) {
                if ($locale === $code) {
                    $link['href'] = get_permalink(get_queried_object_id());
                } elseif (
                    !empty($translations[$code])
                    && 'publish' == get_post_status($translations[$code])
                ) {
                    $link['href'] = get_permalink($translations[$code]);
                }
            }

            $link['lang_tag'] = $link['href'] ? Locale::getLangFromUrl($link['href']) : '';

            $links[$code] = $link;
        }

        return $links;
    }
}
