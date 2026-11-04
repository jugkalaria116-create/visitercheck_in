<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\GalleryModel;
use App\Models\VisitorModel;
use App\Services\{DisplayService, HttpException, Request, Storage, Util};

/** Controlled image delivery. Files live outside the web root and are never directly addressable. */
final class MediaController
{
    public function visitor(Request $r): void
    {
        $oid = Util::oid($r->params['id'] ?? null);
        $v = $oid ? VisitorModel::find($oid) : null;
        if (!$v) {
            throw new HttpException(404, 'Not found.', [], 'not_found');
        }
        $isAdmin = AuthMiddleware::currentAdmin() !== null;
        if (!$isAdmin) {
            // Anonymous clients (TVs) may only fetch the photo that is on screen right now.
            $s = DisplayService::col()->findOne(['_id' => DisplayService::ID]);
            $onScreen = ($s['mode'] ?? '') === 'visitor' && (string) ($s['visitor_id'] ?? '') === (string) $oid;
            if (!$onScreen) {
                throw new HttpException(404, 'Not found.', [], 'not_found');
            }
        }
        $this->send(Storage::resolve($v['photo_path'] ?? null), false);
    }

    public function gallery(Request $r): void
    {
        $oid = Util::oid($r->params['id'] ?? null);
        $g = $oid ? GalleryModel::find($oid) : null;
        if (!$g || (!($g['enabled'] ?? false) && AuthMiddleware::currentAdmin() === null)) {
            throw new HttpException(404, 'Not found.', [], 'not_found');
        }
        $this->send(Storage::resolve($g['image_path'] ?? null), true);
    }

    private function send(?string $file, bool $cacheable): never
    {
        if ($file === null) {
            throw new HttpException(404, 'Not found.', [], 'not_found');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new HttpException(404, 'Not found.', [], 'not_found');
        }
        $etag = '"' . md5_file($file) . '"';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('ETag: ' . $etag);
        // Visitor photos must never be reused after they leave the screen; gallery images may be briefly cached.
        header('Cache-Control: ' . ($cacheable ? 'private, max-age=60, must-revalidate' : 'no-store, max-age=0'));
        if ($cacheable && ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
