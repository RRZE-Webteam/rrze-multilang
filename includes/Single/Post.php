<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;

class Post
{
    protected $options;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();

        add_action('init', [$this, 'init']);

        /* Post Template */
        add_filter('body_class', [$this, 'bodyClass'], 10, 2);
        add_filter('post_class', [$this, 'postClass'], 10, 3);

        add_filter('get_pages', [$this, 'getPages'], 10, 2);
        add_action('save_post', [$this, 'savePost'], 10, 2);
        add_filter('pre_wp_unique_post_slug', [$this, 'uniquePostSlug'], 10, 6);
        add_filter('wp_sitemaps_posts_query_args', [$this, 'sitemapsPostsQueryArgs'], 10, 2);

        /* Posts List Table */
        add_filter('manage_pages_columns', [$this, 'pagesColumns'], 10, 1);
        add_filter('manage_posts_columns', [$this, 'postsColumns'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'managePostsCustomColumn'], 10, 2);
        add_action('manage_posts_custom_column', [$this, 'managePostsCustomColumn'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'restrictManagePosts'], 10, 2);
        add_filter('post_row_actions', [$this, 'postRowActions'], 10, 2);
        add_filter('page_row_actions', [$this, 'postRowActions'], 10, 2);
        add_action('admin_init', [$this, 'addTranslation']);
    }

    public function init()
    {
        $postTypes = self::localizablePostTypes();

        $authCallback = function ($allowed, $metaKey, $objectId, $userId) {
            return user_can($userId, 'edit_post', $objectId);
        };

        foreach ($postTypes as $postType) {
            register_post_meta(
                $postType,
                '_rrze_multilang_single_locale',
                [
                    'type' => 'string',
                    'single' => true,
                    'auth_callback' => $authCallback,
                    'show_in_rest' => true,
                ]
            );

            register_post_meta(
                $postType,
                '_rrze_multilang_single_source',
                [
                    'type' => 'string',
                    'single' => true,
                    'auth_callback' => $authCallback,
                    'show_in_rest' => true,
                ]
            );
        }
    }

    public function bodyClass($classes, $class)
    {
        $locale = Locale::languageTag(get_locale());
        $locale = esc_attr($locale);

        if ($locale && !in_array($locale, $classes)) {
            $classes[] = $locale;
        }

        return $classes;
    }

    public function postClass($classes, $class, $postId)
    {
        $locale = self::getPostLocale($postId);
        $locale = Locale::languageTag($locale);
        $locale = esc_attr($locale);

        if ($locale && !in_array($locale, $classes)) {
            $classes[] = $locale;
        }

        return $classes;
    }

    public static function getPostLocale($postId)
    {
        $locale = get_post_meta($postId, '_rrze_multilang_single_locale', true);

        if (empty($locale)) {
            $locale = Locale::getDefaultLocale();
        }

        return $locale;
    }

    protected function countPosts($locale, $postType = 'post')
    {
        global $wpdb;

        if (
            !Locale::isAvailableLocale($locale)
            || !self::isLocalizablePostType($postType)
        ) {
            return false;
        }

        $q = "SELECT COUNT(1) FROM $wpdb->posts";
        $q .= " LEFT JOIN $wpdb->postmeta ON ID = $wpdb->postmeta.post_id AND meta_key = '_rrze_multilang_single_locale'";
        $q .= " WHERE 1=1";
        $q .= $wpdb->prepare(" AND post_type = %s", $postType);
        $q .= " AND post_status = 'publish'";
        $q .= " AND (1=0";
        $q .= $wpdb->prepare(" OR meta_value LIKE %s", $locale);
        $q .= Locale::isDefaultLocale($locale) ? " OR meta_id IS NULL" : "";
        $q .= ")";

        return (int) $wpdb->get_var($q);
    }

    public static function getPostTranslations($postId = 0)
    {
        $post = get_post($postId);

        if (!$post) {
            return false;
        }

        static $translations = [];

        if (isset($translations[$post->ID])) {
            return $translations[$post->ID];
        }

        $originalPostId = get_post_meta($post->ID, '_rrze_multilang_single_source', true);

        if (empty($originalPostId)) {
            $originalPostId = $post->ID;
        }

        $args = [
            'rrze_multilang_suppress_locale_query' => true,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'post_type' => $post->post_type,
            'meta_key' => '_rrze_multilang_single_source',
            'meta_value' => $originalPostId,
        ];

        $q = new \WP_Query();
        $posts = $q->query($args);
        $originalPost = get_post($originalPostId);

        if (!is_null($originalPost) && 'trash' !== get_post_status($originalPost)) {
            array_unshift($posts, $originalPost);
        }

        $translations[$post->ID] = [];

        foreach ($posts as $p) {
            if ($p->ID === $post->ID) {
                continue;
            }

            $locale = self::getPostLocale($p->ID);

            if (!Locale::isAvailableLocale($locale)) {
                continue;
            }

            if (!isset($translations[$post->ID][$locale])) {
                $translations[$post->ID][$locale] = $p;
            }
        }

        $translations[$post->ID] = array_filter($translations[$post->ID]);

        return $translations[$post->ID];
    }

    public static function getPostTranslation($postId, $locale)
    {
        $translations = self::getPostTranslations($postId);

        if (isset($translations[$locale])) {
            return $translations[$locale];
        }

        return false;
    }

    public static function getPageByPath($pagePath, $locale = null, $postType = 'page')
    {
        global $wpdb;

        if (!Locale::isAvailableLocale($locale)) {
            $locale = Locale::getDefaultLocale();
        }

        $pagePath = rawurlencode(urldecode($pagePath));
        $pagePath = str_replace('%2F', '/', $pagePath);
        $pagePath = str_replace('%20', ' ', $pagePath);

        $parts = explode('/', trim($pagePath, '/'));
        $parts = array_map('esc_sql', $parts);
        $parts = array_map('sanitize_title_for_query', $parts);

        $inString = "'" . implode("','", $parts) . "'";
        $postType_sql = $postType;
        $wpdb->escape_by_ref($postType_sql);

        $q = "SELECT ID, post_name, post_parent FROM $wpdb->posts";
        $q .= " LEFT JOIN $wpdb->postmeta ON ID = $wpdb->postmeta.post_id AND meta_key = '_rrze_multilang_single_locale'";
        $q .= " WHERE 1=1";
        $q .= " AND post_name IN ($inString)";
        $q .= " AND (post_type = '$postType_sql' OR post_type = 'attachment')";
        $q .= " AND (1=0";
        $q .= $wpdb->prepare(" OR meta_value LIKE %s", $locale);
        $q .= Locale::isDefaultLocale($locale) ? " OR meta_id IS NULL" : "";
        $q .= ")";

        $pages = $wpdb->get_results($q, OBJECT_K);

        $revparts = array_reverse($parts);

        $foundid = 0;

        foreach ((array) $pages as $page) {
            if ($page->post_name !== $revparts[0]) {
                continue;
            }

            $count = 0;
            $p = $page;

            while (
                $p->post_parent != 0
                && isset($pages[$p->post_parent])
            ) {
                $count++;
                $parent = $pages[$p->post_parent];

                if (
                    !isset($revparts[$count])
                    || $parent->post_name !== $revparts[$count]
                ) {
                    break;
                }

                $p = $parent;
            }

            if (
                $p->post_parent == 0
                && $count + 1 == count($revparts)
                && $p->post_name === $revparts[$count]
            ) {
                $foundid = $page->ID;
                break;
            }
        }

        if ($foundid) {
            return get_page($foundid);
        }

        return null;
    }

    public static function duplicatePost($originalPost, $locale)
    {
        $originalPost = get_post($originalPost);

        if (
            !$originalPost
            || !self::isLocalizablePostType(get_post_type($originalPost))
            || 'auto-draft' == get_post_status($originalPost)
        ) {
            return false;
        }

        if (
            !Locale::isAvailableLocale($locale)
            || self::getPostLocale($originalPost->ID) == $locale
        ) {
            return false;
        }

        if (self::getPostTranslation($originalPost->ID, $locale)) {
            return false;
        }

        $postarr = [
            'post_content' => $originalPost->post_content,
            'post_title' => $originalPost->post_title,
            'post_name' => $originalPost->post_name,
            'post_excerpt' => $originalPost->post_excerpt,
            'post_status' => 'draft',
            'post_type' => $originalPost->post_type,
            'comment_status' => $originalPost->comment_status,
            'ping_status' => $originalPost->ping_status,
            'post_password' => $originalPost->post_password,
            'post_content_filtered' => $originalPost->post_content_filtered,
            'post_parent' => $originalPost->post_parent,
            'menu_order' => $originalPost->menu_order,
            'post_mime_type' => $originalPost->post_mime_type,
            'tax_input' => [],
            'meta_input' => [],
        ];

        if (!empty($originalPost->post_parent)) {
            $parentTranslation = self::getPostTranslation(
                $originalPost->post_parent,
                $locale
            );

            if ($parentTranslation) {
                $postarr['post_parent'] = $parentTranslation->ID;
            }
        }

        if ($taxonomies = get_object_taxonomies($originalPost)) {
            foreach ($taxonomies as $taxonomy) {
                $terms = wp_get_post_terms(
                    $originalPost->ID,
                    $taxonomy,
                    ['fields' => 'ids']
                );

                if ($terms && !is_wp_error($terms)) {
                    $postarr['tax_input'][$taxonomy] = $terms;
                }
            }
        }

        if ($postMetas = get_post_meta($originalPost->ID)) {
            $postarr['meta_input'] = $postMetas;
        }

        $postarr = apply_filters(
            'rrze_multilang_duplicate_post',
            $postarr,
            $originalPost,
            $locale
        );

        if ($taxonomies = $postarr['tax_input']) {
            $postarr['tax_input'] = [];
        }

        if ($postMetas = $postarr['meta_input']) {
            $postarr['meta_input'] = [];
        }

        $newPostId = wp_insert_post($postarr);

        if ($newPostId) {
            if ($taxonomies) {
                foreach ($taxonomies as $taxonomy => $terms) {
                    wp_set_post_terms($newPostId, $terms, $taxonomy);
                }
            }

            if ($postMetas) {
                foreach ($postMetas as $metaKey => $metaValues) {
                    if (
                        in_array(
                            $metaKey,
                            [
                                '_rrze_multilang_single_locale',
                                '_rrze_multilang_single_source'
                            ]
                        )
                    ) {
                        continue;
                    }

                    foreach ((array) $metaValues as $metaValue) {
                        if (is_serialized($metaValue)) {
                            add_post_meta($newPostId, $metaKey, unserialize($metaValue));
                        } else {
                            add_post_meta($newPostId, $metaKey, $metaValue);
                        }
                    }
                }
            }

            update_post_meta($newPostId, '_rrze_multilang_single_locale', $locale);

            $metaOriginalPost = get_post_meta(
                $originalPost->ID,
                '_rrze_multilang_single_source',
                true
            );

            if ($metaOriginalPost) {
                update_post_meta(
                    $newPostId,
                    '_rrze_multilang_single_source',
                    $metaOriginalPost
                );
            } else {
                $originalPostGuid = get_the_guid($originalPost);

                if (empty($originalPostGuid)) {
                    $originalPostGuid = $originalPost->ID;
                }

                $translations = self::getPostTranslations($originalPost);

                update_post_meta(
                    $originalPost->ID,
                    '_rrze_multilang_single_source',
                    $originalPostGuid
                );

                if ($translations) {
                    foreach ($translations as $trLocale => $tr_post) {
                        update_post_meta(
                            $tr_post->ID,
                            '_rrze_multilang_single_source',
                            $originalPostGuid
                        );
                    }
                }

                update_post_meta(
                    $newPostId,
                    '_rrze_multilang_single_source',
                    $originalPostGuid
                );
            }
        }

        return $newPostId;
    }

    public function getPages($pages, $args)
    {
        if (
            is_admin()
            || !self::isLocalizablePostType($args['post_type'])
            || !empty($args['rrze_multilang_suppress_locale_query'])
        ) {
            return $pages;
        }

        $locale = isset($args['lang']) ? $args['lang'] : get_locale();

        if (!Locale::isAvailableLocale($locale)) {
            return $pages;
        }

        $newPages = [];

        foreach ((array) $pages as $page) {
            $postLocale = self::getPostLocale($page->ID);

            if ($postLocale == $locale) {
                $newPages[] = $page;
            }
        }

        return $newPages;
    }

    public function savePost($postId, $post)
    {
        if (
            did_action('import_start')
            && !did_action('import_end')
        ) {
            return;
        }

        if (!self::isLocalizablePostType($post->post_type)) {
            return;
        }

        $currentLocales = get_post_meta($postId, '_rrze_multilang_single_locale');
        $locale = null;

        if (!empty($currentLocales)) {
            foreach ($currentLocales as $currentLocale) {
                if (Locale::isAvailableLocale($currentLocale)) {
                    $locale = $currentLocale;
                    break;
                }
            }

            if (
                empty($locale)
                || 1 < count($currentLocales)
            ) {
                delete_post_meta($postId, '_rrze_multilang_single_locale');
                $currentLocales = [];
            }
        }

        if (empty($currentLocales)) {
            if (Locale::isAvailableLocale($locale)) {
                // $locale = $locale;
            } elseif (
                !empty($_REQUEST['locale'])
                && Locale::isAvailableLocale($_REQUEST['locale'])
            ) {
                $locale = $_REQUEST['locale'];
            } elseif ('auto-draft' == get_post_status($postId)) {
                $locale = Users::getUserLocale();
            } else {
                $locale = Locale::getDefaultLocale();
            }

            add_post_meta($postId, '_rrze_multilang_single_locale', $locale, true);
        }

        $originalPost = get_post_meta($postId, '_rrze_multilang_single_source', true);

        if (empty($originalPost)) {
            $postGuid = get_the_guid($postId);

            if (empty($postGuid)) {
                $postGuid = $postId;
            }

            $translations = self::getPostTranslations($postId);

            update_post_meta($postId, '_rrze_multilang_single_source', $postGuid);

            if ($translations) {
                foreach ($translations as $trLocale => $tr_post) {
                    update_post_meta($tr_post->ID, '_rrze_multilang_single_source', $postGuid);
                }
            }
        }
    }

    public function uniquePostSlug($overrideSlug, $slug, $postId, $postStatus, $postType, $postParent)
    {

        if (!self::isLocalizablePostType($postType)) {
            return $overrideSlug;
        }

        $locale = self::getPostLocale($postId);

        if (!Locale::isAvailableLocale($locale)) {
            return $overrideSlug;
        }

        $overrideSlug = $slug;

        $q = new \WP_Query();

        global $wp_rewrite;

        $feeds = is_array($wp_rewrite->feeds) ? $wp_rewrite->feeds : [];

        if (is_post_type_hierarchical($postType)) {
            $qArgs = [
                'name' => $slug,
                'lang' => $locale,
                'post_type' => [$postType, 'attachment'],
                'post_parent' => $postParent,
                'post__not_in' => [$postId],
                'posts_per_page' => 1,
            ];

            $isBadSlug = in_array($slug, $feeds)
                || 'embed' === $slug
                || preg_match("@^($wp_rewrite->pagination_base)?\d+$@", $slug)
                || apply_filters(
                    'wp_unique_post_slug_is_bad_hierarchical_slug',
                    false,
                    $slug,
                    $postType,
                    $postParent
                );

            if (!$isBadSlug) {
                $qResults = $q->query($qArgs);
                $isBadSlug = !empty($qResults);
            }

            if ($isBadSlug) {
                $suffix = 1;

                while ($isBadSlug) {
                    $suffix += 1;
                    $altSlug = sprintf(
                        '%s-%s',
                        $this->truncatePostSlug($slug, 200 - (strlen($suffix) + 1)),
                        $suffix
                    );

                    $qResults = $q->query(array_merge(
                        $qArgs,
                        ['name' => $altSlug]
                    ));

                    $isBadSlug = !empty($qResults);
                }

                $overrideSlug = $altSlug;
            }
        } else {
            $qArgs = [
                'name' => $slug,
                'lang' => $locale,
                'post_type' => $postType,
                'post__not_in' => [$postId],
                'posts_per_page' => 1,
            ];

            $isBadSlug = in_array($slug, $feeds)
                || 'embed' === $slug
                || apply_filters(
                    'wp_unique_post_slug_is_bad_flat_slug',
                    false,
                    $slug,
                    $postType
                );

            if (!$isBadSlug) {
                $post = get_post($postId);

                if (
                    'post' === $postType
                    && (!$post || $post->post_name !== $slug)
                    && preg_match('/^[0-9]+$/', $slug)
                ) {
                    $slugNum = intval($slug);

                    if ($slugNum) {
                        $permastructs = array_values(array_filter(
                            explode('/', get_option('permalink_structure'))
                        ));
                        $postnameIndex = array_search('%postname%', $permastructs);

                        $isBadSlug = false
                            || 0 === $postnameIndex
                            || ($postnameIndex
                                && '%year%' === $permastructs[$postnameIndex - 1]
                                && 13 > $slugNum)
                            || ($postnameIndex
                                && '%monthnum%' === $permastructs[$postnameIndex - 1]
                                && 32 > $slugNum);
                    }
                }
            }

            if (!$isBadSlug) {
                $qResults = $q->query($qArgs);
                $isBadSlug = !empty($qResults);
            }

            if ($isBadSlug) {
                $suffix = 1;

                while ($isBadSlug) {
                    $suffix += 1;
                    $altSlug = sprintf(
                        '%s-%s',
                        $this->truncatePostSlug($slug, 200 - (strlen($suffix) + 1)),
                        $suffix
                    );

                    $qResults = $q->query(array_merge(
                        $qArgs,
                        ['name' => $altSlug]
                    ));

                    $isBadSlug = !empty($qResults);
                }

                $overrideSlug = $altSlug;
            }
        }

        return $overrideSlug;
    }

    public function sitemapsPostsQueryArgs($args, $post_type)
    {
        if ($this->isLocalizablePostType($post_type)) {
            $args['rrze_multilang_suppress_locale_query'] = true;
        }

        return $args;
    }

    protected function truncatePostSlug($slug, $length = 200)
    {
        $slug = $slug ?? '';

        if (strlen($slug) > $length) {
            $decoded_slug = urldecode($slug);

            if ($decoded_slug === $slug) {
                $slug = substr($slug, 0, $length);
            } else {
                $slug = utf8_uri_encode($decoded_slug, $length);
            }
        }

        return rtrim($slug, '-');
    }

    public function pagesColumns($columns)
    {
        return $this->postsColumns($columns, 'page');
    }

    public function postsColumns($columns, $postType)
    {
        if (!self::isLocalizablePostType($postType)) {
            return $columns;
        }

        if (!isset($columns['locale'])) {
            $columns = array_merge(
                array_slice($columns, 0, 2),
                ['locale' => __('Translation', 'rrze-multilang')],
                array_slice($columns, 2)
            );
        }

        return $columns;
    }


    public function managePostsCustomColumn($column, $postId)
    {
        if ($column !== 'locale') {
            return;
        }

        $postType = get_post_type($postId);

        if (!self::isLocalizablePostType($postType)) {
            return;
        }

        $locale = get_post_meta($postId, '_rrze_multilang_single_locale', true);

        if (empty($locale)) {
            return;
        }

        $language = Locale::getLanguage($locale);

        if (empty($language)) {
            $language = $locale;
        }

        printf(
            '<a href="%1$s">%2$s</a>',
            esc_url(
                add_query_arg(
                    [
                        'post_type' => $postType,
                        'lang' => $locale,
                    ],
                    'edit.php'
                )
            ),
            esc_html($language)
        );
    }

    public function restrictManagePosts($postType, $which)
    {
        if (!self::isLocalizablePostType($postType)) {
            return;
        }

        $availableLanguages = Locale::getAvailableLanguages();
        $currentLocale = empty($_GET['lang']) ? '' : $_GET['lang'];

        echo '<select name="lang">';

        $selected = ('' == $currentLocale) ? ' selected="selected"' : '';

        echo '<option value=""' . $selected . '>'
            . esc_html(__('Show all locales', 'rrze-multilang')) . '</option>';

        foreach ($availableLanguages as $locale => $lang) {
            $selected = ($locale == $currentLocale) ? ' selected="selected"' : '';

            echo '<option value="' . esc_attr($locale) . '"' . $selected . '>'
                . esc_html($lang) . '</option>';
        }

        echo '</select>' . "\n";
    }

    public function postRowActions($actions, $post)
    {
        if (
            !self::isLocalizablePostType($post->post_type)
            || 'trash' === $post->post_status
        ) {
            return $actions;
        }

        $postTypeObject = get_post_type_object($post->post_type);

        if (!current_user_can($postTypeObject->cap->edit_posts)) {
            return $actions;
        }

        $userLocale = Users::getUserLocale();
        $postLocale = self::getPostLocale($post->ID);

        if ($userLocale == $postLocale) {
            return $actions;
        }

        if ($translation = self::getPostTranslation($post, $userLocale)) {
            if (
                empty($translation->ID)
                || $translation->ID === $post->ID
            ) {
                return $actions;
            }
            /* translators: %s: The translated post language. */
            $text = __('Edit %s translation', 'rrze-multilang');
            $editLink = get_edit_post_link($translation->ID);
        } else {
            /* translators: %s: The translated post language. */
            $text = __('Translate into %s', 'rrze-multilang');
            $editLink = admin_url(
                'post-new.php?post_type=' . $post->post_type
                    . '&action=rrze-multilang-add-translation'
                    . '&locale=' . $userLocale
                    . '&original_post=' . $post->ID
            );
            $editLink = wp_nonce_url($editLink, 'rrze-multilang-add-translation');
        }

        $language = Locale::getLanguage($userLocale);

        if (empty($language)) {
            $language = $userLocale;
        }

        $actions['translate'] = sprintf(
            '<a href="%1$s">%2$s</a>',
            $editLink,
            esc_html(sprintf($text, $language))
        );

        return $actions;
    }

    public function addTranslation()
    {
        if (
            empty($_REQUEST['action'])
            || 'rrze-multilang-add-translation' != $_REQUEST['action']
        ) {
            return;
        }

        check_admin_referer('rrze-multilang-add-translation');

        $locale = isset($_REQUEST['locale']) ? $_REQUEST['locale'] : '';
        $originalPost = isset($_REQUEST['original_post'])
            ? absint($_REQUEST['original_post']) : 0;

        if (!Locale::isAvailableLocale($locale)) {
            return;
        }

        if (
            !$originalPost
            || !$originalPost = get_post($originalPost)
        ) {
            return;
        }

        $postTypeObject = get_post_type_object($originalPost->post_type);

        if (
            $postTypeObject
            && current_user_can($postTypeObject->cap->edit_posts)
        ) {
            $newPostId = self::duplicatePost($originalPost, $locale);

            if ($newPostId) {
                $redirectTo = get_edit_post_link($newPostId, 'raw');
                wp_safe_redirect($redirectTo);
                exit();
            }
        }
    }

    public static function localizablePostTypes()
    {
        $options = (object) Options::getOptions();
        $localizable = apply_filters(
            'rrze_multilang_localizable_post_types',
            $options->post_types
        );

        $localizable = array_diff(
            $localizable,
            ['attachment', 'revision', 'nav_menu_item']
        );

        return $localizable;
    }

    public static function isLocalizablePostType($postType)
    {
        return !empty($postType) && in_array($postType, self::localizablePostTypes());
    }
}
