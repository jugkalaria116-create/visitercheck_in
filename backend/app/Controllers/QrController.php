<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\{QrService, Request, Response, SettingsService};

final class QrController
{
    /** GET /api/admin/qr-code?format=svg|png&download=1 — encodes only the public registration URL. */
    public function show(Request $r): void
    {
        $url = SettingsService::get()['registration_url'];
        $format = ($r->query['format'] ?? 'svg') === 'png' ? 'png' : 'svg';
        [$bin, $mime] = QrService::render($url, $format);
        Response::noStore();
        header('Content-Type: ' . $mime);
        if (!empty($r->query['download'])) {
            header('Content-Disposition: attachment; filename="visitor-registration-qr.' . $format . '"');
        }
        header('Content-Length: ' . strlen($bin));
        echo $bin;
        exit;
    }
}
