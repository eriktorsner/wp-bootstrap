<?php

namespace Wpbootstrap;

/**
 *
 */
class Resolver
{
    /**
     * @var Import
     */
    private $import;

    /**
     * Resolver constructor.
     */
    public function __construct()
    {
        $app = Bootstrap::getApplication();
        $this->import = $app['import'];
    }

    /**
     * Iterates an array with references to options in the wp_options table.
     * Each value is checked to see if it points to an imported post/term and if so,
     * updates the option value to the new post or term id
     *
     * @param array $references
     * @param string $type
     */
    public function resolveOptionReferences($references, $type)
    {
        wp_cache_flush();

        foreach ($references as $key => $value) {
            if (is_integer($key)) {
                $currentValue = get_option($value, 0);
                $newId = $this->import->findTargetObjectId($currentValue, $type);
                if ($newId != 0) {
                    update_option($value, $newId);
                }
            } elseif (is_string($key) && is_string($value)) {
                $path = $value;
                $currentStruct = get_option($key, 0);
                try {
                    $currentValue = $this->getValue($currentStruct, $path);
                    $newId = $this->import->findTargetObjectId($currentValue, $type);
                    if ($newId != 0) {
                        $this->setValue($currentStruct, $path, $newId);
                        update_option($key, $currentStruct);
                    }
                } catch (\Exception $e) {
                    continue;
                }
            } elseif (is_string($key) && is_array($value)) {
                $currentStruct = get_option($key, 0);
                $paths = $value;
                foreach ($paths as $path) {
                    $currentValue = $this->getValue($currentStruct, $path);
                    $newId = $this->import->findTargetObjectId($currentValue, $type);
                    if ($newId != 0) {
                        $this->setValue($currentStruct, $path, $newId);
                    }
                }
                update_option($key, $currentStruct);
            }
        }
    }

    /**
     * Iterates an array with references to post_meta fields.
     * Each value is checked to see if it points to an imported post/term and if so,
     * updates the post_meta value to the new post or term id
     *
     * @param array $references
     * @param string $type
     */
    public function resolvePostMetaReferences($references, $type)
    {
        foreach ($references as $key => $value) {
            if (is_integer($key)) {
                foreach ($this->import->posts as $post) {
                    if (isset($post->post->post_meta[$value])) {
                        foreach ($post->post->post_meta[$value] as $currentValue) {
                            $newValue = $this->calcNewValue($currentValue, $type);
                            if ($newValue !== false) {
                                update_post_meta($post->id, $value, $newValue, $currentValue);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns the new value for a replaced field based on it's current value.
     * If the value is a string, a regex search is made to see the old value might
     * refer to a post/term
     *
     * @param $currentValue
     * @param $type
     * @return bool|int|mixed
     */
    private function calcNewValue($currentValue, $type)
    {
        if (is_integer($currentValue)) {
            $newId = $this->import->findTargetObjectId($currentValue, $type);
            if ($newId != 0) {
                return $newId;
            }
        }
        $count = preg_match_all('!\d+!', $currentValue, $matches);
        if ($count == 1) {
            $oldId = $matches[0][0];
            $newId = $this->import->findTargetObjectId($oldId, $type);
            if ($newId != 0) {
                $newValue = str_replace($oldId, $newId, $currentValue);

                return $newValue;
            }
        }

        return false;
    }

    /**
     * Use PHP eval to evaluate an expression that refers to a value
     * inside an object or array and return that value
     *
     * @param mixed $obj
     * @param string $path
     * @return mixed
     */
    private function getValue($obj, $path)
    {
        return eval('return $obj'.$path.';');
    }

    /**
     * Use PHP eval to update a member in a object or array with a new value
     *
     * @param mixed $obj
     * @param string $path
     * @param $value
     */
    private function setValue(&$obj, $path, $value)
    {
        $evalStr = '$obj'.$path.'='.$value.'; return $obj;';
        $obj = eval($evalStr);
    }
}
