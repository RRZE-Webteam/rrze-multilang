<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Locale;

class Metabox
{
    public function __construct()
    {
        add_action('add_meta_boxes', [$this, 'addL10nMetabox'], 10, 2);
    }

    public function addL10nMetabox($postType, $post)
    {
        if (!Post::isLocalizablePostType($postType)) {
            return;
        }

        if (in_array($postType, ['comment', 'link'])) {
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
        $initial = ('auto-draft' == $post->post_status);

        if ($initial) {
            $postLocale = Users::getUserLocale();
        } else {
            $postLocale = Post::getPostLocale($post->ID);
        }

        $translations = Post::getPostTranslations($post->ID);
        $availableLanguages = Locale::getAvailableLanguages([
            'current_user_can_access' => true,
            'selected_only' => false
        ]);

        echo '<div class="descriptions">';
        if (isset($availableLanguages[$postLocale])) {
            $lang = $availableLanguages[$postLocale];
        } else {
            $lang = $postLocale;
        }
        echo '<p>';
        echo '<strong>', esc_html(__('Language', 'rrze-multilang')), ': </strong>';
        echo esc_html($lang);
        echo '</p>';
        echo '</div>';

        $availableLanguages = Locale::getAvailableLanguages([
            'current_user_can_access' => true
        ]);
        if (isset($availableLanguages[$postLocale])) {
            unset($availableLanguages[$postLocale]);
        }
        echo '<div class="descriptions">';
        printf(
            '<p><strong>%s:</strong></p>',
            esc_html(__('Translations', 'rrze-multilang'))
        );

        echo '<ul id="rrze-multilang-translations">';

        foreach ($translations as $locale => $translation) {
            $editLink = get_edit_post_link($translation->ID);
            echo '<li>';

            if ($editLink) {
                printf(
                    '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s <span class="screen-reader-text">%3$s</span></a>',
                    esc_url($editLink),
                    get_the_title($translation->ID),
                    /* translators: accessibility text */
                    esc_html(__('Opens in a new window.', 'rrze-multilang'))
                );
            } else {
                echo get_the_title($translation->ID);
            }

            if (isset($availableLanguages[$locale])) {
                $lang = $availableLanguages[$locale];
            } else {
                $lang = $locale;
            }

            echo ' [' . $lang . ']';
            echo '</li>';

            // make it unavailable for select options
            unset($availableLanguages[$locale]);
        }

        echo '</ul>';
        echo '</div>';

        if ($initial || empty($availableLanguages)) {
            return;
        }

        echo '<div id="rrze-multilang-add-translation-actions" class="descriptions">';
        printf(
            '<p><strong>%s:</strong></p>',
            esc_html(__('Add Translation', 'rrze-multilang'))
        );
        echo '<select id="rrze-multilang-translations-to-add">';

        foreach ($availableLanguages as $locale => $language) {
            if (isset($translations[$locale])) {
                continue;
            }

            printf(
                '<option value="%1$s">%2$s</option>',
                esc_attr($locale),
                esc_html($language)
            );
        }

        echo '</select>';
        echo '<p>';
        printf(
            '<button type="button" class="button" id="%1$s">%2$s</button>',
            'rrze-multilang-add-translation',
            esc_html(__('Add Translation', 'rrze-multilang'))
        );
        echo '<span class="spinner"></span>';
        echo '</p>';
        echo '<div class="clear"></div>';
        echo '</div>';
    }
}
