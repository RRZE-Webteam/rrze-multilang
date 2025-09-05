<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Single\Post;
use RRZE\Multilang\Single\Switcher as SingleSwitcher;
use RRZE\Multilang\Multiple\Switcher as MultipleSwitcher;

/**
 * Helper functions for the RRZE Multilang plugin.
 * 
 * @package RRZE\Multilang
 */
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
     * Get translations for the current post or a given post ID.
     *
     * @param int $postId Optional explicit post ID. If 0, falls back to queried object.
     * @param bool $onlyPublished Whether to include only published posts. Default true.
     * @return array Array of translation link data (possibly empty).
     */
    public static function getTranslations(int $postId = 0, bool $onlyPublished = true): array
    {
        $queriedId = $postId ?: (int) get_queried_object_id();
        $post = get_post($queriedId);
        if (!$post instanceof \WP_Post) {
            return [];
        }

        $options = (object) Options::getOptions();
        $mode = isset($options->multilang_mode) ? (int) $options->multilang_mode : 0;

        if ($mode === 1) {
            $translations = SingleSwitcher::getLinks($queriedId, $onlyPublished);
            return is_array($translations) ? $translations : [];
        }

        if ($mode === 2 && is_multisite()) {
            $translations = MultipleSwitcher::getLinks($queriedId, $onlyPublished);
            return is_array($translations) ? $translations : [];
        }

        return [];
    }

    /**
     * Get the translated post ID for a given post in a given language.
     *
     * Rules:
     * - If $postid is invalid or does not exist => return false
     * - If $lang is null => use get_locale()
     * - If the post itself is already in $lang => return $postid
     * - If no exact translation exists but a translation in the same base language exists (e.g. en_GB for en_US) => return that ID
     * - If no translation exists in $lang => return 0
     *
     * @param int         $postid  Base post ID
     * @param string|null $lang    Locale string (e.g. en_US). If null, falls back to get_locale()
     * @param bool        $onlyPublished If true, only return published posts. If false, return any status.
     * @return int|bool   Translated post ID, 0 if no translation, false if invalid input
     */
    public static function getPostIdTranslation(int $postid, ?string $lang = null, bool $onlyPublished = true): int|bool
    {
        // Validate the base post
        $post = get_post($postid);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        // Normalize target locale
        $targetLocale = Locale::normalizeLocale($lang ?: get_locale());
        $targetBase = Locale::localeBase($targetLocale);

        // Collect translations using the existing helper
        $translations = self::getTranslations($postid, $onlyPublished);
        if (!is_array($translations)) {
            return 0;
        }

        foreach ($translations as $t) {
            if ($t['lang'] === $lang) {
                return $t['post_id'];
            }
        }

        // Fallback: same base language (e.g. en_US <-> en_GB)
        foreach ($translations as $t) {
            if (Locale::localeBase($t['lang']) === $targetBase) {
                return $t['post_id'];
            }
        }

        return 0;
    }

    /**
     * Get post translations.
     * Only for Single mode.
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
     * Only for Single mode.
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
                'post_id' => $translations[$code]->ID,
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
