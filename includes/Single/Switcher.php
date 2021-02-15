<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

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

        $links = self::getLinks($args);
        $total = count($links);
        $count = 0;

        $output = sprintf('<nav role="navigation" aria-label="%s">', __('Language Switcher', 'rrze-multilang')) . PHP_EOL;

        foreach ($links as $link) {
            $count += 1;
            $class = [];
            $class[] = Locale::languageTag($link['locale']);
            $class[] = Locale::langSlug($link['locale']);

            if (get_locale() === $link['locale']) {
                continue;
            }

            if (1 == $count) {
                $class[] = 'first';
            }

            if ($total == $count) {
                $class[] = 'last';
            }

            $class = implode(' ', array_unique($class));

            $label = $link['native_name'] ? $link['native_name'] : $link['title'];
            $title = $link['title'];

            if (empty($link['href'])) {
                $li = esc_html($label);
            } else {
                $atts = [
                    'rel' => 'alternate',
                    'hreflang' => $link['lang'],
                    'href' => esc_url($link['href']),
                    'title' => esc_attr($title)
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

            $li = sprintf('<li class="%1$s">%2$s</li>', $class, $li);

            $output .= $li . PHP_EOL;
        }

        $output = '<ul class="rrze-multilang-language-switcher">' . $output . '</ul>' . PHP_EOL;
        $output .= '</nav>' . PHP_EOL;

        $output = apply_filters('rrze_multilang_language_switcher', $output, $args);

        if ($args['echo']) {
            echo $output;
        } else {
            return $output;
        }
    }

    protected static function getLinks($args = '')
    {
        global $wp_query;

        $args = wp_parse_args($args, []);

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

            if ($isSingular) {
                if ($locale === $code) {
                    $link['href'] = get_permalink(get_queried_object_id());
                } elseif (
                    !empty($translations[$code])
                    && 'publish' == get_post_status($translations[$code])
                ) {
                    $link['href'] = get_permalink($translations[$code]);
                }
            } else {
                $link['href'] = Locale::url(null, $code);
            }

            $links[] = $link;
        }

        return $links;
    }
}
