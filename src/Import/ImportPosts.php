<?php

namespace Wpbootstrap\Import;

use Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ImportPosts
 * @package Wpbootstrap\Import
 */
class ImportPosts
{
    /**
     * @var array
     */
    public $posts = array();

    /**
     * @var array
     */
    public $media = array();


    public function __construct()
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }

    /**
     * The main import process
     */
    public function import()
    {
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $yaml = new Yaml();

        $dir = BASEPATH . '/bootstrap/posts';
        foreach ($helpers->getFiles($dir) as $postType) {
            foreach ($helpers->getFiles($dir . '/' . $postType) as $slug) {
                $newPost = new \stdClass();
                $newPost->done = false;
                $newPost->id = 0;
                $newPost->parentId = 0;
                $newPost->slug = $slug;
                $newPost->type = $postType;
                $newPost->tries = 0;

                $file = BASEPATH . "/bootstrap/posts/$postType/$slug";
                $newPost->post = $yaml->parse(file_get_contents($file));

                $this->posts[] = $newPost;
            }
        }

        if (count($this->posts) > 0) {
            $this->internalImport();
        }
    }

    private function internalImport()
    {
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];
        $baseUrl = get_option('siteurl');

        $helpers->fieldSearchReplace($this->posts, Bootstrap::NEUTRALURL, $baseUrl);
        remove_all_actions('transition_post_status');

        $done = false;
        while (!$done) {
            $deferred = 0;
            foreach ($this->posts as &$post) {
                $post->tries++;
                if (!$post->done) {
                    $parentId = $this->parentId($post->post['post_parent'], $this->posts);
                    if ($parentId || $post->post['post_parent'] == 0 || $post->tries > 9) {
                        $this->updatePost($post, $parentId);
                        $post->done = true;
                    } else {
                        $deferred++;
                    }
                }
            }
            if ($deferred == 0) {
                $done = true;
            }
        }

        $this->importMedia();
    }

    /**
     * Update individual post
     *
     * @param $post
     * @param $parentId
     */
    private function updatePost(&$post, $parentId)
    {
        global $wpdb;
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];

        $sql = $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s",
            $post->slug,
            $post->type
        );

        $postId = $wpdb->get_var($sql);
        $args = array(
          'post_type' => $post->post['post_type'],
          'post_mime_type' => $post->post['post_mime_type'],
          'post_parent' => $parentId,
          'post_title' => $post->post['post_title'],
          'post_content' => $post->post['post_content'],
          'post_status' => $post->post['post_status'],
          'post_name' => $post->post['post_name'],
          'post_excerpt' => $post->post['post_excerpt'],
          'ping_status' => $post->post['ping_status'],
          'pinged' => $post->post['pinged'],
          'comment_status' => $post->post['comment_status'],
          'post_date' => $post->post['post_date'],
          'post_date_gmt' => $post->post['post_date_gmt'],
          'post_modified' => $post->post['post_modified'],
          'post_modified_gmt' => $post->post['post_modified_gmt'],
        );

        if (!$postId) {
            $postId = wp_insert_post($args);
        } else {
            $args['ID'] = $postId;
            wp_update_post($args);
        }

        $existingMeta = get_post_meta($postId);
        foreach ($post->post['post_meta'] as $meta => $value) {
            if (isset($existingMeta[$meta])) {
                $existingMetaItem = $existingMeta[$meta];
            }
            $i = 0;
            foreach ($value as $val) {
                if ($helpers->isSerialized($val)) {
                    $val = unserialize($val);
                }
                if (isset($existingMetaItem[$i])) {
                    update_post_meta($postId, $meta, $val, $existingMetaItem[$i]);
                } else {
                    update_post_meta($postId, $meta, $val);
                }
            }
        }
        $post->id = $postId;
    }

    /**
     * Import media file and meta data
     */
    private function importMedia()
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];
        $uploadDir = wp_upload_dir();
        $yaml = new Yaml();

        // check all the media.
        foreach (glob(BASEPATH.'/bootstrap/media/*') as $dir) {
            $item = $yaml->parse(file_get_contents("$dir/meta"));

            // does this image have an imported post as it's parent?
            $parentId = $this->parentId($item['post_parent'], $this->posts);
            if ($parentId != 0) {
                $cli->debug("Media is attached to post {$item['ID']} $parentId");
            }

            // does an imported post have this image as thumbnail?
            $isAThumbnail = $this->isAThumbnail($item['ID']);
            if ($isAThumbnail) {
                $cli->debug('Media is thumbnail to (at least) one post  ' . $item['ID']);
            }

            $args = array(
                'name' => $item['post_name'],
                'post_type' => $item['post_type'],
            );

            $file = $item['post_meta']['_wp_attached_file'][0];

            $existing = new \WP_Query($args);
            if (!$existing->have_posts()) {
                $args = array(
                    'post_title' => $item['post_title'],
                    'post_name' => $item['post_name'],
                    'post_type' => $item['post_type'],
                    'post_parent' => $parentId,
                    'post_status' => $item['post_status'],
                    'post_mime_type' => $item['post_mime_type'],
                    'guid' => $uploadDir['basedir'].'/'.$file,
                );
                $id = wp_insert_post($args);
            } else {
                $id = $existing->post['ID'];
            }
            update_post_meta($id, '_wp_attached_file', $file);

            // move the file
            $src = $dir.'/'.basename($file);
            $trg = $uploadDir['basedir'].'/'.$file;
            @mkdir($uploadDir['basedir'].'/'.dirname($file), 0777, true);

            if (file_exists($src) && file_exists($trg)) {
                if (filesize($src) == filesize($trg)) {
                    // set this image as a thumbnail and then exit
                    if ($isAThumbnail) {
                        $this->setAsThumbnail($item->ID, $id);
                    }
                    continue;
                }
            }

            if (file_exists($src)) {
                copy($src, $trg);
                // create metadata and other sizes
                $attachData = wp_generate_attachment_metadata($id, $trg);
                wp_update_attachment_metadata($id, $attachData);
            }

            // set this image as a thumbnail if needed
            if ($isAThumbnail) {
                $this->setAsThumbnail($item['ID'], $id);
            }
        }
    }

    /**
     * Finds a post parent based on it's original id. If found, returns the new (after import) id
     *
     * @param int $foreignParentId
     * @param array $objects
     * @return int
     */
    private function parentId($foreignParentId, $objects)
    {
        foreach ($objects as $object) {
            if ($object->post['ID'] == $foreignParentId) {
                return $object->id;
            }
        }

        return 0;
    }

    /**
     * Checks if a media/attachment is serves as a thumbnail for another post
     *
     * @param int $id
     * @return bool
     */
    private function isAThumbnail($id)
    {
        foreach ($this->posts as $post) {
            if (isset($post->post['post_meta']['_thumbnail_id'])) {
                $thumbId = $post->post['post_meta']['_thumbnail_id'][0];
                if ($thumbId == $id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Assigns an attachment as a thumbnail
     *
     * @param int $oldId
     * @param int $newId
     */
    private function setAsThumbnail($oldId, $newId)
    {
        foreach ($this->posts as $post) {
            if (isset($post->post['post_meta']['_thumbnail_id'])) {
                $thumbId = $post->post['post_meta']['_thumbnail_id'][0];
                if ($thumbId == $oldId) {
                    set_post_thumbnail($post->id, $newId);
                }
            }
        }
    }
}
