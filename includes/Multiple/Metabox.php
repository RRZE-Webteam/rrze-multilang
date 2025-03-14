<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

class Metabox
{
    protected $currentBlogId;

    public function __construct()
    {
        $this->currentBlogId = get_current_blog_id();

        add_action('add_meta_boxes', [$this, 'addL10nMetabox'], 10, 2);
        add_action('save_post', [$this, 'saveCustomFields']);
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
        $secondarySites = Sites::getSecondaryToLink($post);

        if (empty($secondarySites)) {
            echo '<p>', __('There are no websites available for translations.', 'rrze-multilang'), '</p>';
            return;
        }

        /* Reffered Posts */
        echo '<div id="rrze-multilang-update-links-actions" class="descriptions">';
        foreach ($secondarySites as $blogId => $blog) {
            printf(
                '<p><strong>%1$s</strong> &mdash; %2$s</p>',
                $blog['name'],
                $blog['language']
            );

            // Add a nonce field for security
            wp_nonce_field('rrze_multilang_to_link_nonce_action', 'rrze_multilang_to_link_nonce');

            // Add hidden field
            printf('<input type="hidden" name="rrze_multilang_blogid_to_link" value="%s">', $blogId);

            // Selector/Option field
            echo '<select class="rrze-multilang-links" name="rrze_multilang_links_to_update">';

            foreach ($blog['options'] as $option) {
                $selected = $option['value'] == $blog['selected'] ? ' selected' : '';
                $disabled = $option['disabled'] ? ' disabled' : '';
                printf(
                    '<option value="%1$s"%2$s%3$s>%4$s</option>',
                    $option['value'],
                    $selected,
                    $disabled,
                    $option['label']
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
        $secondarySites = Sites::getSecondaryToCopy($post);

        if (empty($secondarySites)) {
            return;
        }

        echo '<div id="rrze-multilang-add-copy-actions" class="descriptions">';
        printf(
            '<p><strong>%s</strong></p>',
            esc_html(__('Copy To:', 'rrze-multilang'))
        );
        echo '<select id="rrze-multilang-copy-to-add">';

        $firstKey = array_key_first($secondarySites);
        $options = $secondarySites[$firstKey]['options'];
        foreach ($options as $option) {
            $disabled = $option['disabled'] ? ' disabled' : '';
            printf(
                '<option value="%1$s"%2$s>%3$s</option>',
                $option['value'],
                $disabled,
                esc_html($option['label']),
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

    public function saveCustomFields($postId)
    {
        // Check if the nonce is set
        if (!isset($_POST['rrze_multilang_to_link_nonce']) || !wp_verify_nonce($_POST['rrze_multilang_to_link_nonce'], 'rrze_multilang_to_link_nonce_action')) {
            return;
        }

        // Check for autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Sanitize and save the field
        if (isset($_POST['rrze_multilang_links_to_update']) && isset($_POST['rrze_multilang_blogid_to_link'])) {
            $postType = get_post_type($postId);
            $remoteBlogId = $_POST['rrze_multilang_blogid_to_link'];
            $linkToUpdate = $_POST['rrze_multilang_links_to_update'];
            $linkToUpdate = explode(':', $linkToUpdate);
            $remotePostId = $linkToUpdate[1] ?? null;

            if (!isset($linkToUpdate[0]) || $remoteBlogId !== $linkToUpdate[0] || is_null($remotePostId)) {
                return;
            }

            $prevReference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
            if (!$prevReference) {
                $reference = [
                    $remoteBlogId => $remotePostId
                ];
                add_post_meta($postId, '_rrze_multilang_multiple_reference', $reference);
            } else {
                $reference = $prevReference;
                $reference[$remoteBlogId] = $remotePostId;
                update_post_meta($postId, '_rrze_multilang_multiple_reference', $reference, $prevReference);
            }

            switch_to_blog($remoteBlogId);
            $this->deleteRemotePostMeta('_rrze_multilang_multiple_reference', [$this->currentBlogId => $postId], $postType);
            $reference = [
                $this->currentBlogId => $postId
            ];
            add_post_meta($remotePostId, '_rrze_multilang_multiple_reference', $reference);
            restore_current_blog();
        }
    }

    private function deleteRemotePostMeta($metaKey, $metaValue, $postType)
    {
        $args = [
            'post_type' => $postType,
            'meta_key' => $metaKey,
            'meta_value' => '',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];

        $posts = get_posts($args);

        foreach ($posts as $postId) {
            $value = get_post_meta($postId, $metaKey, true);
            if ($value === $metaValue) {
                delete_post_meta($postId, $metaKey);
            }
        }
    }
}
