<?php
namespace Wpbootstrap;

class Resolver
{
    public static function resolveReferences()
    {
        global $bootstrapSettings;

        foreach ($bootstrapSettings->references as $reference) {
            if (!isset($reference->path) && !isset($reference->paths)) {
                $currentValue = get_option($reference->option_name, 0);
                $postId = findTargetPostId($currentValue);
                if ($postId != 0) {
                    update_option($reference->option_name, $postId);
                }
            } elseif (isset($reference->path) && !isset($reference->paths)) {
                $path = $reference->path;
                $currentStruct = get_option($reference->option_name, 0);
                $currentValue = self::getValue($currentStruct, $path);
                $postId = findTargetPostId($currentValue);
                if ($postId != 0) {
                    self::setValue($currentStruct, $path, $postId);
                    update_option($reference->option_name, $currentStruct);
                }
            } elseif (isset($reference->paths)) {
                $currentStruct = get_option($reference->option_name, 0);
                $paths = $reference->paths;
                foreach ($paths as $path) {
                    $currentValue = self::getValue($currentStruct, $path);
                    $postId = findTargetPostId($currentValue);
                    if ($postId != 0) {
                        self::setValue($currentStruct, $path, $postId);
                    }
                }
                update_option($reference->option_name, $currentStruct);
            }
        }
    }

    private static function getValue($obj, $path)
    {
        return eval("return \$obj".$path.";");
    }

    private static function setValue(&$obj, $path, $value)
    {
        $evalStr = "\$obj".$path."=".$value."; return \$obj;";
        $obj = eval($evalStr);
    }

    public static function fieldSearchReplace(&$fld_value, $search, $replace)
    {
        $value = $fld_value;
        $ret = false;
        if (is_numeric($fld_value)) {
            return $ret;
        }

        $b64 = self::isBase64($fld_value);
        if ($b64) {
            $value = base64_decode($value);
        }
        $ser = self::isSerialized($value);
        if ($ser) {
            $value = unserialize($value);
        }

        self::recurseSearchReplace($value, $search, $replace);

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

    private static function recurseSearchReplace(&$obj, $search, $replace)
    {
        if (is_object($obj) || is_array($obj)) {
            foreach ($obj as &$member) {
                self::recurseSearchReplace($member, $search, $replace);
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

    private static function isSerialized($data)
    {
        $test = @unserialize(($data));

        return ($test !== false || $test === 'b:0;') ? true : false;
    }

    private static function isBase64($data)
    {
        if (@base64_encode(base64_decode($data)) === $data) {
            return true;
        } else {
            return false;
        }
    }
}
