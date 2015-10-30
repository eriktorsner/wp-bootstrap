<?php
namespace Wpbootstrap;

class Pushposts
{
    public $posts = array();
    public $media = array();

    private $bootstrap;
    private $import;
    private $resolver;

    public function __construct()
    {
        $this->bootstrap = Bootstrap::getInstance();
        $this->import = Import::getInstance();
        $this->resolver = Resolver::getInstance();

        foreach ($this->bootstrap->appSettings->wpbootstrap->posts as $postType => $val) {
            foreach ($val as $slug) {
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
                        $deferred++;
                    }
                }
            }
            if ($deferred == 0) {
                $done = true;
            }
        }

        // check all the media.
        foreach (glob(BASEPATH.'/bootstrap/media/*') as $dir) {
            $item = unserialize(file_get_contents("$dir/meta"));
            $parentId = $this->parentId($item->post_parent, $this->posts);
            if ($parentId == 0) {
                continue;
            }

            $args = array(
                'name' => $item->post_name,
                'post_type' => $item->post_type,
            );
            $id = 0;
            $existing = new \WP_Query($args);
            if (!$existing->have_posts()) {
                $args = array(
                    'post_title'     => $item->post_title,
                    'post_name'      => $item->post_name,
                    'post_type'      => $item->post_type,
                    'post_parent'    => $parentId,
                    'post_status'    => $item->post_status,
                    'post_mime_type' => $item->post_mime_type,
                );
                $id = wp_insert_post($args);
            } else {
                $id = $existing->post->ID;
            }
            update_post_meta($id, '_wp_attached_file', $item->meta['file']);

            // move the file
            $src = $dir.'/'.basename($item->meta['file']);
            $trg = $this->import->uploadDir['basedir'].'/'.$item->meta['file'];
            @mkdir($this->import->uploadDir['basedir'].'/'.dirname($item->meta['file']), 0777, true);
            copy($src, $trg);
            // create metadata and other sizes
            $attachData = wp_generate_attachment_metadata($id, $trg);
            wp_update_attachment_metadata($id, $attachData);

            $mediaItem = new \stdClass();
            $mediaItem->meta = $item;
            $mediaItem->id = $id;
            $this->media[] = $mediaItem;
        }
    }

    private function updatePost(&$post, $parentId)
    {
        global $wpdb;

        $postId = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s", $post->slug));
        $args = array(
          'post_type'      => $post->post->post_type,
          'post_parent'      => $parentId,
          'post_title'     => $post->post->post_title,
          'post_content'   => $post->post->post_content,
          'post_status'    => $post->post->post_status,
          'post_name'      => $post->post->post_name,
          'post_exerp'     => $post->post->post_exerp,
          'ping_status'    => $post->post->ping_status,
          'comment_status' => $post->post->comment_status,
          //'page_template'  => $post->post->page_template_slug,
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

    private function parentId($foreignParentId, $objects)
    {
        foreach ($objects as $object) {
            if ($object->post->ID == $foreignParentId) {
                return $object->id;
            }
        }

        return 0;
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
