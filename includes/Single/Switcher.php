<?php

namespace RRZE\Multilang\Single;

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

        $ul = '<ul>';

        foreach ($links as $link) {
            $languageTag = Locale::languageTag($link['locale']);
            $langSlug = Locale::langSlug($link['locale']);

            $class = [];
            $class[] = $languageTag;
            $class[] = $langSlug;

            if (get_locale() === $link['locale']) {
                continue;
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

            $ul .= $li;
        }

        $output = '<div class="rrze-multilang"><ul class="language-switcher">' . PHP_EOL;
        $output .= sprintf('<nav aria-label="%s">', __('Language Switcher', 'rrze-multilang')) . PHP_EOL;
        $output .= $ul . PHP_EOL;
        $output .= '</nav>' . PHP_EOL;
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
                'href' => '',
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

            $links[] = $link;
        }

        return $links;
    }
}
