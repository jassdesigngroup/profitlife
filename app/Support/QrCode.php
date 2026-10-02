<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Código QR en SVG (se genera en el servidor, sin servicios externos).
 */
class QrCode
{
    public static function svg(string $data, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        // Quita la declaración XML para poder insertarlo dentro del HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString($data)));
    }
}
