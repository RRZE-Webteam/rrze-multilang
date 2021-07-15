<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Locale;

class Query
{
    public function __construct()
    {
        add_action('parse_query', [$this, 'parseQuery'], 10, 1);
        add_filter('posts_join', [$this, 'postsJoin'], 10, 2);
        add_filter('posts_where', [$this, 'postsWhere'], 10, 2);
        add_filter('option_sticky_posts', [$this, 'optionStickyPosts'], 10, 1);
        add_filter('option_page_on_front', [$this, 'getLocalPost'], 10, 1);
        add_filter('option_page_for_posts', [$this, 'getLocalPost'], 10, 1);
    }

    public function parseQuery($query)
    {
        $qv = &$query->query_vars;

        if (!empty($qv['rrze_multilang_suppress_locale_query'])) {
            return;
        }

        if (
            $query->is_preview()
            && ($qv['page_id'] || $qv['p'])
        ) {
            $qv['rrze_multilang_suppress_locale_query'] = true;
            return;
        }

        if (
            isset($qv['post_type'])
            && 'any' != $qv['post_type']
        ) {
            $localizable = array_filter(
                (array) $qv['post_type'],
                '\RRZE\Multilang\Single\Post::isLocalizablePostType'
            );

            if (empty($localizable)) {
                $qv['rrze_multilang_suppress_locale_query'] = true;
                return;
            }
        }

        $lang = isset($qv['lang']) ? $qv['lang'] : '';

        if (is_admin()) {
            $locale = $lang;
        } else {
            if ($lang) {
                $locale = Locale::getClosestLocale($lang);
            } else {
                $locale = get_locale();
            }

            if (empty($locale)) {
                $locale = Locale::getDefaultLocale();
            }
        }

        if (
            empty($locale)
            || !Locale::isAvailableLocale($locale)
        ) {
            $qv['rrze_multilang_suppress_locale_query'] = true;
            return;
        }

        $qv['lang'] = $locale;

        if (is_admin()) {
            return;
        }

        if (
            $query->is_home
            && 'page' == get_option('show_on_front')
            && get_option('page_on_front')
        ) {
            $query_keys = array_keys(wp_parse_args($query->query));
            $query_keys = array_diff(
                $query_keys,
                ['preview', 'page', 'paged', 'cpage', 'lang']
            );

            if (empty($query_keys)) {
                $query->is_page = true;
                $query->is_home = false;
                $qv['page_id'] = get_option('page_on_front');

                if (!empty($qv['paged'])) {
                    $qv['page'] = $qv['paged'];
                    unset($qv['paged']);
                }
            }
        }

        if ('' != $qv['pagename']) {
            $query->queried_object = Post::getPageByPath($qv['pagename'], $locale);

            if (!empty($query->queried_object)) {
                $query->queried_object_id = (int) $query->queried_object->ID;
            } else {
                unset($query->queried_object);
                unset($query->queried_object_id);
            }

            if (
                'page' == get_option('show_on_front')
                && isset($query->queried_object_id)
                && $query->queried_object_id == get_option('page_for_posts')
            ) {
                $query->is_page = false;
                $query->is_home = true;
                $query->is_posts_page = true;
            }
        }

        if (
            isset($qv['post_type'])
            && 'any' != $qv['post_type']
            && !is_array($qv['post_type'])
            && '' != $qv['name']
        ) {
            $query->queried_object = Post::getPageByPath(
                $qv['name'],
                $locale,
                $qv['post_type']
            );

            if (!empty($query->queried_object)) {
                $query->queried_object_id = (int) $query->queried_object->ID;
            } else {
                unset($query->queried_object);
                unset($query->queried_object_id);
            }
        }

        if (
            $query->is_posts_page
            && (!isset($qv['withcomments']) || !$qv['withcomments'])
        ) {
            $query->is_comment_feed = false;
        }

        $query->is_singular =
            ($query->is_single || $query->is_page || $query->is_attachment);

        $query->is_embed =
            $query->is_embed && ($query->is_singular || $query->is_404);
    }

    public function postsJoin($join, $query)
    {
        global $wpdb;

        $qv = &$query->query_vars;

        if (!empty($qv['rrze_multilang_suppress_locale_query'])) {
            return $join;
        }

        $locale = empty($qv['lang']) ? '' : $qv['lang'];

        if (!Locale::isAvailableLocale($locale)) {
            return $join;
        }

        if (!$metaTable = _get_meta_table('post')) {
            return $join;
        }

        $join .= " LEFT JOIN $metaTable AS postmeta_locale ON ($wpdb->posts.ID = postmeta_locale.post_id AND postmeta_locale.meta_key = '_rrze_multilang_single_locale')";

        return $join;
    }

    public function postsWhere($where, $query)
    {
        global $wpdb;

        $qv = &$query->query_vars;

        if (!empty($qv['rrze_multilang_suppress_locale_query'])) {
            return $where;
        }

        $locale = empty($qv['lang']) ? '' : $qv['lang'];

        if (!Locale::isAvailableLocale($locale)) {
            return $where;
        }

        if (!$metaTable = _get_meta_table('post')) {
            return $where;
        }

        $where .= " AND (1=0";

        $where .= $wpdb->prepare(" OR postmeta_locale.meta_value LIKE %s", $locale);

        if (Locale::isDefaultLocale($locale)) {
            $where .= " OR postmeta_locale.meta_id IS NULL";
        }

        $where .= ")";

        return $where;
    }

    public function optionStickyPosts($posts)
    {
        if (is_admin()) {
            return $posts;
        }

        $locale = get_locale();

        foreach ($posts as $key => $postId) {
            if ($locale != Post::getPostLocale($postId)) {
                unset($posts[$key]);
            }
        }

        return $posts;
    }

    public function getLocalPost($postId)
    {
        global $wpdb;

        if (
            is_admin()
            || empty($postId)
        ) {
            return $postId;
        }

        $postType = get_post_type($postId);

        if (
            !post_type_exists($postType)
            || !Post::isLocalizablePostType($postType)
        ) {
            return $postId;
        }

        $locale = get_locale();

        if (Post::getPostLocale($postId) == $locale) {
            return $postId;
        }

        $original_post = get_post_meta($postId, '_rrze_multilang_single_source', true);

        // For back-compat
        if (empty($original_post)) {
            $original_post = $postId;
        }

        $q = "SELECT ID FROM $wpdb->posts AS posts";
        $q .= " LEFT JOIN $wpdb->postmeta AS pm1";
        $q .= " ON posts.ID = pm1.post_id AND pm1.meta_key = '_rrze_multilang_single_source'";
        $q .= " LEFT JOIN $wpdb->postmeta AS pm2";
        $q .= " ON posts.ID = pm2.post_id AND pm2.meta_key = '_rrze_multilang_single_locale'";
        $q .= " WHERE 1=1";
        $q .= " AND post_status = 'publish'";
        $q .= $wpdb->prepare(" AND post_type = %s", $postType);

        if (is_int($original_post)) { // For back-compat
            $q .= $wpdb->prepare(
                " AND (ID = %d OR pm1.meta_value = %d)",
                $original_post,
                $original_post
            );
        } else {
            $q .= $wpdb->prepare(" AND pm1.meta_value = %s", $original_post);
        }

        $q .= " AND (1=0";
        $q .= $wpdb->prepare(" OR pm2.meta_value LIKE %s", $locale);

        if (Locale::isDefaultLocale($locale)) {
            $q .= " OR pm2.meta_id IS NULL";
        }

        $q .= ")";

        $translation = absint($wpdb->get_var($q));

        if ($translation) {
            return $translation;
        }

        return $postId;
    }
}
