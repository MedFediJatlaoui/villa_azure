<?php

namespace IAWP\Favicon\Converter;

/** @internal */
abstract class AbstractConverter
{
    protected string $blob;
    protected string $path;
    public function __construct(string $blob, string $path)
    {
        $this->blob = $blob;
        $this->path = $path;
    }
    public abstract function save();
    public static function make(string $blob, string $path) : ?\IAWP\Favicon\Converter\AbstractConverter
    {
        if (\extension_loaded('imagick')) {
            return new \IAWP\Favicon\Converter\ImagickConverter($blob, $path);
        } elseif (\extension_loaded('gd')) {
            return new \IAWP\Favicon\Converter\GraphicsDriverConverter($blob, $path);
        } else {
            return null;
        }
    }
}
