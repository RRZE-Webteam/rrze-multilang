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

        foreach ($this->options->post_types as $postType) {
            add_filter('manage_edit-' . $postType . '_columns', [$this, 'customColumns']);
            add_action('manage_' . $postType . '_posts_custom_column', [$this, 'postsCustomColumn'], 10, 2);
        }
    }

    public function customColumns($columns)
    {
        $position = array_search('comments', array_keys($columns));
        if ($position === false) {
            $position = array_search('date', array_keys($columns));
            if ($position === false) {
                $position = array_search('last-modified', array_keys($columns));
            }
        }

        if ($position !== false) {
            $columns = array_slice($columns, 0, $position, true) + array('language' => '') + array_slice($columns, $position, count($columns) - $position, true);
        }

        $columns['language'] = __('Language', 'rrze-multilang');

        return $columns;
    }

    public function postsCustomColumn($column, $postId)
    {
        if ($column !== 'language') {
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
        $reference = (array) get_post_meta($postId, '_rrze_multilang_multiple_reference', true);

        if (!$isMain && $reference) {
            $ref = $reference;
            $refBlogId = array_keys($ref);
            $refBlogId = array_shift($refBlogId);
            $refPostId = array_shift($ref[$refBlogId]);
            switch_to_blog($refBlogId);
            $reference = $reference + (array) get_post_meta($refPostId, '_rrze_multilang_multiple_reference', true);
            restore_current_blog();
        }

        if (isset($reference[$this->currentBlogId])) {
            unset($reference[$this->currentBlogId]);
        }

        foreach ($reference as $blogId => $refPostId) {
            switch_to_blog($blogId);
            $locale = Locale::getDefaultLocale();
            $title = get_the_title($refPostId);
            $permalink = get_permalink($refPostId);
            restore_current_blog();

            $nativeName = Locale::getLanguageNativeName($locale);

            if (Locale::isLocaleIso639($locale)) {
                $nativeName = Locale::getShortName($nativeName);
            }

            $lang = sprintf(' &mdash; <span class="translation">%s</span></a>', $nativeName);

            $posts[] = sprintf('<a href="%1$s" target="__blank">%2$s%3$s', $permalink, $title, $lang);
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

        $sourcetags = wp_get_post_tags($processInfo['source_post_id'], array('fields' => 'names'));

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
        foreach ($metaValues as $key => $values) {
            if ($key[0] == '_') {
                continue;
            }
            foreach ($values as $value) {
                if (is_serialized($value)) {
                    update_post_meta($postId, $key, unserialize($value));
                } else {
                    update_post_meta($postId, $key, $value);
                }
            }
        }
    }
}
