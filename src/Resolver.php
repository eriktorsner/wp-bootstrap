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

    public function resolveOptionReferences($references, $type)
    {
        wp_cache_delete('alloptions', 'options');

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

        $this->recurseSearchReplace($value, $search, $replace);

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

            $b64 = $this->isBase64($obj);
            if ($b64) {
                $obj = base64_decode($obj);
            }
            $ser = $this->isSerialized($obj);
            if ($ser) {
                $obj = unserialize($obj);
            }

            $obj =  str_replace($search, $replace, $obj);

            if ($ser) {
                $obj = serialize($obj);
            }
            if ($b64) {
                $obj = base64_encode($obj);
            }
        }
    }

    private function isSerialized($data)
    {
        if (!is_string($data)) {
            return false;
        }
        $test = @unserialize(($data));

        return ($test !== false || $test === 'b:0;') ? true : false;
    }

    private function isBase64($data)
    {
        if (!is_string($data)) {
            return false;
        }

        $decoded = base64_decode($data, true);
        if (@base64_encode($decoded) === $data) {
            $printable = ctype_print($decoded);
            if ($printable) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
