<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Functions;
use RRZE\Multilang\Locale;

class RestApi
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'restApiInit'], 10, 0);
    }

    public function restApiInit()
    {
        register_rest_route(
            'rrze-multilang/v1',
            '/languages',
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'restLanguages'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'rrze-multilang/v1',
            '/posts/(?P<id>\d+)/translations',
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'restPostTranslations'],
                'permission_callback' => '__return_true',
            ]
        );

        $localePattern = '[a-z]{2,3}(?:_[A-Z]{2}(?:_[A-Za-z0-9]+)?)?';

        register_rest_route(
            'rrze-multilang/v1',
            '/posts/(?P<id>\d+)/translations/(?P<locale>' . $localePattern . ')',
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'restCreatePostTranslation'],
                'permission_callback' => function (\WP_REST_Request $request) {
                    $locale = $request->get_param('locale');

                    if (current_user_can('rrze_multilang_access_locale', $locale)) {
                        return true;
                    } else {
                        return new \WP_Error(
                            'rrze_multilang_locale_forbidden',
                            __('You are not allowed to access posts in the requested locale.', 'rrze-multilang'),
                            ['status' => 403]
                        );
                    }
                },
            ]
        );
    }

    public function restLanguages(\WP_REST_Request $request)
    {
        if (!function_exists('wp_get_available_translations')) {
            require_once ABSPATH . 'wp-admin/includes/translation-install.php';
        }

        $availableTranslations = wp_get_available_translations();

        $localAvailableLocales = Locale::availableLocales();

        $availableTranslations = array_intersect_key(
            $availableTranslations,
            array_flip($localAvailableLocales)
        );

        return rest_ensure_response($availableTranslations);
    }

    public function restPostTranslations(\WP_REST_Request $request)
    {
        $postId = $request->get_param('id');

        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error(
                'rrze_multilang_post_not_found',
                __('The requested post was not found.', 'rrze-multilang'),
                ['status' => 404]
            );
        }

        if (!Post::isLocalizablePostType($post->post_type)) {
            return new \WP_Error(
                'rrze_multilang_post_type_invalid',
                __('The requested post type is not localizable.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $postType_object = get_post_type_object($post->post_type);
        $edit_post_cap = $postType_object->cap->edit_post;

        if (
            !current_user_can($edit_post_cap, $post->ID)
            && 'publish' !== get_post_status($post)
        ) {
            return new \WP_Error(
                'rrze_multilang_post_not_found',
                __('The requested post was not found.', 'rrze-multilang'),
                ['status' => 404]
            );
        }

        $response = [];
        $translations = Post::getPostTranslations($post);

        foreach ($translations as $locale => $translation) {
            if (
                !current_user_can('edit_post', $translation->ID)
                && 'publish' !== get_post_status($translation)
            ) {
                continue;
            }

            $response[$locale] = [
                'lang' => ['tag' => Locale::languageTag($locale)],
                'id' => $translation->ID,
                'link' => get_permalink($translation->ID),
                'slug' => $translation->post_name,
                'type' => $translation->post_type,
                'date' => mysql_to_rfc3339($translation->post_date),
                'date_gmt' => mysql_to_rfc3339($translation->post_date_gmt),
                'modified' => mysql_to_rfc3339($translation->post_modified),
                'modified_gmt' => mysql_to_rfc3339($translation->post_modified_gmt),
                'guid' => ['rendered' => '', 'raw' => ''],
                'title' => ['rendered' => '', 'raw' => ''],
                'content' => ['rendered' => '', 'raw' => ''],
                'excerpt' => ['rendered' => '', 'raw' => ''],
            ];

            $lang = Locale::getLanguage($locale);
            $lang = empty($lang) ? $locale : $lang;
            $response[$locale]['lang']['name'] = $lang;

            if (!empty($translation->guid)) {
                $response[$locale]['guid']['rendered'] =
                    apply_filters('get_the_guid', $translation->guid);

                if (current_user_can($edit_post_cap, $translation->ID)) {
                    $response[$locale]['guid']['raw'] = $translation->guid;
                }
            }

            if (!empty($translation->post_title)) {
                $response[$locale]['title']['rendered'] =
                    get_the_title($translation->ID);

                if (current_user_can($edit_post_cap, $translation->ID)) {
                    $response[$locale]['title']['raw'] = $translation->post_title;
                }
            }

            if (!empty($translation->post_content)) {
                $response[$locale]['content']['rendered'] = apply_filters(
                    'the_content',
                    $translation->post_content
                );

                if (current_user_can($edit_post_cap, $translation->ID)) {
                    $response[$locale]['content']['raw'] = $translation->post_content;
                }
            }

            if (!empty($translation->post_excerpt)) {
                $response[$locale]['excerpt']['rendered'] = apply_filters(
                    'the_excerpt',
                    apply_filters('get_the_excerpt', $translation->post_excerpt)
                );

                if (current_user_can($edit_post_cap, $translation->ID)) {
                    $response[$locale]['excerpt']['raw'] = $translation->post_excerpt;
                }
            }
        }

        return rest_ensure_response($response);
    }

    public function restCreatePostTranslation(\WP_REST_Request $request)
    {
        $postId = $request->get_param('id');

        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error(
                'rrze_multilang_post_not_found',
                __('The requested post was not found.', 'rrze-multilang'),
                ['status' => 404]
            );
        }

        if (!Post::isLocalizablePostType($post->post_type)) {
            return new \WP_Error(
                'rrze_multilang_post_type_invalid',
                __('The requested post type is not localizable.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $locale = $request->get_param('locale');

        if (!Locale::isAvailableLocale($locale)) {
            return new \WP_Error(
                'rrze_multilang_locale_invalid',
                __('The requested locale is not available.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $newPostId = Post::duplicatePost($post, $locale);

        if (!$newPostId) {
            return new \WP_Error(
                'rrze_multilang_post_duplication_failed',
                __('Failed to duplicate a post.', 'rrze-multilang'),
                ['status' => 500]
            );
        }

        $newPost = get_post($newPostId);
        $response = [];

        $response[$locale] = [
            'lang' => ['tag' => Locale::languageTag($locale)],
            'id' => $newPost->ID,
            'link' => get_permalink($newPost->ID),
            'edit_link' => get_edit_post_link($newPost->ID, 'raw'),
            'slug' => $newPost->post_name,
            'type' => $newPost->post_type,
            'date' => mysql_to_rfc3339($newPost->post_date),
            'date_gmt' => mysql_to_rfc3339($newPost->post_date_gmt),
            'modified' => mysql_to_rfc3339($newPost->post_modified),
            'modified_gmt' => mysql_to_rfc3339($newPost->post_modified_gmt),
            'guid' => ['rendered' => '', 'raw' => $newPost->guid],
            'title' => ['rendered' => '', 'raw' => $newPost->post_title],
            'content' => ['rendered' => '', 'raw' => $newPost->post_content],
            'excerpt' => ['rendered' => '', 'raw' => $newPost->post_excerpt],
        ];

        $lang = Locale::getLanguage($locale);
        $lang = empty($lang) ? $locale : $lang;
        $response[$locale]['lang']['name'] = $lang;

        if (!empty($newPost->guid)) {
            $response[$locale]['guid']['rendered'] =
                apply_filters('get_the_guid', $newPost->guid);
        }

        if (!empty($newPost->post_title)) {
            $response[$locale]['title']['rendered'] =
                get_the_title($newPost->ID);
        }

        if (!empty($newPost->post_content)) {
            $response[$locale]['content']['rendered'] =
                apply_filters('the_content', $newPost->post_content);
        }

        if (!empty($newPost->post_excerpt)) {
            $response[$locale]['excerpt']['rendered'] = apply_filters(
                'the_excerpt',
                apply_filters('get_the_excerpt', $newPost->post_excerpt)
            );
        }

        //$postTypeObj = get_post_type_object($newPost->post_type);
        //$postTypeLabel = $postTypeObj->labels->singular_name;

        Functions::flashAdminNotice(
            sprintf(
                /* translators: %s: The language of the added translation. */
                __('A %s translation has been added.', 'rrze-multilang'),
                $lang
            ),
            'updated'
        );

        return rest_ensure_response($response);
    }
}
