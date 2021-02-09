<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Locale;

class Metabox
{
    protected $currentBlogId;

    public function __construct()
    {
        $this->currentBlogId = get_current_blog_id();

        add_action('add_meta_boxes', [$this, 'addL10nMetabox'], 10, 2);
    }

    public function addL10nMetabox($postType, $post)
    {
        if (!Post::isLocalizablePostType($postType)) {
            return;
        }

        add_meta_box(
            'rrze-multilang-l10n',
            __('Language', 'rrze-multilang'),
            [$this, 'l10nMetabox'],
            null,
            'side',
            'high',
            [
                '__back_compat_meta_box' => true,
            ]
        );
    }

    public function l10nMetabox($post)
    {
        $postId = $post->ID;
        $postType = get_post_type($post);

        $reference = (array) get_post_meta($postId, '_rrze_multilang_multiple_reference', true);

        $secondarySites = Sites::getSecondary($postType);

        if (empty($secondarySites)) {
            echo '<p>', __('There are no websites available for translations.', 'rrze-multilang'), '</p>';
            return;
        }

        // Links
        echo '<div id="rrze-multilang-update-links-actions" class="descriptions">';
        foreach ($secondarySites as $blog) {
            printf(
                '<p><strong>%1$s</strong> &mdash; %2$s</p>',
                esc_html($blog['name']),
                esc_html(Locale::getLanguageNativeName($blog['language']))
            );

            printf(
                '<select class="rrze-multilang-links" name="rrze-multilang-links-to-update-%d">',
                $blog['blog_id']
            );

            printf(
                '<option value="0::0">%s</option>',
                __('&mdash; Select &mdash;', 'rrze-multilang')
            );

            foreach ($blog['posts'] as $refPost) {
                $reffered = false;
                if (
                    isset($reference[$blog['blog_id']])
                    && $reference[$blog['blog_id']] == $refPost->ID
                ) {
                    switch_to_blog($blog['blog_id']);
                    $remoteRef = (array) get_post_meta($refPost->ID, '_rrze_multilang_multiple_reference', true);
                    restore_current_blog();
                    if (
                        isset($remoteRef[$this->currentBlogId])
                        && $remoteRef[$this->currentBlogId] == $postId
                    ) {
                        $reffered = true;
                    }
                }
                $selected = selected($reffered, true, false);
                printf(
                    '<option value="%1$d::%2$d" %3$s>%4$s</option>',
                    $blog['blog_id'],
                    $refPost->ID,
                    $selected,
                    esc_html($refPost->post_title)
                );
            }

            echo '</select>';
        }
        echo '<p>';
        printf(
            '<button type="button" class="button" id="%1$s">%2$s</button>',
            'rrze-multilang-update-links',
            esc_html(__('Update Links', 'rrze-multilang'))
        );
        echo '<span class="spinner"></span>';
        echo '</p>';
        echo '<div class="clear"></div>';
        echo '</div>';

        // Copy
        echo '<div id="rrze-multilang-add-copy-actions" class="descriptions">';
        printf(
            '<p><strong>%s</strong></p>',
            esc_html(__('Copy To:', 'rrze-multilang'))
        );
        echo '<select id="rrze-multilang-copy-to-add">';

        printf(
            '<option value="0">%s</option>',
            __('&mdash; Select &mdash;', 'rrze-multilang')
        );
        foreach ($secondarySites as $blog) {
            printf(
                '<option value="%1$s">%2$s &mdash; %3$s</option>',
                esc_attr($blog['blog_id']),
                esc_html($blog['name']),
                esc_html(Locale::getLanguageNativeName($blog['language']))
            );
        }

        echo '</select>';
        echo '<p>';
        printf(
            '<button type="button" class="button" id="%1$s">%2$s</button>',
            'rrze-multilang-add-copy',
            esc_html(__('Add a Copy', 'rrze-multilang'))
        );
        echo '<span class="spinner"></span>';
        echo '</p>';
        echo '<div class="clear"></div>';
        echo '</div>';
    }
}
