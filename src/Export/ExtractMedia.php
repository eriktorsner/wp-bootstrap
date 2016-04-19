<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;

/**
 * Class ExtractMedia
 * @package Wpbootstrap\Export
 */
class ExtractMedia
{
    /**
     * ExtractMedia constructor.
     */
    public function __construct()
    {
        $this->uploadDir = wp_upload_dir();
    }

    /**
     * Extracts id's of all attachments that this post/term/widget is referring to via
     * an url in the content
     *
     * @param \stdClass $obj
     * @return array
     */
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

    /**
     * Identifies links to images/attachments on WordPress installation
     *
     * @param \stdClass $obj
     * @return array
     */
    private function findMediaFromObj($obj)
    {
        $ret = array();
        $app = Bootstrap::getApplication();
        $helpers = $app['helpers'];

        $b64 = $helpers->isBase64($obj);
        if ($b64) {
            $obj = base64_decode($obj);
        }
        $ser = $helpers->isSerialized($obj);
        if ($ser) {
            $obj = unserialize($obj);
        }

        if (is_object($obj) || is_array($obj)) {
            foreach ($obj as $key => &$member) {
                $arr = $this->findMediaFromObj($member);
                $ret = array_merge($ret, $arr);
            }
        } else {
            if (is_string($obj)) {
                $base = rtrim($this->uploadDir['baseurl'], '/') . '/';
                $pattern = '~('.preg_quote($base, '~').'[^.]+.\w\w\w\w?)~i';
                preg_match_all($pattern, $obj, $matches);
                if (isset($matches[0]) && is_array($matches[0])) {
                    $ret = array_merge($ret, $matches[0]);
                }
            }
        }

        return array_unique($ret);
    }

    /**
     * Removes the last (and only last) media size indicator at the end of an URL.
     *
     * @param string $link
     * @return string
     */
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

    /**
     * Returns the attachment/media id of an image by looking up the GUID
     *
     * @param string $link
     * @return mixed
     */
    public function getImageId($link)
    {
        global $wpdb;
        $attachment = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE guid='%s';",
                $link
            )
        );

        if ($attachment[0]) {
            return $attachment[0];
        }

        $attachment = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value='%s';",
            str_replace($this->uploadDir['baseurl'] . '/', '', $link)
            )
        );

        return $attachment[0];

    }

    /**
     * Determines if a link is in fact a link to an image
     *
     * @param string $link
     * @return bool
     */
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
