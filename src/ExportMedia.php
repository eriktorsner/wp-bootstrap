<?php

namespace Wpbootstrap;

class ExportMedia extends ExportBase
{
    private $mediaIds = array();

    public function __construct()
    {
        parent::__construct();
    }

    public function export()
    {
        $this->mediaIds = array_unique($this->mediaIds);
        $uploadDir = wp_upload_dir();

        foreach ($this->mediaIds as $itemId) {
            $item = get_post($itemId);
            if ($item) {
                $meta = get_post_meta($itemId);
                $item->post_meta = $meta;

                $file = $meta['_wp_attached_file'][0];

                $attachmentMeta = wp_get_attachment_metadata($itemId, true);
                $item->attachment_meta = $attachmentMeta;

                $dir = BASEPATH.'/bootstrap/media/'.$item->post_name;
                @mkdir($dir, 0777, true);
                file_put_contents($dir.'/meta', serialize($item));
                $src = $uploadDir['basedir'].'/'.$file;
                $trg = $dir.'/'.basename($file);
                if (file_exists($src)) {
                    copy($src, $trg);
                }
            }
        }
    }

    public function addMedia($mediaId)
    {
        if (is_array($mediaId)) {
            $this->log->addDebug('Including thumbnail media', $mediaId);
            $this->mediaIds = array_merge($this->mediaIds, $mediaId);
        } else {
            $this->log->addDebug('Including thumbnail media', array($mediaId));
            $this->mediaIds[] = $mediaId;
        }
    }
}
