<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Functions;
use RRZE\Multilang\Locale;

class Switcher
{
    public static function languageSwitcher($args = '')
    {
        $args = wp_parse_args($args, [
            'echo' => false
        ]);

        wp_enqueue_style('rrze-multilang-frontend');

        $links = self::getLinks();
        ksort($links, SORT_NATURAL);

        $ul = '<ul>';
        $linkfound = false;
        foreach ($links as $link) {
            $languageTag = Locale::languageTag($link['locale']);
            $langSlug = Locale::langSlug($link['locale']);

            $class = [];
            $class[] = $languageTag;
            $class[] = $langSlug;

            if (get_locale() === $link['locale']) {
                $class[] = 'current';
            }

            if (empty($link['href'])) {
                $class[] = 'notranslation';
            } else {
                $linkfound = true;
            }

            $class = implode(' ', array_unique($class));

            $label = $link['native_name'] ? $link['native_name'] : $link['title'];

            if (empty($link['href'])) {
                $li = '<span data-lang="' . esc_attr($langSlug) . '">' . esc_html($label) . '</span>';
            } else {
                $atts = [
                    'rel' => 'alternate',
                    'hreflang' => $langSlug,
                    'href' => esc_url($link['href'])
                ];

                if (get_locale() === $link['locale']) {
                    $atts += [
                        'class' => 'current',
                        'aria-current' => 'page',
                    ];
                }

                $li = sprintf(
                    '<a %1$s>%2$s</a>',
                    Functions::formatAtts($atts),
                    esc_html($label)
                );
            }

            $li = sprintf('<li class="%1$s" lang="%2$s">%3$s</li>', $class, $langSlug, $li);

            $ul .= $li;
        }
        $ul .= '</ul>';
        $output = '<div class="rrze-multilang">' . PHP_EOL;


        if ($linkfound) {
            // use <nav> and set aria-label
            if ($args['title']) {
                $output .= sprintf('<nav aria-label="%s">', $args['title']) . PHP_EOL;
            } else {
                $output .= sprintf('<nav aria-label="%s">', __('Language Switcher', 'rrze-multilang')) . PHP_EOL;
            }
        } else {
            // use <div> and set aria-hidden
            if ($args['title']) {
                $output .= sprintf('<div class="notranslation" aria-hidden="true" role="presentation" title="%s">', $args['title']) . PHP_EOL;
            } else {
                $output .= sprintf('<div class="notranslation" aria-hidden="true" role="presentation" title="%s">', __('Language Switcher', 'rrze-multilang')) . PHP_EOL;
            }
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

    protected static function getLinks()
    {
        global $wp_query;

        $links = [];
        $currentBlogId = get_current_blog_id();
        $options = (object) Options::getOptions();
        $siteOptions = (object) Options::getSiteOptions();

        $connections = !empty($siteOptions->connections[$currentBlogId])
            ? $siteOptions->connections[$currentBlogId]
            : [];

        $isMain = $options->connection_type == 1 ? true : false;
        if (!$isMain) {
            $mainBlogId = array_shift($connections);
            $connections = !empty($siteOptions->connections[$mainBlogId])
                ? array_merge($siteOptions->connections[$mainBlogId], [$mainBlogId])
                : [$mainBlogId];
            $connections = array_unique($connections);
            $key = array_search($currentBlogId, $connections);
            if ($key !== false) {
                unset($connections[$key]);
            }
        }

        if (empty($connections)) {
            return $links;
        }

        $postId = 0;
        $postType = null;
        $reference = [];
        $isSingular = false;

        if (
            is_singular()
            || !empty($wp_query->is_posts_page)
        ) {
            $post = get_post();
            $postId = $post->ID;
            $postType = get_post_type();
            $reference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
            $isSingular = true;
        }

        if (!$isMain && is_array($reference)) {
            $refBlogId = array_key_first($reference);
            $refPostId = isset($reference[$refBlogId]) ? $reference[$refBlogId] : 0;
            if ($refPostId) {
                switch_to_blog($refBlogId);
                $remoteRef = get_post_meta($refPostId, '_rrze_multilang_multiple_reference', true);
                $reference = is_array($remoteRef) ? $reference + $remoteRef : $reference;
                restore_current_blog();
            }
        }

        if (isset($reference[$currentBlogId])) {
            unset($reference[$currentBlogId]);
        }

        foreach ($connections as $blogId) {
            if (!Functions::isBlogAvailable($blogId)) {
                continue;
            }
            $refPostId = 0;
            if (!empty($reference[$blogId])) {
                $refPostId = $reference[$blogId];
            }
            switch_to_blog($blogId);
            $refOptions = (object) Options::getOptions();
            $defaultPage = $refOptions->default_page ? get_permalink($refOptions->default_page) : 0;
            $refLocale = Locale::getDefaultLocale();
            $refStatus = get_post_status($refPostId);
            $refPermalink = get_permalink($refPostId);
            restore_current_blog();

            $nativeName = Locale::getLanguageNativeName($refLocale);

            if (Locale::isLocaleIso639($refLocale)) {
                $nativeName = Locale::getShortName($nativeName);
            }

            $link = [
                'locale' => $refLocale,
                'lang' => Locale::languageTag($refLocale),
                'title' => esc_html($nativeName),
                'native_name' => esc_html($nativeName),
                'href' => '',
            ];

            $studiengang = self::getStudiengang();

            if ($isSingular && $refPostId) {
                if (!in_array($postType, $refOptions->post_types)) {
                    $link['href'] = $defaultPage ? $defaultPage : '';
                } elseif ('publish' == $refStatus) {
                    $link['href'] = $refPermalink ? $refPermalink : '';
                }
            } elseif ($isSingular && !empty($studiengang)) {
                switch_to_blog($blogId);
                $options = (object) Options::getOptions();
                $isMain = $options->connection_type == 1 ? true : false;
                $siteUrl = get_site_url();
                restore_current_blog();
                if ($isMain) {
                    $link['href'] = $siteUrl . '/studiengang/' . $studiengang['post_name'] . '/';
                } else {
                    $link['href'] = $siteUrl . '/studiengang/' . $studiengang['post_name_en'] . '/';
                }
            } else {
                $link['href'] = $defaultPage ? $defaultPage : '';
            }

            $links[$refLocale] = $link;
        }

        return $links;
    }

    protected static function getStudiengang()
    {
        $studiengang = [];
        $postType = get_post_type();
        if ($postType === 'studiengang') {
            $post = get_post();
            if ($post) {
                $postId = $post->ID;
                $postName = $post->post_name;
                $postNameEn = get_post_meta($postId, 'post_name_en', true);
                $studiengang = [
                    'post_name' => $postName,
                    'post_name_en' => $postNameEn
                ];
            }
        }
        return $studiengang;
    }
}
