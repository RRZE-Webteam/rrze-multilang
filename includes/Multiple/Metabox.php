<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;

class Metabox
{
    protected $options;

    protected $siteOptions;

    protected $currentBlogId;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();
        $this->siteOptions = (object) Options::getSiteOptions();
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

        // Add a nonce field for security
        wp_nonce_field('rrze_multilang_to_link_nonce_action', 'rrze_multilang_to_link_nonce');

        // Add hidden field
        printf('<input type="hidden" name="rrze_multilang_blogids_to_link" value="%1$s">', esc_attr(implode(',', array_keys($secondarySites))));

        foreach ($secondarySites as $blogId => $blog) {
            printf(
                '<p><strong>%1$s</strong> &mdash; %2$s</p>',
                esc_html($blog['name']),
                esc_html($blog['language'])
            );

            // Selector/Option field
            echo '<select class="rrze-multilang-links" name="rrze_multilang_links_to_update[]">';

            foreach ($blog['options'] as $option) {
                $selected = $option['value'] == $blog['selected'] ? ' selected' : '';
                $disabled = $option['disabled'] ? ' disabled' : '';
                printf(
                    '<option value="%1$s"%2$s%3$s>%4$s</option>',
                    esc_attr($option['value']),
                    $selected,
                    $disabled,
                    esc_html($option['label'])
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
        if (
            !isset($_POST['rrze_multilang_to_link_nonce'])
            || !wp_verify_nonce($_POST['rrze_multilang_to_link_nonce'], 'rrze_multilang_to_link_nonce_action')
        ) {
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

        // Check the custom fields
        if (
            empty($_POST['rrze_multilang_blogids_to_link'])
            || empty($_POST['rrze_multilang_links_to_update'])
            || !is_array($_POST['rrze_multilang_links_to_update'])
        ) {
            return;
        }

        $postType = get_post_type($postId);
        $remoteBlogIds = $_POST['rrze_multilang_blogids_to_link'];
        $remoteBlogIds = explode(',', $remoteBlogIds);

        $linksToUpdate = $_POST['rrze_multilang_links_to_update'];

        $references = [];

        foreach ($linksToUpdate as $linkToUpdate) {
            $linkToUpdate = explode(':', $linkToUpdate);
            $linkedRemoteBlogId = $linkToUpdate[0] ?? 0;
            $linkedRemotePostId = $linkToUpdate[1] ?? 0;

            $remoteBlogId = absint($linkedRemoteBlogId);
            $remotePostId = absint($linkedRemotePostId);

            if (
                $remoteBlogId === 0
                || !in_array($remoteBlogId, $remoteBlogIds)
                || !isset($this->siteOptions->connections[$remoteBlogId])
                || !in_array($this->currentBlogId, $this->siteOptions->connections[$remoteBlogId])
                || get_blog_status($remoteBlogId, 'archived')
                || get_blog_status($remoteBlogId, 'deleted')
            ) {
                continue;
            }

            if ($remotePostId === 0) {
                continue;
            }

            switch_to_blog($remoteBlogId);
            $remotePost = get_post($remotePostId);
            $remoteOptions = (object) Options::getOptions();
            restore_current_blog();

            if (
                !$remotePost
                || !in_array($remotePost->post_type, $this->options->post_types)
                || !in_array($remotePost->post_type, $remoteOptions->post_types)
            ) {
                continue;
            }

            $references[$remoteBlogId] = $remotePostId;
        }

        $mainReference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
        if (!empty($mainReference) && is_array($mainReference)) {
            foreach ($mainReference as $remoteBlogId => $remotePostId) {
                switch_to_blog($remoteBlogId);
                delete_post_meta($remotePostId, '_rrze_multilang_multiple_reference');
                restore_current_blog();
            }
        }

        if (empty($references)) {
            delete_post_meta($postId, '_rrze_multilang_multiple_reference');
            return;
        }

        update_post_meta($postId, '_rrze_multilang_multiple_reference', $references);

        foreach ($references as $remoteBlogId => $remotePostId) {
            $reference = [
                $this->currentBlogId => $postId
            ];
            switch_to_blog($remoteBlogId);
            $this->deleteRemotePostMeta('_rrze_multilang_multiple_reference', $reference, $postType);
            delete_post_meta($remotePostId, '_rrze_multilang_multiple_reference');
            add_post_meta($remotePostId, '_rrze_multilang_multiple_reference', $reference);
            restore_current_blog();
        }
    }

    /**
     * Delete the meta for the filtered posts
     *
     * @param string $metaKey
     * @param mixed $metaValue
     * @param string $postType
     */
    private function deleteRemotePostMeta($metaKey, $metaValue, $postType)
    {
        global $wpdb;

        // Query to find posts with the specific meta key and value
        $query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s",
            $metaKey,
            maybe_serialize($metaValue),
            $postType
        );

        $postIds = $wpdb->get_col($query);

        // Delete the meta for the filtered posts
        foreach ($postIds as $postId) {
            delete_post_meta($postId, $metaKey);
        }
    }
}
