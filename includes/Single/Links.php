<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Locale;

class Links
{
    public function __construct()
    {
        add_filter('post_link', [$this, 'postLink'], 10, 3);
        add_filter('page_link', [$this, 'pageLink'], 10, 3);
        add_filter('post_type_link', [$this, 'postTypeLink'], 10, 4);

        add_filter('year_link', [$this, 'yearLink'], 10, 2);
        add_filter('month_link', [$this, 'monthLink'], 10, 3);
        add_filter('day_link', [$this, 'dayLink'], 10, 4);
        add_filter('feed_link', [$this, 'feedLink'], 10, 2);
        add_filter('author_feed_link', [$this, 'authorFeedLink'], 10, 2);
        add_filter('category_feed_link', [$this, 'categoryFeedLink'], 10, 2);
        add_filter('taxonomy_feed_link', [$this, 'taxonomyFeedLink'], 10, 3);
        add_filter('post_type_archive_link', [$this, 'postTypeArchiveLink'], 10, 2);
        add_filter('post_type_archive_feed_link', [$this, 'postTypeArchiveFeedLink'], 10, 2);
        add_filter('term_link', [$this, 'termLink'], 10, 3);

        add_filter('home_url', [$this, 'homeUrl'], 10, 1);
        add_action('wp_head', [$this, 'm17nHeaders']);

        add_filter('get_previous_post_join', [$this, 'adjacentPostJoin'], 10, 3);
        add_filter('get_next_post_join', [$this, 'adjacentPostJoin'], 10, 3);

        add_filter('get_previous_post_where', [$this, 'adjacentPostWhere'], 10, 3);
        add_filter('get_next_post_where', [$this, 'adjacentPostWhere'], 10, 3);
    }

    public function postLink($permalink, $post, $leavename)
    {
        if (!Post::isLocalizablePostType($post->post_type)) {
            return $permalink;
        }

        $locale = Post::getPostLocale($post->ID);
        $sample = (isset($post->filter) && 'sample' == $post->filter);
        $permalinkStructure = get_option('permalink_structure');

        $usingPermalinks = $permalinkStructure &&
            ($sample || !in_array($post->post_status, ['draft', 'pending', 'auto-draft']));

        $permalink = Locale::getUrlWithLang(
            $permalink,
            $locale,
            ['using_permalinks' => $usingPermalinks]
        );

        return $permalink;
    }

    public function pageLink($permalink, $id, $sample)
    {
        if (!Post::isLocalizablePostType('page')) {
            return $permalink;
        }

        $locale = Post::getPostLocale($id);
        $post = get_post($id);

        if ('page' == get_option('show_on_front')) {
            $front_page_id = get_option('page_on_front');

            if ($id == $front_page_id) {
                return $permalink;
            }

            $translation = Post::getPostTranslation($front_page_id, $locale);

            if ($translation && $translation->ID === $id) {
                $home = set_url_scheme(get_option('home'));
                $home = trailingslashit($home);
                return Locale::url($home, $locale);
            }
        }

        $permalinkStructure = get_option('permalink_structure');

        $usingPermalinks = $permalinkStructure &&
            ($sample || !in_array($post->post_status, ['draft', 'pending', 'auto-draft']));

        $permalink = Locale::getUrlWithLang(
            $permalink,
            $locale,
            ['using_permalinks' => $usingPermalinks]
        );

        return $permalink;
    }

    public function postTypeLink($permalink, $post, $leavename, $sample)
    {
        if (!Post::isLocalizablePostType($post->post_type)) {
            return $permalink;
        }

        $locale = Post::getPostLocale($post->ID);
        $permalinkStructure = get_option('permalink_structure');

        $usingPermalinks = $permalinkStructure &&
            ($sample || !in_array($post->post_status, ['draft', 'pending', 'auto-draft']));

        $permalink = Locale::getUrlWithLang(
            $permalink,
            $locale,
            ['using_permalinks' => $usingPermalinks]
        );

        return $permalink;
    }

    public function yearLink($link, $year)
    {
        return Locale::url($link);
    }

    public function monthLink($link, $year, $month)
    {
        return Locale::url($link);
    }

    public function dayLink($link, $year, $month, $day)
    {
        return Locale::url($link);
    }

    public function feedLink($link, $feed)
    {
        return Locale::url($link);
    }

    public function authorFeedLink($link, $feed)
    {
        return Locale::url($link);
    }

    public function categoryFeedLink($link, $feed)
    {
        return Locale::url($link);
    }

    public function taxonomyFeedLink($link, $feed, $taxonomy)
    {
        return Locale::url($link);
    }

    public function postTypeArchiveLink($link, $postType)
    {
        return Locale::url($link);
    }

    public function postTypeArchiveFeedLink($link, $feed)
    {
        return Locale::url($link);
    }

    public function termLink($link, $term, $taxonomy)
    {
        return Locale::url($link);
    }

    public function homeUrl($url)
    {
        if (
            is_admin()
            || !did_action('template_redirect')
        ) {
            return $url;
        }

        return Locale::url($url);
    }

    public function m17nHeaders()
    {
        $languages = [];

        if (is_singular()) {
            $postId = get_queried_object_id();

            if (
                $postId
                && $translations = Post::getPostTranslations($postId)
            ) {
                $locale = get_locale();
                $translations[$locale] = get_post($postId);

                foreach ($translations as $lang => $translation) {
                    $languages[] = [
                        'hreflang' => Locale::languageTag($lang),
                        'href' => get_permalink($translation),
                    ];
                }
            }
        } else {
            $available_locales = Locale::availableLocales();

            foreach ($available_locales as $locale) {
                $languages[] = [
                    'hreflang' => Locale::languageTag($locale),
                    'href' => Locale::url(null, $locale),
                ];
            }
        }

        $languages = apply_filters('rrze_multilang_rel_alternate_hreflang', $languages);

        foreach ((array) $languages as $language) {
            $hreflang = isset($language['hreflang']) ? $language['hreflang'] : '';
            $href = isset($language['href']) ? $language['href'] : '';

            if ($hreflang && $href) {
                $link = sprintf(
                    '<link rel="alternate" hreflang="%1$s" href="%2$s" />',
                    esc_attr($hreflang),
                    esc_url($href)
                );

                echo $link . "\n";
            }
        }
    }

    public function adjacentPostJoin($join, $in_same_term, $excluded_terms)
    {
        global $wpdb;

        $post = get_post();

        if (
            $post
            && Post::isLocalizablePostType(get_post_type($post))
        ) {
            $join .= " LEFT JOIN $wpdb->postmeta AS postmeta_locale ON (p.ID = postmeta_locale.post_id AND postmeta_locale.meta_key = '_rrze_multilang_single_locale')";
        }

        return $join;
    }

    public function adjacentPostWhere($where, $in_same_term, $excluded_terms)
    {
        global $wpdb;

        $post = get_post();

        if (
            $post
            && Post::isLocalizablePostType(get_post_type($post))
        ) {
            $locale = Post::getPostLocale($post->ID);

            $where .= " AND (1=0";
            $where .= $wpdb->prepare(" OR postmeta_locale.meta_value LIKE %s", $locale);

            if (Locale::isDefaultLocale($locale)) {
                $where .= " OR postmeta_locale.meta_id IS NULL";
            }

            $where .= ")";
        }

        return $where;
    }
}
