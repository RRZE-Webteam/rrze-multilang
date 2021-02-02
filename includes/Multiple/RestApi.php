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

        add_action('rest_api_init', [$this, 'restApiInit'], 10, 0);
    }

    public function restApiInit()
    {
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

    public function restCopyPost(\WP_REST_Request $request)
    {
        $postId = $request->get_param('id');

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

        $blogId = $request->get_param('blogid');

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

        $newPostId = Post::duplicatePost($postId, $blogId, $postType);

        if (!$newPostId) {
            return new \WP_Error(
                'rrze_multilang_post_duplication_failed',
                __('Failed to duplicate a post.', 'rrze-multilang'),
                ['status' => 500]
            );
        }

        $blogDetails = get_blog_details(['blog_id' => $blogId]);
        $blogName = $blogDetails->blogname;

        $newPost = get_post($newPostId);

        $postTypeObj = get_post_type_object($newPost->post_type);
        $postTypeLabel = $postTypeObj->labels->singular_name;
        Functions::flashAdminNotice(sprintf(__('A copy of this %1$s has been added on %2$s', 'rrze-multilang'), $postTypeLabel, $blogName), 'updated');

        $response[$blogId] = $blogId;

        return rest_ensure_response($response);
    }
}
