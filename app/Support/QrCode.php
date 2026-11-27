<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    public static function svg(string $content): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(256, 1), new SvgImageBackEnd));

        return $writer->writeString($content);
    }
}
