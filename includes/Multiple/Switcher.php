<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Functions;
use RRZE\Multilang\Locale;

/**
 * Class to render a language switcher for multiple mode.
 * 
 * @package RRZE\Multilang\Multiple
 */
class Switcher
{
    /**
     * Render a language switcher.
     * 
     * @param array|string $args Arguments for the switcher. Accepts 'echo' (bool) and 'title' (string).
     * @return string|null The HTML output of the language switcher, or null if echoed.
     */
    public static function languageSwitcher($args = '')
    {
        $args = wp_parse_args($args, [
            'echo'  => false,
            'title' => '',
        ]);

        wp_enqueue_style('rrze-multilang-frontend');

        $links = self::getLinks();
        ksort($links, SORT_NATURAL);

        $ul = '<ul>';
        $linkfound = false;

        foreach ($links as $link) {
            $languageTag = Locale::languageTag($link['locale']); // e.g., de-DE
            $langSlug = Locale::langSlug($link['locale']);    // e.g., de

            $class = [$languageTag, $langSlug];

            if (get_locale() === $link['locale']) {
                continue;
            }

            if (empty($link['href'])) {
                $class[] = 'notranslation';
            } else {
                $linkfound = true;
            }

            $class = implode(' ', array_map('sanitize_html_class', array_unique($class)));

            $label = $link['native_name'] ? $link['native_name'] : $link['title'];

            if (empty($link['href'])) {
                $li = '<span data-lang="' . esc_attr($langSlug) . '">' . esc_html($label) . '</span>';
            } else {
                $atts = [
                    'rel' => 'alternate',
                    'hreflang' => $languageTag,
                    'href' => esc_url($link['href'])
                ];

                $li = sprintf(
                    '<a %1$s>%2$s</a>',
                    Functions::formatAtts($atts),
                    esc_html($label)
                );
            }

            $li = sprintf(
                '<li class="%1$s" lang="%2$s">%3$s</li>',
                esc_attr($class),
                esc_attr($langSlug),
                $li
            );

            $ul .= $li;
        }

        $ul .= '</ul>';
        $output  = '<div class="rrze-multilang">' . PHP_EOL;

        if ($linkfound) {
            $output .= sprintf(
                '<nav aria-label="%s">',
                $args['title'] ? esc_attr($args['title']) : esc_attr(__('Language Switcher', 'rrze-multilang'))
            ) . PHP_EOL;
        } else {
            $output .= '<div class="notranslation" aria-hidden="true" role="presentation">' . PHP_EOL;
        }

        $output .= $ul . PHP_EOL;
        $output .= $linkfound ? '</nav>' . PHP_EOL : '</div>' . PHP_EOL;
        $output .= '</div>' . PHP_EOL;

        $output = apply_filters('rrze_multilang_language_switcher', $output, $args);

        if (!empty($args['echo'])) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Get translations for the current post or a given post ID.
     * 
     * @param integer $postId Optional post ID to get translations for. Defaults to the current queried object.
     * @param boolean $onlyPublished Whether to include only published posts. Default true.
     * @return array An array of translation links.
     */
    public static function getLinks(int $postId = 0, bool $onlyPublished = true): array
    {
        global $wp_query;

        $links = [];
        $currentBlogId = get_current_blog_id();
        $locale = get_locale();
        $options = (object) Options::getOptions();
        $siteOptions = (object) Options::getSiteOptions();

        $queriedId = $postId ?: get_queried_object_id();
        $queriedPostType = $queriedId ? get_post_type($queriedId) : null;

        $connections = !empty($siteOptions->connections[$currentBlogId])
            ? (array) $siteOptions->connections[$currentBlogId]
            : [];

        $isMain = ((int) $options->connection_type === 1);

        if (!$isMain) {
            $mainBlogId = (int) array_shift($connections);
            $mainSet    = !empty($siteOptions->connections[$mainBlogId])
                ? (array) $siteOptions->connections[$mainBlogId]
                : [];

            $connections = array_unique(array_map('intval', array_merge($mainSet, [$mainBlogId])));

            // $key = array_search($currentBlogId, $connections, true);
            // if ($key !== false) {
            //     unset($connections[$key]);
            // }
        }

        if (empty($connections)) {
            return $links;
        }

        array_unshift($connections, $currentBlogId);

        $postId      = 0;
        $postType    = null;
        $reference   = [];
        $isSingular  = false;
        $studiengang = self::getStudiengang();

        if ($queriedId) {
            $post = get_post($queriedId);
            if ($post instanceof \WP_Post) {
                $postId   = (int) $post->ID;
                $postType = get_post_type($post);
                $reference = (array) get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
                $isSingular = true;
            }
        } elseif (is_singular() || !empty($wp_query->is_posts_page)) {
            $post = get_post();
            if ($post instanceof \WP_Post) {
                $postId    = (int) $post->ID;
                $postType  = get_post_type($post);
                $reference = (array) get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
                $isSingular = true;
            }
        }

        if (!$isMain && !empty($reference) && is_array($reference)) {
            $refBlogId = (int) array_key_first($reference);
            $refPostId = (int) ($reference[$refBlogId] ?? 0);

            if ($refBlogId && $refPostId) {
                switch_to_blog($refBlogId);
                $remoteRef = get_post_meta($refPostId, '_rrze_multilang_multiple_reference', true);
                restore_current_blog();

                if (is_array($remoteRef)) {
                    $reference = $reference + $remoteRef;
                }
            }
        }

        if (empty($reference) && empty($studiengang)) {
            return $links;
        }

        if (isset($reference[$currentBlogId])) {
            unset($reference[$currentBlogId]);
        }

        foreach ($connections as $blogId) {
            $blogId = (int) $blogId;

            if (!Functions::isBlogAvailable($blogId)) {
                continue;
            }

            $refPostId = (int) ($reference[$blogId] ?? 0);

            switch_to_blog($blogId);

            $refOptions   = (object) Options::getOptions();
            $defaultPage  = !empty($refOptions->default_page) ? get_permalink((int) $refOptions->default_page) : '';
            $refLocale    = Locale::getDefaultLocale();
            $refStatus    = $refPostId ? get_post_status($refPostId) : false;
            $refPermalink = $refPostId ? get_permalink($refPostId) : '';
            $siteUrl      = get_site_url();

            restore_current_blog();

            $nativeName = Locale::getLanguageNativeName($refLocale);
            if (Locale::isLocaleIso639($refLocale)) {
                $nativeName = Locale::getShortName($nativeName);
            }

            $postId = $refLocale === Locale::getDefaultLocale() ? $queriedId : $refPostId;
            $postStatus = $refLocale === Locale::getDefaultLocale() ? get_post_status($postId) : $refStatus;

            $link = [
                'blog_id'     => $blogId,
                'post_id'     => $postId,
                'post_status' => $postStatus,
                'locale'      => $refLocale,
                'lang'        => Locale::languageTag($refLocale),
                'title'       => $nativeName,
                'native_name' => $nativeName,
                'href'        => '',
            ];

            if ($isSingular && $refPostId) {
                if (!empty($refOptions->post_types) && is_array($refOptions->post_types) && !in_array($postType, $refOptions->post_types, true)) {
                    $link['href'] = $defaultPage ?: '';
                } elseif (!$onlyPublished || $refStatus === 'publish') {
                    $link['href'] = $refPermalink ?: '';
                }
            } elseif ($isSingular && !empty($studiengang)) {
                $postNameKey = (substr(Locale::getDefaultLocale(), 0, 2) === 'de') ? 'post_name' : 'post_name_en';
                if (!empty($studiengang[$postNameKey])) {
                    $link['href'] = trailingslashit($siteUrl) . 'studiengang/' . $studiengang[$postNameKey] . '/';
                } else {
                    $link['href'] = $defaultPage ?: '';
                }
            } else {
                $link['href'] = $defaultPage ?: '';
            }

            $links[$refLocale] = $link;
        }

        return $links;
    }

    /**
     * Get studiengang post names for URL construction.
     * 
     * @return array Associative array with 'post_name' and 'post_name_en' keys.
     */
    protected static function getStudiengang()
    {
        if (get_post_type() !== 'studiengang') {
            return [];
        }
        $post = get_post();
        if (!$post instanceof \WP_Post) {
            return [];
        }

        $postName   = sanitize_title($post->post_name);
        $postNameEn = sanitize_title((string) get_post_meta($post->ID, 'post_name_en', true));

        return [
            'post_name'     => $postName,
            'post_name_en'  => $postNameEn,
        ];
    }
}
