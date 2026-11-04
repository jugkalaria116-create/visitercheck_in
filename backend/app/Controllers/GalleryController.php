<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Env;
use App\Models\GalleryModel;
use App\Services\{HttpException, ImageService, Request, Response, Storage, Util};

final class GalleryController
{
    public function index(Request $r): void
    {
        $items = [];
        foreach (GalleryModel::col()->find([], ['sort' => ['created_at' => -1]]) as $g) {
            $items[] = GalleryModel::view($g);
        }
        Response::json(['data' => $items]);
    }

    /** POST multipart: images[] (1–20 files), optional titles[] */
    public function store(Request $r): void
    {
        $files = $this->normalizeFiles($_FILES['images'] ?? null);
        if (!$files) {
            throw new HttpException(422, 'Choose at least one image.', ['images' => 'No files received.'], 'validation_failed');
        }
        if (count($files) > 20) {
            throw new HttpException(422, 'Upload at most 20 images at a time.', ['images' => 'Too many files.'], 'validation_failed');
        }
        $titles = $r->body()['titles'] ?? [];
        $uploaded = [];
        $failed = [];
        foreach ($files as $i => $f) {
            try {
                $img = ImageService::store($f, 'gallery', Env::int('UPLOAD_MAX_SIZE', 8388608), 3840, 'images');
            } catch (HttpException $e) {
                $failed[] = ['name' => (string) $f['name'], 'error' => $e->getMessage()];
                continue;
            }
            $title = is_array($titles) && isset($titles[$i]) ? (string) $titles[$i] : pathinfo((string) $f['name'], PATHINFO_FILENAME);
            $doc = [
                'title' => $this->cleanTitle($title),
                'image_path' => $img['path'],
                'mime_type' => $img['mime'],
                'enabled' => true,
                'created_at' => Util::now(),
            ];
            $doc['_id'] = GalleryModel::col()->insertOne($doc)->getInsertedId();
            $uploaded[] = GalleryModel::view($doc);
        }
        Response::json(['data' => ['uploaded' => $uploaded, 'failed' => $failed]], $uploaded ? 201 : 422);
    }

    public function update(Request $r): void
    {
        $g = $this->load($r);
        $b = $r->body();
        $set = [];
        if (array_key_exists('title', $b)) {
            $t = $this->cleanTitle((string) $b['title']);
            if ($t === '') {
                throw new HttpException(422, 'Title cannot be empty.', ['title' => 'Required.'], 'validation_failed');
            }
            $set['title'] = $t;
        }
        if (array_key_exists('enabled', $b)) {
            $en = filter_var($b['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($en === null) {
                throw new HttpException(422, 'Enabled must be true or false.', ['enabled' => 'Invalid.'], 'validation_failed');
            }
            $set['enabled'] = $en;
        }
        if ($set) {
            GalleryModel::col()->updateOne(['_id' => $g['_id']], ['$set' => $set]);
        }
        Response::json(['data' => GalleryModel::view(GalleryModel::find($g['_id']))]);
    }

    public function destroy(Request $r): void
    {
        $g = $this->load($r);
        GalleryModel::col()->deleteOne(['_id' => $g['_id']]);
        Storage::delete($g['image_path'] ?? null);   // TVs rotate away on their next poll
        Response::json(['data' => ['deleted' => true]]);
    }

    private function load(Request $r): array
    {
        $oid = Util::oid($r->params['id'] ?? null);
        $g = $oid ? GalleryModel::find($oid) : null;
        if (!$g) {
            throw new HttpException(404, 'Image not found.', [], 'not_found');
        }
        return $g;
    }

    private function cleanTitle(string $t): string
    {
        $t = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($t)));
        return mb_substr($t, 0, 100);
    }

    private function normalizeFiles(?array $f): array
    {
        if (!$f) {
            return [];
        }
        if (!is_array($f['name'])) {
            return [$f];
        }
        $out = [];
        foreach ($f['name'] as $i => $_) {
            $out[] = ['name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
                'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        }
        return $out;
    }
}
