<?php

namespace Wpbootstrap;

class ExtractMedia extends ExportBase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getReferencedMedia($obj)
    {
        $links = $this->findMediaFromObj($obj);

        $ret = array();
        foreach ($links as $link) {
            if (!$this->isImageUrl($link)) {
                continue;
            }

            $id = $this->getImageId($link);
            if (!$id) {
                $id = $this->getImageId($this->removeLastSizeIndicator($link));
            }

            if ($id > 0) {
                $ret[] = $id;
            }
        }

        return $ret;
    }

    private function findMediaFromObj($obj)
    {
        $ret = array();

        if (is_object($obj) || is_array($obj)) {
            foreach ($obj as &$member) {
                $arr = $this->findMediaFromObj($member);
                $ret = array_merge($ret, $arr);
            }
        } else {
            if (is_string($obj)) {
                $base = $this->uploadDir['baseurl'];
                $pattern = '~('.preg_quote($base, '~').'[^.]+.\w\w\w\w?)~i';
                preg_match_all($pattern, $obj, $matches);
                if (isset($matches[0]) && is_array($matches[0])) {
                    $ret = array_merge($ret, $matches[0]);
                }
            }
        }

        return array_unique($ret);
    }

    public function removeLastSizeIndicator($link)
    {
        $ret = $link;
        $pattern = '~-\d{1,}x\d{1,}~';
        $hits = preg_match_all($pattern, $link, $matches, PREG_OFFSET_CAPTURE);
        if ($hits > 0) {
            $last = end($matches[0]);
            $ret = substr($link, 0, $last[1]).substr($link, $last[1] + strlen($last[0]));
        }

        return $ret;
    }

    public function getImageId($link)
    {
        global $wpdb;
        $attachment = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE guid='%s';",
                $link
            )
        );

        return $attachment[0];
    }

    public function isImageUrl($link)
    {
        $ret = false;
        $imgExtensions = array('gif', 'jpg', 'jpeg', 'png', 'tiff', 'tif');
        $urlParts = parse_url($link);
        $urlExt = strtolower(pathinfo($urlParts['path'], PATHINFO_EXTENSION));
        if (in_array($urlExt, $imgExtensions)) {
            $ret = true;
        }

        return $ret;
    }
}
