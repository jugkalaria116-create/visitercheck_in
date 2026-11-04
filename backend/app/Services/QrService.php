<?php
declare(strict_types=1);

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

final class QrService
{
    /** @return array{0:string,1:string} [binary, mime] — high contrast, high error correction, 40px quiet zone. */
    public static function render(string $url, string $format): array
    {
        $result = Builder::create()
            ->writer($format === 'png' ? new PngWriter() : new SvgWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size(800)
            ->margin(40)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();
        return [$result->getString(), $result->getMimeType()];
    }
}
