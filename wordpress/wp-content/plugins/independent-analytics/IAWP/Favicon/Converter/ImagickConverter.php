<?php

namespace IAWP\Favicon\Converter;

/** @internal */
class ImagickConverter extends \IAWP\Favicon\Converter\AbstractConverter
{
    public function save()
    {
        try {
            $blob = $this->blob;
            $path = $this->path;
            $imagick = new \Imagick();
            $imagick->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 128 * 1024 * 1024);
            // 128MB
            $imagick->setResourceLimit(\Imagick::RESOURCETYPE_AREA, 4000 * 4000);
            // 16MP limit
            $imagick->setOption('svg:decode-entities', 'false');
            try {
                $imagick->readImageBlob($blob);
            } catch (\Throwable $e) {
                // Retry with ICO format if initial read fails (common for some ICO files)
                $imagick = new \Imagick();
                $imagick->setFormat('ico');
                $imagick->readImageBlob($blob);
            }
            // Handle multi-frame files (like ICO) by selecting the best frame
            if ($imagick->getNumberImages() > 1) {
                $bestIndex = 0;
                $maxArea = 0;
                $numImages = $imagick->getNumberImages();
                for ($i = 0; $i < $numImages; $i++) {
                    $imagick->setIteratorIndex($i);
                    $area = $imagick->getImageWidth() * $imagick->getImageHeight();
                    if ($area > $maxArea) {
                        $maxArea = $area;
                        $bestIndex = $i;
                    }
                }
                $imagick->setIteratorIndex($bestIndex);
                $image = $imagick->getImage();
                $imagick->clear();
                $imagick = $image;
            }
            $imagick->stripImage();
            $imagick->setImageFormat('png');
            $imagick->thumbnailImage(48, 48, \true);
            $thumbnail_size = \min($imagick->getImageWidth(), $imagick->getImageHeight());
            $imagick->cropThumbnailImage($thumbnail_size, $thumbnail_size);
            $imagick->writeImage($path);
            $imagick->clear();
            $imagick->destroy();
        } catch (\Throwable $e) {
            // ...
        }
    }
}
