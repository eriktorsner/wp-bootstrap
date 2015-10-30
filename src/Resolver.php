<?php
namespace Wpbootstrap;

class Resolver
{
    private $import;
    private static $self = false;

    public static function getInstance()
    {
        if (!self::$self) {
            self::$self = new Resolver();
        }

        return self::$self;
    }

    public function __construct()
    {
        $this->import = Import::getInstance();
    }

    public function resolveReferences($references)
    {
        wp_cache_delete('alloptions', 'options');

        foreach ($references as $key => $value) {
            if (is_integer($key)) {
                $currentValue = get_option($value, 0);
                $postId = $this->import->findTargetPostId($currentValue);
                if ($postId != 0) {
                    update_option($value, $postId);
                }
            } elseif (is_string($key) && is_string($value)) {
                $path = $value;
                $currentStruct = get_option($key, 0);
                try {
                    $currentValue = $this->getValue($currentStruct, $path);
                    $postId = $this->import->findTargetPostId($currentValue);
                    if ($postId != 0) {
                        $this->setValue($currentStruct, $path, $postId);
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
                    $postId = $this->import->findTargetPostId($currentValue);
                    if ($postId != 0) {
                        $this->setValue($currentStruct, $path, $postId);
                    }
                }
                update_option($key, $currentStruct);
            }
        }
    }

    private function getValue($obj, $path)
    {
        return eval("return \$obj".$path.";");
    }

    private function setValue(&$obj, $path, $value)
    {
        $evalStr = "\$obj".$path."=".$value."; return \$obj;";
        $obj = eval($evalStr);
    }

    public function fieldSearchReplace(&$fld_value, $search, $replace)
    {
        $value = $fld_value;
        $ret = false;
        if (is_numeric($fld_value)) {
            return $ret;
        }

        $b64 = $this->isBase64($fld_value);
        if ($b64) {
            $value = base64_decode($value);
        }
        $ser = $this->isSerialized($value);
        if ($ser) {
            $value = unserialize($value);
        }

        $this->recurseSearchReplace($value, $search, $replace);

        if ($ser) {
            $value = serialize($value);
        }
        if ($b64) {
            $value = base64_encode($value);
        }

        if ($fld_value != $value) {
            $fld_value = $value;
            $ret = true;
        }

        return $ret;
    }

    private function recurseSearchReplace(&$obj, $search, $replace)
    {
        if (is_object($obj) || is_array($obj)) {
            foreach ($obj as &$member) {
                $this->recurseSearchReplace($member, $search, $replace);
            }
        } else {
            if (is_numeric($obj)) {
                return;
            }
            if (is_bool($obj)) {
                return;
            }
            if (is_null($obj)) {
                return;
            }
            $obj =  str_replace($search, $replace, $obj);
        }
    }

    private function isSerialized($data)
    {
        $test = @unserialize(($data));

        return ($test !== false || $test === 'b:0;') ? true : false;
    }

    private function isBase64($data)
    {
        if (@base64_encode(base64_decode($data)) === $data) {
            return true;
        } else {
            return false;
        }
    }
}
