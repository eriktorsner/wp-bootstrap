<?php

namespace Wpbootstrap;

/**
 * Class ImportTaxonomies
 * @package Wpbootstrap
 */
class ImportTaxonomies
{
    /**
     * @var array
     */
    public $taxonomies = array();

    /**
     * @var Import
     */
    private $import;

    /**
     * ImportTaxonomies constructor.
     */
    public function __construct()
    {
        $container = Container::getInstance();

        $helpers = $container->getHelpers();
        $this->import = $container->getImport();
        $this->log = $container->getLog();
        $dir = BASEPATH.'/bootstrap/taxonomies';
        foreach ($helpers->getFiles($dir) as $subdir) {
            if (!is_dir("$dir/$subdir")) {
                continue;
            }
            $taxonomy = new \stdClass();
            $taxonomy->slug = $subdir;
            $taxonomy->terms = array();
            $this->readManifest($taxonomy, $subdir);
            foreach ($helpers->getFiles($dir.'/'.$subdir) as $file) {
                $term = new \stdClass();
                $term->done = false;
                $term->id = 0;
                $term->slug = $file;
                $term->term = unserialize(file_get_contents($dir.'/'.$subdir.'/'.$file));
                $taxonomy->terms[] = $term;
            }
            $this->taxonomies[] = $taxonomy;
        }
        $this->process();
    }

    /**
     * The main import process
     */
    private function process()
    {
        $currentTaxonomies = get_taxonomies();

        foreach ($this->taxonomies as &$taxonomy) {
            if (isset($currentTaxonomies[$taxonomy->slug])) {
                $this->processTaxonomy($taxonomy);
            }
        }
    }

    /**
     * Searches through a imported posts to identify all taxonomy terms assignments
     * for each post and assigns the post to the terms in the current WP install
     */
    public function assignObjects()
    {
        // Posts
        $this->log->addDebug('Assigning objects to taxonomies');
        $posts = $this->import->posts->posts;
        foreach ($this->taxonomies as &$taxonomy) {
            foreach ($posts as $post) {
                if (isset($post->post->taxonomies[$taxonomy->slug])) {
                    $newTerms = array();
                    foreach ($post->post->taxonomies[$taxonomy->slug] as $orgSlug) {
                        $termSlug = $this->findNewTerm($taxonomy, $orgSlug);
                        $newTerms[] = $termSlug;
                    }
                    $this->log->addDebug("adding terms to object {$post->id}", $newTerms);
                    wp_set_object_terms($post->id, $newTerms, $taxonomy->slug, false);
                }
            }
        }
    }

    /**
     * Process individual taxonomy
     *
     * @param \stdClass $taxonomy
     */
    private function processTaxonomy(&$taxonomy)
    {
        $currentTerms = get_terms($taxonomy->slug, array('hide_empty' => false));
        $done = false;
        while (!$done) {
            $deferred = 0;
            foreach ($taxonomy->terms as &$term) {
                if (!$term->done) {
                    $parentId = $this->parentId($term->term->parent, $taxonomy);
                    $this->log->addDebug("Importing term {$term->term->name}/{$term->term->slug}");
                    if ($parentId || $term->term->parent == 0) {
                        $args = array(
                            'description' => $term->term->description,
                            'parent' => $parentId,
                            'slug' => $term->term->slug,
                            'term_group' => $term->term->term_group,
                            'name' => $term->term->name,
                        );
                        switch ($taxonomy->type) {
                            case 'postid':
                                $this->adjustTypePostId($term->term, $args);
                                break;
                        }
                        $existingTermId = $this->findExistingTerm($term, $currentTerms);
                        if ($existingTermId > 0) {
                            wp_update_term($existingTermId, $taxonomy->slug, $args);
                            $term->id = $existingTermId;
                        } else {
                            $id = wp_insert_term($term->term->name, $taxonomy->slug, $args);
                            $term->id = $id['term_id'];
                        }
                        $term->done = true;
                    } else {
                        ++$deferred;
                    }
                }
                if ($deferred == 0) {
                    $done = true;
                }
            }
        }
    }

    /**
     * Handles name/slug adjustments for terms in a "postid" taxonomy
     *
     * @param \stdClass $term
     * @param array $args
     */
    private function adjustTypePostId(&$term, &$args)
    {
        // slug refers to a postid.
        $importPosts = $this->import->posts;
        $newId = $importPosts->findTargetPostId($term->slug);

        if ($newId) {
            $args['slug'] = strval($newId);
            $args['name'] = strval($newId);
            $term->name = strval($newId);
            $term->slug = strval($newId);
        }
    }

    /**
     * Finds a term in the existing WP install based on what that terms
     * was named in the old.
     *
     * @param \stdClass $taxonomy
     * @param string $orgSlug
     * @return string
     */
    private function findNewTerm($taxonomy, $orgSlug)
    {
        foreach ($taxonomy->terms as $term) {
            if ($term->slug == $orgSlug) {
                return $term->term->slug;
            }
        }

        return $orgSlug;
    }

    /**
     * Finds a the current parent id for a term based on its old value
     *
     * @param int $foreignParentId
     * @param \stdClass $taxonomy
     * @return int
     */
    private function parentId($foreignParentId, $taxonomy)
    {
        foreach ($taxonomy->terms as $term) {
            if ($term->term->term_id == $foreignParentId) {
                return $term->id;
            }
        }

        return 0;
    }

    /**
     * Searches all current trems in a traxonomy and returns the id
     * for the searched term slug
     *
     * @param string $term
     * @param array $currentTerms
     * @return int
     */
    private function findExistingTerm($term, $currentTerms)
    {
        foreach ($currentTerms as $currentTerm) {
            if ($currentTerm->slug == $term->term->slug) {
                return $currentTerm->term_id;
            }
        }

        return 0;
    }

    /**
     * Parse the manifest file generated for the taxonomy
     *
     * @param \stdClass $taxonomy
     * @param string $taxonomyName
     */
    private function readManifest(&$taxonomy, $taxonomyName)
    {
        $taxonomy->type = 'standard';
        $taxonomy->termDescriptor = 'indirect';
        $file = BASEPATH."/bootstrap/taxonomies/{$taxonomyName}_manifest.json";
        if (file_exists($file)) {
            $manifest = json_decode(file_get_contents($file));
            if (isset($manifest->type)) {
                $taxonomy->type = $manifest->type;
            }
            if (isset($manifest->termDescriptor)) {
                $taxonomy->termDescriptor = $manifest->termDescriptor;
            }
        }
    }

    /**
     * Searches all imported taxonomies for a specific term, returns the term id if fount
     *
     * @param $target
     * @return int
     */
    public function findTargetTermId($target)
    {
        foreach ($this->taxonomies as $taxonomy) {
            foreach ($taxonomy->terms as $term) {
                if ($term->term->term_id == $target) {
                    return $term->id;
                }
            }
        }

        return 0;
    }
}
