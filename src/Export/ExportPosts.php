<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Dumper;

/**
 * Class ExportPosts
 * @package Wpbootstrap\Export
 */
class ExportPosts
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
        $this->posts = new \stdClass();
    }

    /**
     *  Export posts
     */
    public function export()
    {
        $app = Bootstrap::getApplication();
        $settings = $app['settings'];
        if (!isset($settings['content']['posts'])) {
            return;
        }

        $cli = $app['cli'];

        foreach ($settings['content']['posts'] as $postType => $posts) {
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

        $cli->debug('Initiated ExportPosts');
        $this->doExport();
    }

    /**
     * Export the posts
     */
    public function doExport()
    {
        global $wpdb;
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];
        $extractMedia = $app['extractmedia'];
        $exportMedia = $app['exportmedia'];
        $exportTaxonomies = $app['exporttaxonomies'];
        $helpers = $app['helpers'];
        $baseUrl = get_option('siteurl');
        $dumper = new Dumper();

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
                    $arrPost = get_post($postId, ARRAY_A);
                    if (!$arrPost) {
                        $cli->debug("Post $postId not found");
                        continue;
                    }

                    $cli->debug("Exporting post {$post->slug} ($postId)");
                    $meta = get_post_meta($arrPost['ID']);
                    $arrPost['taxonomies'] = array();

                    // extract media ids
                    // 1. from attached media:
                    $media = get_attached_media('', $postId);
                    foreach ($media as $item) {
                        $exportMedia->addMedia($item->ID);
                    }

                    // 2. from featured image:
                    if (has_post_thumbnail($postId)) {
                        $mediaId = get_post_thumbnail_id($postId);
                        $exportMedia->addMedia($mediaId);
                    }

                    // 3. Is this post actually an attachment?
                    if ($arrPost['post_type'] == 'attachment') {
                        $exportMedia->addMedia($arrPost['ID']);
                    }

                    // 4. referenced in the content
                    $ret = $extractMedia->getReferencedMedia($arrPost);
                    if (count($ret) > 0) {
                        $exportMedia->addMedia($ret);
                    }

                    // 5. referenced in meta data
                    $ret = $extractMedia->getReferencedMedia($meta);
                    if (count($ret) > 0) {
                        $exportMedia->addMedia($ret);
                    }

                    // terms
                    foreach (get_taxonomies() as $taxonomy) {
                        if (in_array($taxonomy, $this->excludedTaxonomies)) {
                            continue;
                        }
                        $terms = wp_get_object_terms($postId, $taxonomy);
                        if (count($terms)) {
                            $cli->debug('Adding '.count($terms)." terms from $taxonomy");
                        }
                        foreach ($terms as $objTerm) {
                            // add it to the exported terms
                            $exportTaxonomies->addTerm($taxonomy, $objTerm->slug);

                            if (!isset($arrPost['taxonomies'][$taxonomy])) {
                                $arrPost['taxonomies'][$taxonomy] = array();
                            }
                            $arrPost['taxonomies'][$taxonomy][] = $objTerm->slug;
                        }
                    }

                    $arrPost['post_meta'] = $meta;
                    $helpers->fieldSearchReplace($arrPost, $baseUrl, Bootstrap::NEUTRALURL);

                    $file = BASEPATH."/bootstrap/posts/{$arrPost['post_type']}/{$arrPost['post_name']}";
                    $cli->debug("Storing $file");
                    @mkdir(dirname($file), 0777, true);
                    file_put_contents($file, $dumper->dump($arrPost, 4));
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
