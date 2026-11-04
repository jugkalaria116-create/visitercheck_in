<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Validates an uploaded image by content (not by extension), decodes it with GD and
 * re-encodes it. Re-encoding strips EXIF/metadata and any embedded payloads.
 */
final class ImageService
{
    private const TYPES = [
        IMAGETYPE_JPEG => ['image/jpeg', 'jpg'],
        IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'],
    ];
    private const MIN_SIDE = 100;
    private const MAX_SIDE = 12000;
    private const MAX_PIXELS = 25_000_000;

    /** @return array{path:string,mime:string,width:int,height:int,size:int} */
    public static function store(array $file, string $subdir, int $maxBytes, int $maxDim = 1920, string $field = 'photo'): array
    {
        $fail = static fn(string $m) => new HttpException(422, $m, [$field => $m], 'invalid_image');

        if (!isset($file['error'], $file['tmp_name']) || is_array($file['error'])) {
            throw $fail('Invalid upload.');
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw $fail('Please add a photo.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw $fail('The photo is too large.');
            default:
                throw $fail('The upload failed. Please try again.');
        }
        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp) && !(defined('VMS_ALLOW_NON_UPLOADED') && VMS_ALLOW_NON_UPLOADED)) {
            throw $fail('Invalid upload.');
        }
        $size = filesize($tmp);
        if ($size === false || $size <= 0) {
            throw $fail('The photo is empty.');
        }
        if ($size > $maxBytes) {
            throw $fail('The photo is too large. Maximum size is ' . round($maxBytes / 1048576, 1) . ' MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = (string) $finfo->file($tmp);
        $info = @getimagesize($tmp);
        if ($info === false || !isset(self::TYPES[$info[2]]) || self::TYPES[$info[2]][0] !== $detected) {
            throw $fail('Only JPEG, PNG or WebP images are allowed.');
        }
        [$w, $h] = [(int) $info[0], (int) $info[1]];
        if ($w < self::MIN_SIDE || $h < self::MIN_SIDE) {
            throw $fail('The photo is too small (minimum ' . self::MIN_SIDE . '×' . self::MIN_SIDE . ' pixels).');
        }
        if ($w > self::MAX_SIDE || $h > self::MAX_SIDE || $w * $h > self::MAX_PIXELS) {
            throw $fail('The photo dimensions are too large.');
        }

        $raw = file_get_contents($tmp);
        $img = $raw === false ? false : @imagecreatefromstring($raw);
        unset($raw);
        if ($img === false) {
            throw $fail('The image could not be read. It may be corrupted.');
        }

        [$mime, $ext] = self::TYPES[$info[2]];
        if ($mime === 'image/webp' && !function_exists('imagewebp')) {
            [$mime, $ext] = self::TYPES[IMAGETYPE_JPEG];
        }
        $alpha = $mime !== 'image/jpeg';

        // Honour camera orientation for JPEGs.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($tmp);
            $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
            if ($rot !== 0) {
                $r = imagerotate($img, $rot, 0);
                if ($r !== false) {
                    imagedestroy($img);
                    $img = $r;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, $maxDim / max($w, $h));
        if ($scale < 1.0) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($alpha) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);
            $img = $dst;
            [$w, $h] = [$nw, $nh];
        } elseif ($alpha) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        ob_start();
        $ok = match ($mime) {
            'image/png' => imagepng($img, null, 6),
            'image/webp' => imagewebp($img, null, 88),
            default => imagejpeg($img, null, 88),
        };
        $data = (string) ob_get_clean();
        imagedestroy($img);
        if (!$ok || $data === '') {
            throw $fail('The image could not be processed.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = Storage::dir($subdir);
        $full = $dir . '/' . $name;
        if (file_put_contents($full, $data, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write image file.');
        }
        @chmod($full, 0640);

        return ['path' => $subdir . '/' . $name, 'mime' => $mime, 'width' => $w, 'height' => $h, 'size' => strlen($data)];
    }
}
