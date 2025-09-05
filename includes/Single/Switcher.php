<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Functions;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Single\Post;

/**
 * Class to render a language switcher for single mode.
 * 
 * @package RRZE\Multilang\Single
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
            'echo' => false,
            'title' => '',
        ]);

        wp_enqueue_style('rrze-multilang-frontend');

        $links = self::getLinks();

        $ul = '<ul>';
        $linkfound = false;
        foreach ($links as $link) {
            $languageTag = Locale::languageTag($link['locale']);
            $langSlug = Locale::langSlug($link['locale']);
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
        $output = '<div class="rrze-multilang">' . PHP_EOL;

        if ($linkfound) {
            // Use <nav> and set aria-label
            if ($args['title']) {
                $output .= sprintf('<nav aria-label="%s">', $args['title']) . PHP_EOL;
            } else {
                $output .= sprintf('<nav aria-label="%s">', __('Language Switcher', 'rrze-multilang')) . PHP_EOL;
            }
        } else {
            // Use <div> and set aria-hidden
            $output .= '<div class="notranslation" aria-hidden="true" role="presentation">' . PHP_EOL;
        }


        $output .= $ul . PHP_EOL;
        if ($linkfound) {
            $output .= '</nav>' . PHP_EOL;
        } else {
            $output .= '</div>' . PHP_EOL;
        }

        $output .= '</div>' . PHP_EOL;

        $output = apply_filters('rrze_multilang_language_switcher', $output, $args);

        if ($args['echo']) {
            echo $output;
        } else {
            return $output;
        }
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

        $options = (object) Options::getOptions();
        $locale = get_locale();
        $availableLanguages = Locale::getAvailableLanguages();

        $translations = [];
        $isSingular = false;

        $queriedId = $postId ?: get_queried_object_id();
        $queriedPostType = $queriedId ? get_post_type($queriedId) : null;

        $studiengang = self::getStudiengang();

        if ($queriedId && (is_singular() || !empty($wp_query->is_posts_page))) {
            $translations = Post::getPostTranslations($queriedId);
            $isSingular = true;
        }

        $links = [];

        $defaultPage = !empty($options->default_page) ? get_permalink($options->default_page) : false;
        $isLocalizablePostType = $queriedPostType ? Post::isLocalizablePostType($queriedPostType) : false;

        foreach ($availableLanguages as $code => $name) {
            $nativeName = Locale::getLanguageNativeName($code);
            if (Locale::isLocaleIso639($code)) {
                $nativeName = Locale::getShortName($nativeName);
            }

            $postId = $translations[$code]->ID ?? ($code === Locale::getDefaultLocale() ? $queriedId : 0);
            $postStatus = !empty($translations[$code]) ? get_post_status($translations[$code]) : '';

            $link = [
                'post_id'     => $postId,
                'post_status' => $postStatus,
                'locale'      => $code,
                'lang'        => Locale::languageTag($code),
                'title'       => $name,
                'native_name' => trim($nativeName),
                'href'        => ''
            ];

            if ($isSingular && empty($studiengang)) {
                if ($locale === $code) {
                    $link['href'] = $queriedId ? get_permalink($queriedId) : '';
                } elseif (!empty($translations[$code]) && (!$onlyPublished || get_post_status($translations[$code]) === 'publish')) {
                    $link['href'] = get_permalink($translations[$code]);
                } elseif ($defaultPage && $isLocalizablePostType) {
                    $link['href'] = $defaultPage;
                }
            } elseif ($isSingular && !empty($studiengang)) {
                $postNameKey = (substr($code, 0, 2) === 'de') ? 'post_name' : 'post_name_en';
                if (!empty($studiengang[$postNameKey])) {
                    $link['href'] = trailingslashit(Locale::url(get_site_url(), $code)) . 'studiengang/' . $studiengang[$postNameKey] . '/';
                }
            } else {
                $link['href'] = Locale::url(null, $code);
            }

            $links[] = $link;
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
