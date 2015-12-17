<?php

namespace Wpbootstrap;

class Helpers
{
    public function prettyPrint($json)
    {
        $result = '';
        $level = 0;
        $in_quotes = false;
        $in_escape = false;
        $ends_line_level = null;
        $json_length = strlen($json);

        for ($i = 0; $i < $json_length; ++$i) {
            $char = $json[$i];
            $new_line_level = null;
            $post = '';
            if ($ends_line_level !== null) {
                $new_line_level = $ends_line_level;
                $ends_line_level = null;
            }
            if ($in_escape) {
                $in_escape = false;
            } elseif ($char === '"') {
                $in_quotes = !$in_quotes;
            } elseif (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = null;
                        $new_line_level = $level;
                        break;

                    case '{':
                    case '[':
                        $level++;
                        // intentonal
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = ' ';
                        break;

                    case ' ':
                    case "\t":
                    case "\n":
                    case "\r":
                        $char = '';
                        $ends_line_level = $new_line_level;
                        $new_line_level = null;
                        break;
                }
            } elseif ($char === '\\') {
                $in_escape = true;
            }
            if ($new_line_level !== null) {
                $result .= "\n".str_repeat("\t", $new_line_level);
            }
            $result .= $char.$post;
        }

        // arrays with zero or one item goes on the same line
        $result = preg_replace('/\[\s+\]/', '[]', $result);
        $result = preg_replace('/\[(\s+)(".*")(\s+)\]/', '[$2]', $result);

        return $result;
    }

    public function getFiles($folder)
    {
        $ret = array();
        if (!file_exists($folder)) {
            return $ret;
        }
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file != '..' && $file != '.') {
                $ret[] = $file;
            }
        }

        return $ret;
    }

    public function isUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        } else {
            return true;
        }
    }

    public function recursiveRemoveDirectory($directory)
    {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                $this->recursiveRemoveDirectory($file);
            } else {
                unlink($file);
            }
        }
        if (file_exists($directory)) {
            rmdir($directory);
        }
    }

    public function uniqueObjectArray($array, $key)
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val->$key, $key_array)) {
                $key_array[$i] = $val->$key;
                $temp_array[$i] = $val;
            }
            ++$i;
        }

        return $temp_array;
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

            $obj = str_replace($search, $replace, $obj);

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
