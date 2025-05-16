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
        $secondary = [];
        $secondarySites = self::getSecondarySites($post->post_type, true);
        if (empty($secondarySites)) {
            return $secondary;
        }

        $currentBlogId = get_current_blog_id();
        $reference = (array) get_post_meta($post->ID, '_rrze_multilang_multiple_reference', true);
        $emdash = html_entity_decode('&mdash;', ENT_COMPAT, 'UTF-8');

        foreach ($secondarySites as $blogId => $blog) {
            $name = esc_html($blog['name']);
            $language = esc_html(Locale::getLanguageNativeName($blog['language']));
            $url = esc_url($blog['url']);

            $selectedValue = 0;

            $options = [];
            $options[] = [
                'label' => sprintf('%1$s %2$s %1$s', $emdash, __('None', 'rrze-multilang')),
                'value' => sprintf('%d:0', $blogId),
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
                $value = sprintf('%1$d:%2$d', $blogId, $refPost->ID);
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
                'url' => $url,
                'selected' => $selectedValue,
                'options' => $options
            ];
        }
        return $secondary;
    }

    public static function getSecondaryToCopy(\WP_Post $post)
    {
        $secondary = [];
        $secondarySites = self::getSecondarySites($post->post_type);
        if (empty($secondarySites)) {
            return $secondary;
        }

        $emdash = html_entity_decode('&mdash;', ENT_COMPAT, 'UTF-8');

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
        if (empty($siteOptions->connections[$currentBlogId]) || !is_array($siteOptions->connections[$currentBlogId])) {
            return $secondarySites;
        }
        foreach ($siteOptions->connections[$currentBlogId] as $blogId) {
            if (!Functions::isBlogAvailable($blogId)) {
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
