<?php

namespace Wpbootstrap;

class ImportTaxonomies
{
    public $taxonomies = array();

    private $import;

    public function __construct()
    {
        $container = Container::getInstance();

        $helpers = $container->getHelpers();
        $this->import = $container->getImport();

        $dir = BASEPATH.'/bootstrap/taxonomies';
        foreach ($helpers->getFiles($dir) as $subdir) {
            $taxonomy = new \stdClass();
            $taxonomy->slug = $subdir;
            $taxonomy->terms = array();
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

    private function process()
    {
        $currentTaxonomies = get_taxonomies();

        foreach ($this->taxonomies as &$taxonomy) {
            if (isset($currentTaxonomies[$taxonomy->slug])) {
                $this->processTaxonomy($taxonomy);
            }
        }
    }

    public function assignObjects()
    {
        // Posts
        $posts = $this->import->posts->posts;
        foreach ($this->taxonomies as &$taxonomy) {
            foreach ($posts as $post) {
                if (isset($post->post->taxonomies[$taxonomy->slug])) {
                    $term = $post->post->taxonomies[$taxonomy->slug][0];
                    $ret = wp_set_object_terms($post->id, $term, $taxonomy->slug);
                }
            }
        }
    }

    private function processTaxonomy(&$taxonomy)
    {
        $currentTerms = get_terms($taxonomy->slug, array('hide_empty' => false));
        $done = false;
        while (!$done) {
            $deferred = 0;
            foreach ($taxonomy->terms as &$term) {
                if (!$term->done) {
                    $parentId = $this->parentId($term->term->parent, $taxonomy);
                    if ($parentId || $term->term->parent == 0) {
                        $existingTermId = $this->findExistingTerm($term, $currentTerms);
                        $args = array(
                            'description' => $term->term->description,
                            'parent' => $parentId,
                            'slug' => $term->term->slug,
                            'term_group' => $term->term->term_group,
                            'name' => $term->term->name,
                        );

                        if ($existingTermId > 0) {
                            $ret = wp_update_term($existingTermId, $taxonomy->slug, $args);
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

    private function parentId($foreignParentId, $taxonomy)
    {
        foreach ($taxonomy->terms as $term) {
            if ($term->term->term_id == $foreignParentId) {
                return $term->id;
            }
        }

        return 0;
    }

    private function findExistingTerm($term, $currentTerms)
    {
        foreach ($currentTerms as $currentTerm) {
            if ($currentTerm->slug == $term->term->slug) {
                return $currentTerm->term_id;
            }
        }

        return 0;
    }

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
