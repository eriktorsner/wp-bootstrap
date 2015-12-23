<?php

namespace Wpbootstrap;

class ExportTaxonomies extends ExportBase
{
    private $taxonomies;

    public function __construct()
    {
        parent::__construct();

        $this->taxonomies = new \stdClass();
        if (isset($this->appSettings->content->taxonomies)) {
            foreach ($this->appSettings->content->taxonomies as $taxonomyName => $terms) {
                $this->taxonomies->$taxonomyName = new \stdClass();
                $this->taxonomies->$taxonomyName->termsDescriptor = $terms;
                $this->taxonomies->$taxonomyName->type = 'standard';
                $this->taxonomies->$taxonomyName->terms = array();
                if (is_object($terms)) {
                    $this->taxonomies->$taxonomyName->termsDescriptor = $terms->terms;
                    $this->taxonomies->$taxonomyName->type = $terms->type;
                }
                if ($this->taxonomies->$taxonomyName->termsDescriptor == '*') {
                    $allTerms = get_terms($taxonomyName, array('hide_empty' => false));
                    foreach ($allTerms as $term) {
                        $this->addTerm($taxonomyName, $term->slug);
                    }
                } else {
                    foreach ($terms as $term) {
                        $this->addTerm($taxonomyName, $term);
                    }
                }
            }
        }
    }

    public function export()
    {
        $count = 1;
        while ($count > 0) {
            $count = 0;

            foreach ($this->taxonomies as $taxonomyName => $taxonomy) {
                foreach ($taxonomy->terms as &$term) {
                    if ($term->done == true) {
                        continue;
                    }

                    $objTerm = get_term_by('slug', $term->slug, $taxonomyName);
                    $file = BASEPATH."/bootstrap/taxonomies/$taxonomyName/{$term->slug}";
                    @mkdir(dirname($file), 0777, true);
                    file_put_contents($file, serialize($objTerm));

                    if ($objTerm->parent) {
                        $parentTerm = get_term_by('id', $objTerm->parent, $taxonomyName);
                        $this->addTerm($taxonomyName, $parentTerm->slug);
                    }
                    $term->done = true;
                    ++$count;
                }
            }
        }

        foreach ($this->taxonomies as $taxonomyName => $taxonomy) {
            $manifestFile = BASEPATH."/bootstrap/taxonomies/{$taxonomyName}_manifest.json";
            $manifest = new \stdClass();
            $manifest->name = $taxonomyName;
            $manifest->type = $taxonomy->type;
            $manifest->termsDescriptor = $taxonomy->termsDescriptor;
            $this->log->addDebug("Creating $taxonomyName manifest ".$manifestFile);
            file_put_contents($manifestFile, $this->helpers->prettyPrint(json_encode($manifest)));
        }
    }

    public function addTerm($taxonomyName, $slug)
    {
        $this->log->addDebug("Adding term $slug to Taxonomy $taxonomyName");
        if (!isset($this->taxonomies->$taxonomyName)) {
            $this->taxonomies->$taxonomyName = new \stdClass();
            $this->taxonomies->$taxonomyName->type = 'standard';
            $this->taxonomies->$taxonomyName->termsDescriptor = 'indirect';
            $this->taxonomies->$taxonomyName->terms = array();
        }
        foreach ($this->taxonomies->$taxonomyName->terms as $term) {
            if ($term->slug == $slug) {
                return;
            }
        }
        $newTerm = new \stdClass();
        $newTerm->slug = $slug;
        $newTerm->done = false;
        array_push($this->taxonomies->$taxonomyName->terms, $newTerm);
    }
}
