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

        $links = self::getLinks();

        $nav = sprintf('<nav aria-label="%s">', __('Language Switcher', 'rrze-multilang'));

        foreach ($links as $link) {
            $languageTag = Locale::languageTag($link['locale']);
            $langSlug = Locale::langSlug($link['locale']);

            $class = [];
            $class[] = $languageTag;
            $class[] = $langSlug;

            if (get_locale() === $link['locale']) {
                continue;
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

            $li = sprintf('<li class="%1$s">%2$s</li>', $class, $li);

            $nav .= $li;
        }

        $nav .= '</nav>';

        $output = '<div class="rrze-multilang"> <ul class="language-switcher">' . PHP_EOL;
        $output .= $nav . PHP_EOL;
        $output .= '</ul> </div>' . PHP_EOL;

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
