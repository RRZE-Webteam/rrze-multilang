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

        $nav = sprintf('<nav aria-label="%s">', __('Language Switcher', 'rrze-multilang'));

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
            }

            $class = implode(' ', array_unique($class));

            $label = $link['native_name'] ? $link['native_name'] : $link['title'];

            if (empty($link['href'])) {
                $li = esc_html($label);
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

            $nav .= $li;
        }

        $nav .= '</nav>';

        $output = '<div class="rrze-multilang"><ul class="language-switcher">' . PHP_EOL;
        $output .= $nav . PHP_EOL;
        $output .= '</ul></div>' . PHP_EOL;

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

        $currentBlogId = get_current_blog_id();
        $options = (object) Options::getOptions();
        $siteOptions = (object) Options::getSiteOptions();

        $connections = $siteOptions->connections[$currentBlogId];
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

        $links = [];

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
            if (!Functions::isBlogPublic($blogId)) {
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

            if ($isSingular && $refPostId) {
                if (!in_array($postType, $refOptions->post_types)) {
                    $link['href'] = $defaultPage ? $defaultPage : '';
                } elseif ('publish' == $refStatus) {
                    $link['href'] = $refPermalink ? $refPermalink : '';
                }
            } else {
                $link['href'] = $defaultPage ? $defaultPage : '';
            }

            $links[$refLocale] = $link;
        }

        return $links;
    }
}
