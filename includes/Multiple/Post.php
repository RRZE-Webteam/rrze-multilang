<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;

class Post
{
    protected $options;

    protected $siteOptions;

    protected $currentBlogId;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();
        $this->siteOptions = (object) Options::getSiteOptions();

        $this->currentBlogId = get_current_blog_id();

        /* Posts List Table */
        foreach ($this->options->post_types as $postType) {
            add_filter("manage_edit-{$postType}_columns", [$this, 'postsColumns']);
            add_action("manage_{$postType}_posts_custom_column", [$this, 'managePostsCustomColumn'], 10, 2);
        }

        // add_filter('manage_pages_columns', [$this, 'pagesColumns'], 10, 1);
        // add_filter('manage_posts_columns', [$this, 'postsColumns'], 10, 2);
        // add_action('manage_pages_custom_column', [$this, 'managePostsCustomColumn'], 10, 2);
        // add_action('manage_posts_custom_column', [$this, 'managePostsCustomColumn'], 10, 2);        
    }

    public function postsColumns($columns)
    {
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

        $posts = $this->getRefPosts($postId);
        if (empty($posts)) {
            $posts[] = '&mdash;';
        }

        echo implode('<br>', $posts);
    }

    protected function getRefPosts($postId)
    {
        $posts = [];
        $isMain = $this->options->connection_type == 1 ? true : false;
        $secondarySites = Sites::getSecondarySites(get_post_type($postId));
        $reference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true);

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

        if (empty($reference) || !is_array($reference)) {
            return $posts;
        }

        foreach ($reference as $blogId => $refPostId) {
            if ($this->currentBlogId == $blogId || !isset($secondarySites[$blogId])) {
                continue;
            }
            switch_to_blog($blogId);
            $locale = Locale::getDefaultLocale();
            $title = get_the_title($refPostId);
            $permalink = get_permalink($refPostId);
            restore_current_blog();

            $nativeName = Locale::getLanguageNativeName($locale);

            if (Locale::isLocaleIso639($locale)) {
                $nativeName = Locale::getShortName($nativeName);
            }

            $posts[] = sprintf(
                '<a href="%1$s" target="__blank">%2$s &mdash; %3$s <span class="dashicons dashicons-external"></span></a>',
                $permalink,
                $title,
                $nativeName
            );
        }

        return $posts;
    }

    public static function getPosts(string $postType, array $postStatus)
    {
        return get_posts(
            [
                'post_type' => $postType,
                'post_status' => $postStatus,
                'orderby' => 'title',
                'order' => 'ASC',
                'nopaging' => true
            ]
        );
    }

    public static function duplicatePost($postId, $blogId, $postType)
    {
        if (apply_filters('rrze_multilang_do_single_metabox_duplication', true)) {

            $remotePostId = self::copyPost(
                $postId,
                $blogId,
                $postType,
                get_current_user_id(),
                'Copy &mdash;',
                'draft'
            );
        }

        return $remotePostId;
    }

    protected static function copyPost($postIdToCopy, $newBlogId, $postType, $postAuthor, $prefix, $postStatus)
    {
        $processInfo = apply_filters('rrze_multilang_copy_source_data', [
            'source_post_id'        => $postIdToCopy,
            'destination_id'        => $newBlogId,
            'post_type'             => $postType,
            'post_author'           => $postAuthor,
            'prefix'                => $prefix,
            'requested_post_status' => $postStatus
        ]);

        $options = (object) Options::getOptions();

        $sourcePost = get_post($processInfo['source_post_id']);

        $title  = get_the_title($sourcePost);

        $sourcetags = wp_get_post_tags($processInfo['source_post_id'], ['fields' => 'names']);

        $sourceBlogId  = get_current_blog_id();

        $sourceCategories = Terms::getObjectsOfPostCategories($processInfo['source_post_id'], $processInfo['post_type']);

        $sourceTaxonomies = Terms::getPostTaxonomyTerms($processInfo['source_post_id'], false, $processInfo['destination_id']);

        if ($processInfo['prefix'] != '') {
            $processInfo['prefix'] = trim($processInfo['prefix']) . ' ';
        }

        $postName = $processInfo['destination_id'] == $sourceBlogId ? null : $sourcePost->post_name;

        $sourcePost = apply_filters(
            'rrze_multilang_setup_destination_data',
            [
                'post_title'    => $processInfo['prefix'] . $title,
                'post_status'   => $processInfo['requested_post_status'],
                'post_type'     => $processInfo['post_type'],
                'post_author'   => $processInfo['post_author'],
                'post_content'  => $sourcePost->post_content,
                'post_excerpt'  => $sourcePost->post_excerpt,
                'post_content_filtered' => $sourcePost->post_content_filtered,
                'post_name'     => $postName

            ],
            $processInfo
        );

        $metaValues = apply_filters('rrze_multilang_filter_post_meta', get_post_meta($processInfo['source_post_id']));

        $featuredImage = Media::getFeaturedImageFromSource($processInfo['source_post_id']);

        if ($processInfo['destination_id'] != $sourceBlogId) {

            $attachedImages = Media::getImagesFromTheContent($processInfo['source_post_id']);

            if ($attachedImages) {

                $attachedImagesAltTags = Media::getImageAltTags($attachedImages);
            }
        } else {

            $attachedImages = false;
        }

        switch_to_blog($processInfo['destination_id']);

        $postId = wp_insert_post($sourcePost);
        if ($postId == 0 && is_wp_error($postId)) {
            return 0;
        }

        self::processMeta($postId, $metaValues);

        if ($attachedImages) {
            if ($options->copy_post_meta['content_images'] && apply_filters('rrze_multilang_copy_content_images', true)) {
                Media::processPostMediaAttachements($postId, $attachedImages, $attachedImagesAltTags, $sourceBlogId, $newBlogId);
            }
        }

        if ($featuredImage) {
            if ($options->copy_post_meta['featured_image'] && apply_filters('rrze_multilang_copy_featured_image', true)) {
                Media::setFeaturedImageToTarget($postId, $featuredImage, $sourceBlogId);
            }
        }

        if ($sourcetags) {
            if ($options->copy_post_meta['tags'] && apply_filters('rrze_multilang_copy_tags', true)) {
                wp_set_post_tags($postId, $sourcetags);
            }
        }

        if ($sourceCategories) {
            if ($options->copy_post_meta['categories'] && apply_filters('rrze_multilang_copy_post_categories', true)) {
                Terms::setTargetCategories($postId, $sourceCategories, $sourcePost['post_type']);
            }
        }

        if ($sourceTaxonomies) {
            if ($options->copy_post_meta['taxonomies'] && apply_filters('rrze_multilang_copy_post_taxonomies', true)) {
                Terms::setPostTaxonomyTerms($postId, $sourceTaxonomies);
            }
        }

        restore_current_blog();

        return $postId;
    }

    public static function processMeta($postId, $metaValues)
    {
        if (empty($metaValues)) {
            return;
        }
        foreach ($metaValues as $metaKey => $metaValues) {
            if (
                in_array(
                    $metaKey,
                    [
                        '_rrze_multilang_single_locale',
                        '_rrze_multilang_single_source',
                        '_rrze_multilang_multiple_reference'
                    ]
                )
            ) {
                continue;
            }
            foreach ((array) $metaValues as $metaValue) {
                if (is_serialized($metaValue)) {
                    update_post_meta($postId, $metaKey, unserialize($metaValue));
                } else {
                    update_post_meta($postId, $metaKey, $metaValue);
                }
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
