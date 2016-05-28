<?php

namespace Wpbootstrap\Export;

use \Wpbootstrap\Bootstrap;
use Symfony\Component\Yaml\Dumper;

/**
 * Class ExportMedia
 * @package Wpbootstrap\Export
 */
class ExportMedia
{
    /**
     * @var array mediaIds
     */
    private $mediaIds = array();

    /**
     * Exports media
     */
    public function export()
    {
        $this->mediaIds = array_unique($this->mediaIds);
        $uploadDir = wp_upload_dir();
        $dumper = new Dumper();

        foreach ($this->mediaIds as $itemId) {
            $item = get_post($itemId, ARRAY_A);
            if ($item) {
                $meta = get_post_meta($itemId);
                $item['post_meta'] = $meta;

                $file = $meta['_wp_attached_file'][0];

                $attachmentMeta = wp_get_attachment_metadata($itemId, true);
                $item['attachment_meta'] = $attachmentMeta;

                $dir = WPBOOT_BASEPATH.'/bootstrap/media/'.$item['post_name'];
                @mkdir($dir, 0777, true);
                file_put_contents($dir.'/meta', $dumper->dump($item, 4));
                $src = $uploadDir['basedir'].'/'.$file;
                $trg = $dir.'/'.basename($file);
                if (file_exists($src)) {
                    copy($src, $trg);
                }
            }
        }
    }

    /**
     * Add mediaId to the internal array
     * @param int|array $mediaId
     */
    public function addMedia($mediaId)
    {
        $app = Bootstrap::getApplication();
        $cli = $app['cli'];

        if (is_array($mediaId)) {
            $cli->debug('Including thumbnail media', $mediaId);
            $this->mediaIds = array_merge($this->mediaIds, $mediaId);
        } else {
            $cli->debug('Including thumbnail media', array($mediaId));
            $this->mediaIds[] = $mediaId;
        }
    }
}
