<?php

namespace RRZE\Multilang\Multiple;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Functions;

defined('ABSPATH') || exit;

class Sites
{
    public static function getSecondaryToLink(\WP_Post $post): array
    {
        $currentBlogId = get_current_blog_id();
        $reference = (array) get_post_meta($post->ID, '_rrze_multilang_multiple_reference', true);
        $secondarySites = Sites::getSecondarySites($post->post_type, true);
        $emdash = html_entity_decode('&mdash;', ENT_COMPAT, 'UTF-8');

        $secondary = [];

        foreach ($secondarySites as $blogId => $blog) {
            $name = esc_html($blog['name']);
            $language = esc_html(Locale::getLanguageNativeName($blog['language']));

            $selectedValue = 0;

            $options = [];
            $options[] = [
                'label' => sprintf('%1$s %2$s %1$s', $emdash, __('Select', 'rrze-multilang')),
                'value' => 0,
                'disabled' => false
            ];

            foreach ($blog['posts'] as $refPost) {
                $reffered = false;
                if (
                    isset($reference[$blogId])
                    && $reference[$blogId] == $refPost->ID
                ) {
                    switch_to_blog($blogId);
                    $remoteRef = (array) get_post_meta($refPost->ID, '_rrze_multilang_multiple_reference', true);
                    restore_current_blog();
                    if (
                        isset($remoteRef[$currentBlogId])
                        && $remoteRef[$currentBlogId] == $post->ID
                    ) {
                        $reffered = true;
                    }
                }
                $label = esc_html($refPost->post_title);
                $value = sprintf('%1$d::%2$d', $blogId, $refPost->ID);
                if ($reffered) {
                    $selectedValue = $value;
                }
                $options[] = [
                    'label' => $label,
                    'value' => $value,
                    'disabled' => false
                ];
            }

            $secondary[$blogId] = [
                'name' => $name,
                'language' => $language,
                'selected' => $selectedValue,
                'options' => $options
            ];
        }
        return $secondary;
    }

    public static function getSecondaryToCopy(\WP_Post $post)
    {
        $secondarySites = self::getSecondarySites($post->post_type);
        $emdash = html_entity_decode('&mdash;', ENT_COMPAT, 'UTF-8');

        $secondary = [];

        $options[] = [
            'label' => sprintf(
                '%1$s %2$s %1$s',
                $emdash,
                __('Select', 'rrze-multilang')
            ),
            'value' => 0,
            'disabled' => false
        ];        

        foreach ($secondarySites as $blogId => $blog) {
            $name = esc_html($blog['name']);
            $language = esc_html(Locale::getLanguageNativeName($blog['language']));

            $options[] = [
                'label' => sprintf(
                    '%1$s %2$s %3$s',
                    $name,
                    $emdash,
                    $language
                ),
                'value' => $blogId,
                'disabled' => false
            ];

        }

        $secondary[] = [
            'options' => $options
        ];

        return $secondary;
    }

    public static function getSecondarySites(string $postType, bool $posts = false): array
    {
        $siteOptions = (object) Options::getSiteOptions();
        $currentBlogId = get_current_blog_id();

        $secondarySites = [];
        foreach ($siteOptions->connections[$currentBlogId] as $blogId) {
            if (!Functions::isBlogPublic($blogId)) {
                continue;
            }

            switch_to_blog($blogId);
            if (
                Post::isLocalizablePostType($postType)
                && isset($siteOptions->connections[$blogId])
                && in_array($currentBlogId, $siteOptions->connections[$blogId])
            ) {
                $secondarySites[$blogId] = [
                    'name' => get_bloginfo('name'),
                    'url' => get_bloginfo('url'),
                    'language' => Locale::getDefaultLocale(),
                    'posts' => $posts ? Post::getPosts($postType, ['publish']) : []
                ];
            }
            restore_current_blog();
        }
        return $secondarySites;
    }
}
