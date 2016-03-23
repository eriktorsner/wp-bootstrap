<?php

namespace Wpbootstrap;

/**
 * Class ExportPosts
 * @package Wpbootstrap
 */
class ExportPosts extends ExportBase
{
    /**
     * @var \stdClass
     */
    private $posts;

    /**
     * Keep track of taxonomies to exclude
     *
     * @var array
     */
    private $excludedTaxonomies = array('nav_menu', 'link_category', 'post_format');

    /**
     * ExportPosts constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $container = Container::getInstance();
        $this->exportMedia = $container->getExportMedia();
        $this->extractMedia = $container->getExtractMedia();
        $this->exportTaxonomies = $container->getExportTaxonomies();

        $this->posts = new \stdClass();
        if (isset($this->appSettings->content->posts)) {
            foreach ($this->appSettings->content->posts as $postType => $posts) {
                if ($posts == '*') {
                    $args = array(
                        'post_type' => $postType,
                        'posts_per_page' => -1,
                        'post_status' => array('publish', 'inherit')
                    );
                    $allPosts = get_posts($args);
                    foreach ($allPosts as $post) {
                        $this->addPost($postType, $post->post_name);
                    }
                } else {
                    foreach ($posts as $post) {
                        $this->addPost($postType, $post);
                    }
                }
            }
        }

        $this->log->addDebug('Initiated ExportPosts.');
    }

    /**
     * Export the posts
     */
    public function export()
    {
        global $wpdb;
        $count = 1;
        while ($count > 0) {
            $count = 0;
            foreach ($this->posts as $postType => &$posts) {
                foreach ($posts as &$post) {
                    if ($post->done == true) {
                        continue;
                    }
                    $postId = $wpdb->get_var($wpdb->prepare(
                        "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s",
                        $postType,
                        $post->slug
                    ));
                    $objPost = get_post($postId);
                    if (!$objPost) {
                        $this->log->addDebug("Post $postId not found");
                        continue;
                    }

                    $this->log->addDebug('Exporting post '.$post->slug, array($postId));
                    $meta = get_post_meta($objPost->ID);
                    $objPost->taxonomies = array();

                    // extract media ids
                    // 1. from attached media:
                    $media = get_attached_media('', $postId);
                    foreach ($media as $item) {
                        $this->exportMedia->addMedia($item->ID);
                    }

                    // 2. from featured image:
                    if (has_post_thumbnail($postId)) {
                        $mediaId = get_post_thumbnail_id($postId);
                        $this->exportMedia->addMedia($mediaId);
                    }

                    // 3. Is this post actually an attachment?
                    if ($objPost->post_type == 'attachment') {
                        $this->exportMedia->addMedia($objPost->ID);
                    }

                    // 4. referenced in the content
                    $ret = $this->extractMedia->getReferencedMedia($objPost);
                    if (count($ret) > 0) {
                        $this->exportMedia->addMedia($ret);
                    }

                    // 5. referenced in meta data
                    $ret = $this->extractMedia->getReferencedMedia($meta);
                    if (count($ret) > 0) {
                        $this->exportMedia->addMedia($ret);
                    }

                    // terms
                    foreach (get_taxonomies() as $taxonomy) {
                        if (in_array($taxonomy, $this->excludedTaxonomies)) {
                            continue;
                        }
                        $terms = wp_get_object_terms($postId, $taxonomy);
                        if (count($terms)) {
                            $this->log->addDebug('Adding '.count($terms)." terms from $taxonomy");
                        }
                        foreach ($terms as $objTerm) {
                            // add it to the exported terms
                            $this->exportTaxonomies->addTerm($taxonomy, $objTerm->slug);

                            if (!isset($objPost->taxonomies[$taxonomy])) {
                                $objPost->taxonomies[$taxonomy] = array();
                            }
                            $objPost->taxonomies[$taxonomy][] = $objTerm->slug;
                        }
                    }

                    $objPost->post_meta = $meta;
                    $this->helpers->fieldSearchReplace($objPost, $this->baseUrl, Bootstrap::NEUTRALURL);

                    $file = BASEPATH."/bootstrap/posts/{$objPost->post_type}/{$objPost->post_name}";
                    $this->log->addDebug("Storing $file");
                    @mkdir(dirname($file), 0777, true);
                    file_put_contents($file, serialize($objPost));
                    $post->done = true;
                    ++$count;
                }
            }
        }
    }

    /**
     * Add the post identified by post_type/slug to the internal array
     *
     * @param string $postType
     * @param string $slug
     */
    public function addPost($postType, $slug)
    {
        if (!isset($this->posts->$postType)) {
            $this->posts->$postType = array();
        }
        foreach ($this->posts->$postType as $post) {
            if ($post->slug == $slug) {
                return;
            }
        }

        $newPost = new \stdClass();
        $newPost->slug = $slug;
        $newPost->done = false;
        array_push($this->posts->$postType, $newPost);
    }
}
