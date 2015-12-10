<?php

namespace Wpbootstrap;

class Pushposts
{
    public $posts = array();
    public $media = array();

    private $bootstrap;
    private $import;
    private $resolver;
    private $log;

    public function __construct()
    {
        $this->bootstrap = Bootstrap::getInstance();
        $this->log = $this->bootstrap->getLog();
        $this->import = Import::getInstance();
        $this->resolver = Resolver::getInstance();

        $dir = BASEPATH.'/bootstrap/posts';
        foreach ($this->bootstrap->getFiles($dir) as $postType) {
            foreach ($this->bootstrap->getFiles($dir.'/'.$postType) as $slug) {
                $newPost = new \stdClass();
                $newPost->done = false;
                $newPost->id = 0;
                $newPost->parentId = 0;
                $newPost->slug = $slug;

                $file = BASEPATH."/bootstrap/posts/$postType/$slug";
                $newPost->post = unserialize(file_get_contents($file));

                $this->posts[] = $newPost;
            }
        }

        $this->resolver->fieldSearchReplace($this->posts, Bootstrap::NETURALURL, $this->import->baseUrl);
        $this->process();
    }

    private function process()
    {
        remove_all_actions('transition_post_status');

        $done = false;
        while (!$done) {
            $deferred = 0;
            foreach ($this->posts as &$post) {
                if (!$post->done) {
                    $parentId = $this->parentId($post->post->post_parent, $this->posts);
                    if ($parentId || $post->post->post_parent == 0) {
                        $this->updatePost($post, $parentId);
                        $post->done = true;
                    } else {
                        ++$deferred;
                    }
                }
            }
            if ($deferred == 0) {
                $done = true;
            }
        }

        $this->importMedia();
    }

    private function updatePost(&$post, $parentId)
    {
        global $wpdb;

        $postId = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s", $post->slug));
        $args = array(
          'post_type' => $post->post->post_type,
          'post_parent' => $parentId,
          'post_title' => $post->post->post_title,
          'post_content' => $post->post->post_content,
          'post_status' => $post->post->post_status,
          'post_name' => $post->post->post_name,
          'post_exerp' => $post->post->post_exerp,
          'ping_status' => $post->post->ping_status,
          'pinged' => $post->post->pinged,
          'comment_status' => $post->post->comment_status,
          'post_date' => $post->post->post_date,
          'post_date_gmt' => $post->post->post_date_gmt,
          'post_modified' => $post->post->post_modified,
          'post_modified_gmt' => $post->post->post_modified_gmt,
        );

        if (!$postId) {
            $postId = wp_insert_post($args);
        } else {
            $args['ID'] = $postId;
            wp_update_post($args);
        }

        $existingMeta = get_post_meta($postId);
        foreach ($post->post->post_meta as $meta => $value) {
            if (isset($existingMeta[$meta])) {
                $existingMetaItem = $existingMeta[$meta];
            }
            $i = 0;
            foreach ($value as $val) {
                if (isset($existingMetaItem[$i])) {
                    update_post_meta($postId, $meta, $val, $existingMetaItem[$i]);
                } else {
                    update_post_meta($postId, $meta, $val);
                }
            }
        }
        $post->id = $postId;
    }

    private function importMedia()
    {
        // check all the media.
        foreach (glob(BASEPATH.'/bootstrap/media/*') as $dir) {
            $item = unserialize(file_get_contents("$dir/meta"));
            $include = false;

            // does this image have an imported post as it's parent?
            $parentId = $this->parentId($item->post_parent, $this->posts);
            if ($parentId != 0) {
                $this->log->addDebug('Media is attached to post', array($item->ID), array($parentId));
                $include = true;
            }

            // does an imported post have this image as thumbnail?
            $isAThumbnail = $this->isAThumbnail($item->ID);
            if ($isAThumbnail) {
                $this->log->addDebug('Media is thumbnail to (at least) one post', array($item->id));
                $include = true;
            }

            if (!$include) {
                continue;
            }

            $args = array(
                'name' => $item->post_name,
                'post_type' => $item->post_type,
            );

            $file = $item->post_meta['_wp_attached_file'][0];
            $id = 0;
            $existing = new \WP_Query($args);
            if (!$existing->have_posts()) {
                $args = array(
                    'post_title' => $item->post_title,
                    'post_name' => $item->post_name,
                    'post_type' => $item->post_type,
                    'post_parent' => $parentId,
                    'post_status' => $item->post_status,
                    'post_mime_type' => $item->post_mime_type,
                    'guid' => $this->import->uploadDir['basedir'].'/'.$file,
                );
                $id = wp_insert_post($args);
            } else {
                $id = $existing->post->ID;
            }
            update_post_meta($id, '_wp_attached_file', $file);

            // move the file
            $src = $dir.'/'.basename($file);
            $trg = $this->import->uploadDir['basedir'].'/'.$file;
            @mkdir($this->import->uploadDir['basedir'].'/'.dirname($file), 0777, true);
            if (file_exists($src)) {
                copy($src, $trg);
            }

            // create metadata and other sizes
            $attachData = wp_generate_attachment_metadata($id, $trg);
            wp_update_attachment_metadata($id, $attachData);

            // set this image as a thumbnail if needed
            if ($isAThumbnail) {
                $this->setAsThumbnail($item->ID, $id);
            }

            $mediaItem = new \stdClass();
            $mediaItem->meta = $item;
            $mediaItem->id = $id;
            $this->media[] = $mediaItem;
        }
    }

    private function parentId($foreignParentId, $objects)
    {
        foreach ($objects as $object) {
            if ($object->post->ID == $foreignParentId) {
                return $object->id;
            }
        }

        return 0;
    }

    private function isAThumbnail($id)
    {
        foreach ($this->posts as $post) {
            if (isset($post->post->post_meta['_thumbnail_id'])) {
                $thumbId = $post->post->post_meta['_thumbnail_id'][0];
                if ($thumbId == $id) {
                    return true;
                }
            }
        }

        return false;
    }

    private function setAsThumbnail($oldId, $newId)
    {
        foreach ($this->posts as $post) {
            if (isset($post->post->post_meta['_thumbnail_id'])) {
                $thumbId = $post->post->post_meta['_thumbnail_id'][0];
                if ($thumbId == $oldId) {
                    set_post_thumbnail($post->id, $newId);
                }
            }
        }
    }

    public function findTargetPostId($target)
    {
        foreach ($this->posts as $post) {
            if ($post->post->ID == $target) {
                return $post->id;
            }
        }

        foreach ($this->media as $media) {
            if ($media->meta->ID == $target) {
                return $media->id;
            }
        }

        return 0;
    }
}
