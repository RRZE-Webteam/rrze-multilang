<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

class Capabilities
{
    public function __construct()
    {
        add_filter('map_meta_cap', [$this, 'mapMetaCap'], 10, 4);
    }

    public function mapMetaCap($caps, $cap, $userId, $args)
    {
        $metaCaps = [
            'rrze_multilang_edit_terms_translations' => 'manage_categories',
            'rrze_multilang_access_all_locales' => 'edit_published_posts',
            'rrze_multilang_access_locale' => 'read'
        ];

        $metaCaps = apply_filters('rrze_multilang_map_meta_cap', $metaCaps);

        $caps = array_diff($caps, array_keys($metaCaps));

        if (isset($metaCaps[$cap])) {
            $caps[] = $metaCaps[$cap];
        }

        static $accessibleLocales = [];

        if (
            'rrze_multilang_access_all_locales' !== $cap
            && !isset($accessibleLocales[$userId])
        ) {
            $accessibleLocales[$userId] = Users::getUserAccessibleLocales(
                $userId
            );
        }

        if (
            'rrze_multilang_access_locale' === $cap
            && !user_can($userId, 'rrze_multilang_access_all_locales')
        ) {
            $locale = $args[0];

            if (!in_array($locale, $accessibleLocales[$userId])) {
                $caps[] = 'do_not_allow';
            }
        }

        if (isset($args[0])) {
            $post = get_post($args[0]);
        }
        
        if (
            in_array($cap, ['edit_post', 'delete_post'], true)
            && isset($post->post_author)
            && $userId !== $post->post_author
            && !user_can($userId, 'rrze_multilang_access_all_locales')
        ) {
            $locale = Post::getPostLocale($post->ID);

            if (!in_array($locale, $accessibleLocales[$userId])) {
                $caps[] = 'do_not_allow';
            }
        }

        return $caps;
    }
}
