<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Env;
use App\Models\VisitorModel;
use App\Services\{DisplayService, HttpException, ImageService, RateLimiter, Request, Response, SettingsService, Storage, Util};
use App\Validators\VisitorValidator;
use MongoDB\BSON\Regex;

final class VisitorController
{
    /** POST /api/visitors — public registration (multipart/form-data). */
    public function store(Request $r): void
    {
        RateLimiter::hit('register', 20, 600, $r->ip());

        $settings = SettingsService::get();
        if (!$settings['registration_enabled']) {
            throw new HttpException(403, 'Visitor registration is currently closed.', [], 'registration_closed');
        }

        $in = $r->body();
        $sid = (string) ($in['submission_id'] ?? '');
        $sid = preg_match('/^[A-Za-z0-9\-]{16,64}$/', $sid) ? $sid : null;

        // Idempotency: a repeated click / network retry returns the original result.
        if ($sid !== null && ($existing = VisitorModel::col()->findOne(['submission_id' => $sid]))) {
            $this->respond($existing, $settings, 200);
        }

        [$clean, $errors] = VisitorValidator::validate($in, $settings);
        $stored = null;
        if (!$errors) {
            try {
                $stored = ImageService::store($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => ''],
                    'visitors', Env::int('UPLOAD_MAX_SIZE', 8388608), 1920, 'photo');
            } catch (HttpException $e) {
                $errors = $e->errors;
            }
        } elseif (empty($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['photo'] = 'Please add a photo.';
        }
        if ($errors) {
            throw new HttpException(422, 'Please correct the highlighted fields.', $errors, 'validation_failed');
        }

        $nowMs = Util::nowMs();
        $now = Util::fromMs($nowMs);
        $expires = Util::fromMs($nowMs + $settings['display_duration_seconds'] * 1000);
        $days = $settings['visitor_retention_days'];
        $doc = [
            'name' => $clean['name'],
            'phone' => $clean['phone'],
            'photo_path' => $stored['path'],
            'created_at' => $now,
            'expires_at' => $expires,
            'status' => 'active',
            'consent_at' => $now,
            'retention_expires_at' => $days > 0 ? Util::fromMs($nowMs + $days * 86400_000) : null,
        ];
        if ($sid !== null) {
            $doc['submission_id'] = $sid;
        }

        try {
            $res = VisitorModel::col()->insertOne($doc);
            $doc['_id'] = $res->getInsertedId();
            DisplayService::activateVisitor($doc['_id'], $now, $expires);
        } catch (\Throwable $e) {
            Storage::delete($stored['path']);
            if ($sid !== null && Util::isDuplicateKey($e) && ($existing = VisitorModel::col()->findOne(['submission_id' => $sid]))) {
                $this->respond($existing, $settings, 200);
            }
            throw $e;
        }
        $this->respond($doc, $settings, 201);
    }

    private function respond(array $v, array $settings, int $status): never
    {
        Response::json(['data' => [
            'id' => (string) $v['_id'],
            'name' => $v['name'],
            'expires_at' => Util::iso($v['expires_at'] ?? null),
            'duration_seconds' => $settings['display_duration_seconds'],
        ]], $status);
    }

    /** GET /api/admin/visitors */
    public function index(Request $r): void
    {
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $per = min(100, max(1, (int) ($r->query['per_page'] ?? 10)));
        $filter = [];

        $q = trim((string) ($r->query['q'] ?? ''));
        if ($q !== '') {
            $rx = new Regex(Util::escapeRegex(mb_substr($q, 0, 60)), 'i');
            $filter['$or'] = [['name' => $rx], ['phone' => $rx]];
        }
        $status = (string) ($r->query['status'] ?? '');
        if (in_array($status, ['active', 'completed'], true)) {
            $filter['status'] = $status;
        }
        $range = [];
        $tz = new \DateTimeZone(date_default_timezone_get());
        foreach (['from' => '00:00:00', 'to' => '23:59:59'] as $k => $time) {
            $d = (string) ($r->query[$k] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "$d $time", $tz);
                if ($dt) {
                    $range[$k === 'from' ? '$gte' : '$lte'] = Util::fromMs($dt->getTimestamp() * 1000);
                }
            }
        }
        if ($range) {
            $filter['created_at'] = $range;
        }

        $total = VisitorModel::col()->countDocuments($filter);
        $cursor = VisitorModel::col()->find($filter, ['sort' => ['created_at' => -1], 'skip' => ($page - 1) * $per, 'limit' => $per]);
        $displayed = $this->displayedId();
        $items = [];
        foreach ($cursor as $v) {
            $items[] = VisitorModel::adminView($v, $displayed);
        }
        Response::json(['data' => $items, 'meta' => [
            'page' => $page, 'per_page' => $per, 'total' => $total, 'pages' => (int) max(1, ceil($total / $per)),
        ]]);
    }

    public function show(Request $r): void
    {
        $v = $this->load($r);
        Response::json(['data' => VisitorModel::adminView($v, $this->displayedId())]);
    }

    public function destroy(Request $r): void
    {
        $v = $this->load($r);
        Storage::delete($v['photo_path'] ?? null);
        VisitorModel::col()->deleteOne(['_id' => $v['_id']]);
        // If this visitor was on screen the next TV poll falls back to the gallery (server-side reconcile).
        Response::json(['data' => ['deleted' => true]]);
    }

    private function load(Request $r): array
    {
        $oid = Util::oid($r->params['id'] ?? null);
        $v = $oid ? VisitorModel::find($oid) : null;
        if (!$v) {
            throw new HttpException(404, 'Visitor not found.', [], 'not_found');
        }
        return $v;
    }

    private function displayedId(): ?string
    {
        $s = DisplayService::col()->findOne(['_id' => DisplayService::ID]);
        return ($s['mode'] ?? '') === 'visitor' && !empty($s['visitor_id']) ? (string) $s['visitor_id'] : null;
    }
}
