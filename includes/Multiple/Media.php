<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

use RRZE\Multilang\Functions;

class Media
{
    public function mediaCopy($postId, $targetId)
    {
        $media = get_post($postId);
        $mediaUrl = wp_get_attachment_url($postId);
        $filetype = wp_check_filetype($mediaUrl, null);
        $imageAlt = get_post_meta($postId, '_wp_attachment_image_alt', true);
        $sourceId = get_current_blog_id();

        $info = pathinfo($mediaUrl);
        $fileName = basename($mediaUrl, '.' . $info['extension']);

        $metaValues = apply_filters('rrze_multilang_filter_media_meta', get_post_meta($postId));

        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name($fileName),
            'post_content'   => $media->post_content,
            'post_status'    => 'inherit',
            'post_excerpt'   => $media->post_excerpt,
            'post_name'      => $media->post_name,
        ];

        switch_to_blog($targetId);

        $attachmentId = self::copyFileToTarget($attachment, $mediaUrl, 0, $sourceId, $media->ID);

        Post::processMeta($attachmentId, $metaValues);

        if ($imageAlt) {
            update_post_meta($attachmentId, '_wp_attachment_image_alt', $imageAlt);
        }

        restore_current_blog();

        return $attachmentId;
    }

    public static function copyFileToTarget($attachment, $img_url, $postId = 0, $sourceId = 0, $fileId = 0)
    {
        $info = pathinfo($img_url);
        $fileName  = basename($img_url, '.' . $info['extension']);

        $uploadDir = wp_upload_dir();

        if (wp_mkdir_p($uploadDir['path'])) {

            $file = $uploadDir['path'] . '/' . $fileName . '.' . $info['extension'];
        } else {

            $file = $uploadDir['basedir'] . '/' . $fileName . '.' . $info['extension'];
        }

        if ($theOriginalId = self::doesFileExist($fileId, $sourceId, get_current_blog_id())) {

            return $theOriginalId;
        }

        $filtered_url = Functions::fixUrl($img_url);

        if ($filtered_url && $filtered_url != '') {

            copy($filtered_url, $file);

            $attachmentId = wp_insert_attachment($attachment, $file, $postId);

            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $attachmentData = wp_generate_attachment_metadata($attachmentId, $file);

            wp_update_attachment_metadata($attachmentId, $attachmentData);

            do_action('rrze_multilang_media_image_added', $attachmentId, $sourceId, $fileId);
        }


        return $attachmentId;
    }

    public static function doesFileExist($sourceFileId, $sourceId, $targetId)
    {
        global $wpdb;

        $targetTablename = Functions::getTablename($targetId, 'postmeta');

        $metaId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_id FROM $targetTablename  WHERE meta_key = %s AND meta_value = %d",
                'rrze_multilang_media_source_' . $sourceId,
                $sourceFileId
            )
        );

        if (null !== $metaId) {

            $sourceTablename = Functions::getTablename($sourceId);

            $currentModTime = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_modified FROM $sourceTablename WHERE ID = %d",
                    $sourceFileId
                )
            );

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $targetTablename  WHERE meta_key = 'rrze_multilang_meta_id_%d'",
                    $metaId
                )
            );

            if (null !== $row) {
                $pastModTime = $row->meta_value;

                if ($pastModTime == $currentModTime) {
                    return $row->post_id;
                } else {
                    return false;
                }
            }
        }

        return false;
    }

    public static function getFeaturedImageFromSource($postId)
    {

        $thumbnailId = get_post_thumbnail_id($postId);
        $image = wp_get_attachment_image_src($thumbnailId, 'full');

        if ($image) {

            $imageDetails = [
                'id'            => $thumbnailId,
                'url'           => get_attached_file($thumbnailId),
                'alt'           => get_post_meta($thumbnailId, '_wp_attachment_image_alt', true),
                'post_title'    => get_post_field('post_title', $thumbnailId),
                'description'   => get_post_field('post_content', $thumbnailId),
                'caption'       => get_post_field('post_excerpt', $thumbnailId),
                'post_name'     => get_post_field('post_name', $thumbnailId)

            ];

            $imageDetails = apply_filters('rrze_multilang_featured_image', $imageDetails);

            return $imageDetails;
        }
    }

    public static function setFeaturedImageToTarget($targetId, $imageDetails, $source_blog_id)
    {
        $uploadDir = wp_upload_dir();

        $filename = apply_filters('rrze_multilang_featured_image_filename', basename($imageDetails['url']), $imageDetails);

        if (wp_mkdir_p($uploadDir['path'])) {
            $file = $uploadDir['path'] . '/' . $filename;
        } else {
            $file = $uploadDir['basedir'] . '/' . $filename;
        }

        if ($theOriginalId = self::doesFileExist($imageDetails['id'], $source_blog_id, get_current_blog_id())) {
            $filetype = wp_check_filetype($filename, null);
            $attachment = [
                'ID' => $theOriginalId,
                'post_parent' => $targetId,
                'post_mime_type' => $filetype['type'],
                'post_title'     => $imageDetails['post_title'],
                'post_content'   => $imageDetails['description'],
                'post_status'    => 'inherit',
                'post_excerpt'   => $imageDetails['caption'],
                'post_name'      => $imageDetails['post_name']
            ];

            $attachmentId = wp_insert_attachment($attachment);
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $attachmentData = wp_generate_attachment_metadata($attachmentId, $file);
            wp_update_attachment_metadata($attachmentId, $attachmentData);
            set_post_thumbnail($targetId, $attachmentId);
        } else {

            if ($imageDetails['url'] && $file) {
                copy($imageDetails['url'], $file);
            }

            $filetype = wp_check_filetype($filename, null);
            $newFileUrl  = $uploadDir['url'] . '/' . $filename;

            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title'     => $imageDetails['post_title'],
                'post_content'   => $imageDetails['description'],
                'post_status'    => 'inherit',
                'post_excerpt'   => $imageDetails['caption'],
                'post_name'      => $imageDetails['post_name']
            ];

            $attachmentId = wp_insert_attachment($attachment, $file, $targetId);

            if ($imageDetails['alt']) {
                update_post_meta($attachmentId, '_wp_attachment_image_alt', $imageDetails['alt']);
            }

            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $attachmentData = wp_generate_attachment_metadata($attachmentId, $file);

            wp_update_attachment_metadata($attachmentId, $attachmentData);

            do_action('rrze_multilang_media_image_added', $attachmentId, $source_blog_id, $imageDetails['id']);

            set_post_thumbnail($targetId, $attachmentId);
        }
    }

    public static function getImagesFromTheContent($postId)
    {
        $html = get_post_field('post_content', $postId);
        if (empty($html)) {
            return '';
        }
        $doc = new \DOMDocument();

        @$doc->loadHTML($html);
        $tags = $doc->getElementsByTagName('img');

        if ($tags) {
            $imagesObjectsFromPost = [];
            foreach ($tags as $tag) {
                preg_match("/(?<=wp-image-)\d+/", $tag->getAttribute('class'), $matches);
                $imageObj = get_post($matches[0]);
                $imagesObjectsFromPost[$matches[0]] = [
                    'attached_file_path' => get_attached_file($matches[0]),
                    'object' => $imageObj
                ];
            }
            return $imagesObjectsFromPost;
        }
    }

    public static function getImageAltTags($postMediaAttachments)
    {
        if ($postMediaAttachments) {
            $altTagsToBeCopied = [];
            $attachementCount = 0;

            foreach ($postMediaAttachments as $postMediaImgData) {
                $postMediaAttachment = $postMediaImgData['object'];
                $fileFullpath = $postMediaImgData['attached_file_path'];

                $alt_tag = get_post_meta($postMediaAttachment->ID, '_wp_attachment_image_alt', true);
                $altTagsToBeCopied[$attachementCount] = $alt_tag;
                $attachementCount++;
            }

            $altTagsToBeCopied = apply_filters('rrze_multilang_alt_tag_array_from_post_content', $altTagsToBeCopied, $postMediaAttachments);

            return $altTagsToBeCopied;
        }
    }

    public static function processPostMediaAttachements($targetPostId, $postMediaAttachments, $attachedImagesAltTags, $sourceId, $newBlogId)
    {
        $imageCount = 0;

        $oldImageIds = array_keys($postMediaAttachments);

        foreach ($postMediaAttachments as $postMediaImgData) {
            $postMediaAttachment = $postMediaImgData['object'];
            $fileFullpath = $postMediaImgData['attached_file_path'];

            if ($fileFullpath && file_exists($fileFullpath)) {
                $imageUrlInfo = pathinfo(self::getAttachmentUrl($postMediaAttachment->ID, $sourceId));
                $imageUrlWithoutExt = $imageUrlInfo['dirname'] . "/" . $imageUrlInfo['filename'];
                $imageUrlWithoutExt = str_replace(get_blog_details($newBlogId)->siteurl, get_blog_details($sourceId)->siteurl, $imageUrlWithoutExt);

                $filename = basename($fileFullpath);

                $uploadDir = wp_upload_dir();

                if (wp_mkdir_p($uploadDir['path'])) {
                    $file = $uploadDir['path'] . '/' . $filename;
                } else {
                    $file = $uploadDir['basedir'] . '/' . $filename;
                }

                $newFileUrl = $uploadDir['url'] . '/' . $filename;
                $newFileUrl = str_replace(get_blog_details($sourceId)->siteurl, get_blog_details($newBlogId)->siteurl, $newFileUrl);

                if ($theOriginalId = self::doesFileExist($postMediaAttachment->ID, $sourceId, $newBlogId)) {

                    $filetype = wp_check_filetype($filename, null);

                    $attachment = [
                        'ID' => $theOriginalId,
                        'post_parent' => $targetPostId,
                        'post_mime_type' => $filetype['type'],
                        'post_title'     => sanitize_file_name($filename),
                        'post_content'   => $postMediaAttachment->post_content,
                        'post_status'    => 'inherit',
                        'post_excerpt'   => $postMediaAttachment->post_excerpt,
                        'post_name'      => $postMediaAttachment->post_name,
                        'guid'           => $newFileUrl
                    ];

                    $attachmentId = wp_insert_attachment($attachment);

                    require_once(ABSPATH . 'wp-admin/includes/image.php');

                    $attachmentData = wp_generate_attachment_metadata($attachmentId, $file);

                    wp_update_attachment_metadata($attachmentId, $attachmentData);
                } else {
                    copy($fileFullpath, $file);

                    $filetype = wp_check_filetype($filename, null);

                    $attachment = apply_filters(
                        'rrze_multilang_post_media_attachments',
                        [
                            'post_mime_type' => $filetype['type'],
                            'post_title'     => sanitize_file_name($filename),
                            'post_content'   => $postMediaAttachment->post_content,
                            'post_status'    => 'inherit',
                            'post_excerpt'   => $postMediaAttachment->post_excerpt,
                            'post_name'      => $postMediaAttachment->post_name,
                            'guid'           => $newFileUrl
                        ],
                        $postMediaAttachment
                    );

                    $attachmentId = wp_insert_attachment($attachment, $file, $targetPostId);

                    if ($attachedImagesAltTags) {
                        update_post_meta($attachmentId, '_wp_attachment_image_alt', $attachedImagesAltTags[$imageCount]);
                    }

                    require_once(ABSPATH . 'wp-admin/includes/image.php');

                    $attachmentData = wp_generate_attachment_metadata($attachmentId, $file);

                    wp_update_attachment_metadata($attachmentId, $attachmentData);

                    do_action('rrze_multilang_media_image_added', $attachmentId, $sourceId, $postMediaAttachment->ID);
                }

                $newImageUrlWithoutExt = self::getImageNewUrlWithoutExt($attachmentId, $sourceId, $newBlogId, $newFileUrl);

                $oldContent = get_post_field('post_content', $targetPostId);
                $middleContent = str_replace($imageUrlWithoutExt, $newImageUrlWithoutExt, $oldContent);
                $updateContent = str_replace('wp-image-' . $oldImageIds[$imageCount], 'wp-image-' . $attachmentId, $middleContent);

                $post_update = [
                    'ID'           => $targetPostId,
                    'post_content' => $updateContent
                ];

                wp_update_post($post_update);

                $imageCount++;
            }
        }
    }

    public static function getAttachmentUrl($ID, $source_blog)
    {
        switch_to_blog($source_blog);
        $attachment_url = wp_get_attachment_url($ID);
        restore_current_blog();

        return $attachment_url;
    }

    public static function getImageNewUrlWithoutExt($attachmentId, $sourceId, $newBlogId, $newFileUrl)
    {
        $newImageUrlWithExt = pathinfo($newFileUrl);

        $newImageUrlWithoutExt  = $newImageUrlWithExt['dirname'] . "/" . $newImageUrlWithExt['filename'];

        if (network_site_url() != get_blog_details($sourceId)->siteurl . "/") {
            $newImageUrlWithoutExt  = str_replace(get_blog_details($sourceId)->siteurl, get_blog_details($newBlogId)->siteurl, $newImageUrlWithoutExt);
        }

        return $newImageUrlWithoutExt;
    }
}
