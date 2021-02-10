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

            printf(
                '<select class="rrze-multilang-links" name="rrze-multilang-links-to-update-%d">',
                $blogId
            );

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
}
