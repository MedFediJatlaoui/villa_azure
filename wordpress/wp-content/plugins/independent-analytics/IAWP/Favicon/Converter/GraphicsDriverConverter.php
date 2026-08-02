<?php

namespace IAWP\Favicon\Converter;

/** @internal */
class GraphicsDriverConverter extends \IAWP\Favicon\Converter\AbstractConverter
{
    public const MAX_DIMENSION = 3000;
    public function save()
    {
        try {
            $blob = $this->blob;
            $path = $this->path;
            $info = \getimagesizefromstring($blob);
            if ($info === \false) {
                return \false;
            }
            list($width, $height, $type) = $info;
            $allowedTypes = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_GIF, \IMAGETYPE_WEBP];
            if (!\in_array($type, $allowedTypes)) {
                return \false;
            }
            // Image is too large to safely process
            if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
                return \false;
            }
            $image = \imagecreatefromstring($blob);
            if ($image === \false) {
                return \false;
            }
            $max_size = 48;
            $new_width = $width;
            $new_height = $height;
            if ($width > $max_size || $height > $max_size) {
                if ($width > $height) {
                    $new_width = $max_size;
                    $new_height = (int) \round($height * ($max_size / $width));
                } else {
                    $new_height = $max_size;
                    $new_width = (int) \round($width * ($max_size / $height));
                }
            }
            $thumbnail_size = \min($new_width, $new_height);
            // First resize the image
            $resized = \imagecreatetruecolor($new_width, $new_height);
            \imagealphablending($resized, \false);
            \imagesavealpha($resized, \true);
            $transparent = \imagecolorallocatealpha($resized, 0, 0, 0, 127);
            \imagefill($resized, 0, 0, $transparent);
            \imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            // Then crop to square
            $final = \imagecreatetruecolor($thumbnail_size, $thumbnail_size);
            \imagealphablending($final, \false);
            \imagesavealpha($final, \true);
            $transparent = \imagecolorallocatealpha($final, 0, 0, 0, 127);
            \imagefill($final, 0, 0, $transparent);
            $src_x = (int) \round(($new_width - $thumbnail_size) / 2);
            $src_y = (int) \round(($new_height - $thumbnail_size) / 2);
            \imagecopy($final, $resized, 0, 0, $src_x, $src_y, $thumbnail_size, $thumbnail_size);
            // Save as PNG
            $result = \imagepng($final, $path);
            // Clean up
            \imagedestroy($image);
            \imagedestroy($resized);
            \imagedestroy($final);
        } catch (\Throwable $e) {
        }
    }
}
