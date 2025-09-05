<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Functions;

class RestApi
{
    protected $options;

    protected $siteOptions;

    protected $currentBlogId;

    public function __construct()
    {
        $this->options = (object) Options::getOptions();
        $this->siteOptions = (object) Options::getSiteOptions();

        $this->currentBlogId = get_current_blog_id();

        add_action('rest_api_init', [$this, 'restApiInit']);
    }

    public function restApiInit()
    {
        register_rest_route(
            'rrze-multilang/v1',
            '/link/(?P<id>\d+)/blog/(?P<blogid>\d+)/post/(?P<postid>\d+)',
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'restLinkPost'],
                'permission_callback' => function (\WP_REST_Request $request) {
                    $postId = $request->get_param('id');
                    if (current_user_can('edit_post', $postId)) {
                        return true;
                    } else {
                        return new \WP_Error(
                            'rrze_multilang_locale_forbidden',
                            __('You are not allowed to link posts.', 'rrze-multilang'),
                            ['status' => 403]
                        );
                    }
                },
            ]
        );

        register_rest_route(
            'rrze-multilang/v1',
            '/copy/(?P<id>\d+)/blog/(?P<blogid>\d+)',
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'restCopyPost'],
                'permission_callback' => function (\WP_REST_Request $request) {
                    $postId = $request->get_param('id');
                    if (current_user_can('edit_post', $postId)) {
                        return true;
                    } else {
                        return new \WP_Error(
                            'rrze_multilang_locale_forbidden',
                            __('You are not allowed to access posts in the requested copy.', 'rrze-multilang'),
                            ['status' => 403]
                        );
                    }
                },
            ]
        );
    }

    public function restLinkPost(\WP_REST_Request $request)
    {
        $postId = absint($request->get_param('id'));

        $post = get_post($postId);
        $postType = get_post_type($post);

        if (!$post) {
            return new \WP_Error(
                'rrze_multilang_post_not_found',
                __('The requested post was not found.', 'rrze-multilang'),
                ['status' => 404]
            );
        }

        if (!in_array($postType, $this->options->post_types)) {
            return new \WP_Error(
                'rrze_multilang_post_type_invalid',
                __('The requested post type is not localizable.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $remoteBlogId = absint($request->get_param('blogid'));

        if (
            !isset($this->siteOptions->connections[$remoteBlogId])
            || !in_array($this->currentBlogId, $this->siteOptions->connections[$remoteBlogId])
            || get_blog_status($remoteBlogId, 'archived')
            || get_blog_status($remoteBlogId, 'deleted')
        ) {
            return new \WP_Error(
                'rrze_multilang_post_not_available',
                __('Please select an available post.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $remotePostId = absint($request->get_param('postid'));

        if (!$remotePostId) {
            $reference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
            if (isset($reference[$remoteBlogId])) {
                unset($reference[$remoteBlogId]);
            }
            if (empty($reference[$remoteBlogId])) {
                switch_to_blog($remoteBlogId);
                $remoteReference = [
                    $this->currentBlogId => $postId
                ];
                $this->deleteRemotePostMeta('_rrze_multilang_multiple_reference', $remoteReference, $postType);
                restore_current_blog();
            }
            if (!empty($reference)) {
                update_post_meta($postId, '_rrze_multilang_multiple_reference', $reference);
            } else {
                delete_post_meta($postId, '_rrze_multilang_multiple_reference');
            }

            switch_to_blog($remoteBlogId);
            $remoteBlogName = get_bloginfo('name');
            restore_current_blog();

            $response[$remotePostId] = [
                'blogId' => $remoteBlogId,
                'blogName' => $remoteBlogName,
                'postId' => 0,
                'postTitle' => ''
            ];

            return rest_ensure_response($response);
        }

        switch_to_blog($remoteBlogId);
        $remoteBlogName = get_bloginfo('name');
        $remotePost = get_post($remotePostId);
        restore_current_blog();

        if (!$remotePost) {
            return new \WP_Error(
                'rrze_multilang_post_not_found',
                __('The requested post was not found.', 'rrze-multilang'),
                ['status' => 404]
            );
        }

        if (!in_array($remotePost->post_type, $this->options->post_types)) {
            return new \WP_Error(
                'rrze_multilang_post_type_invalid',
                __('The requested post type is not localizable.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $reference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true);
        if (!$reference) {
            $reference = [
                $remoteBlogId => $remotePostId
            ];
            add_post_meta($postId, '_rrze_multilang_multiple_reference', $reference);
        } else {
            $reference[$remoteBlogId] = $remotePostId;
            update_post_meta($postId, '_rrze_multilang_multiple_reference', $reference);
        }

        switch_to_blog($remoteBlogId);
        $reference = [
            $this->currentBlogId => $postId
        ];
        $this->deleteRemotePostMeta('_rrze_multilang_multiple_reference', $reference, $postType);
        delete_post_meta($remotePostId, '_rrze_multilang_multiple_reference');
        add_post_meta($remotePostId, '_rrze_multilang_multiple_reference', $reference);
        restore_current_blog();

        $response[$remotePostId] = [
            'blogId' => $remoteBlogId,
            'blogName' => $remoteBlogName,
            'postId' => $remotePostId,
            'postTitle' => $remotePost->post_title
        ];

        return rest_ensure_response($response);
    }

    public function restCopyPost(\WP_REST_Request $request)
    {
        if ($blogId = $request->get_param('blogid')) {
            $blogId = (int) $blogId;
        } else {
            return new \WP_Error(
                'rrze_multilang_blog_id_invalid',
                __('The requested website is not available.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        $postId = (int) $request->get_param('id');
        $post = get_post($postId);

        if (!$post) {
            return new \WP_Error(
                'rrze_multilang_post_not_found',
                __('The requested post was not found.', 'rrze-multilang'),
                ['status' => 404]
            );
        }

        $postType = get_post_type($post);

        if (!in_array($postType, $this->options->post_types)) {
            return new \WP_Error(
                'rrze_multilang_post_type_invalid',
                __('The requested post type is not localizable.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        if (
            !isset($this->siteOptions->connections[$blogId])
            || !in_array($this->currentBlogId, $this->siteOptions->connections[$blogId])
            || get_blog_status($blogId, 'archived')
            || get_blog_status($blogId, 'deleted')
        ) {
            return new \WP_Error(
                'rrze_multilang_blog_id_invalid',
                __('The requested website is not available.', 'rrze-multilang'),
                ['status' => 400]
            );
        }

        if (!current_user_can_for_site($blogId, 'edit_posts')) {
            return new \WP_Error(
                'rrze_multilang_copy_forbidden',
                __('You are not allowed to copy posts to the requested website.', 'rrze-multilang'),
                ['status' => 403]
            );
        }

        $newPostId = Post::duplicatePost($postId, $blogId, $postType);

        if (!$newPostId) {
            return new \WP_Error(
                'rrze_multilang_post_duplication_failed',
                __('Failed to copy a post.', 'rrze-multilang'),
                ['status' => 500]
            );
        }

        $blogDetails = get_blog_details(['blog_id' => $blogId]);
        $blogName = $blogDetails->blogname;

        $reference = get_post_meta($postId, '_rrze_multilang_multiple_reference', true) ?: [];
        if (empty($reference[$blogId])) {
            $reference[$blogId] = $newPostId;
            update_post_meta($postId, '_rrze_multilang_multiple_reference', $reference);

            switch_to_blog($blogId);
            $newPost = get_post($newPostId);
            $reference = [
                $this->currentBlogId => $postId
            ];
            $this->deleteRemotePostMeta('_rrze_multilang_multiple_reference', $reference, $postType);
            delete_post_meta($newPostId, '_rrze_multilang_multiple_reference');
            add_post_meta($newPostId, '_rrze_multilang_multiple_reference', $reference);
            restore_current_blog();
        }

        Functions::flashAdminNotice(
            sprintf(
                /* trasnslator: %s is the blog name */
                __('A copy has been added to %s.', 'rrze-multilang'),
                $blogName
            ),
            'updated'
        );

        $response[$blogId] = $blogId;
        $response[$blogId] = [
            'blogId' => $blogId,
            'blogName' => $blogName,
            'postId' => $newPostId,
            'postTitle' => $newPost->post_title
        ];

        return rest_ensure_response($response);
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
