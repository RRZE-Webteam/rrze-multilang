<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Single\Post;

class Helper
{
    /**
     * Check if the plugin is in multilang mode.
     *
     * @return bool
     */
    public static function hasMultilangMode()
    {
        $options = (object) Options::getOptions();
        return (bool) $options->multilang_mode;
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
                    $postId = get_queried_object_id();
                    $link['post_id'] = $postId;
                    $link['href'] = get_permalink($postId);
                } elseif (
                    !empty($translations[$code])
                    && 'publish' == get_post_status($translations[$code])
                ) {
                    $post = $translations[$code];
                    $link['post_id'] = $post->ID;
                    $link['href'] = get_permalink($post->ID);
                }
            }

            $link['lang_tag'] = $link['href'] ? Locale::getLangFromUrl($link['href']) : '';

            $links[$code] = $link;
        }

        return $links;
    }
}
