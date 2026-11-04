<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DisplayClientModel;
use App\Services\{DisplayService, HttpException, RateLimiter, Request, Response, RetentionService, Util};

final class DisplayController
{
    /** GET /api/display/current — authoritative state; performs due expiry/rotation transitions. */
    public function current(Request $r): void
    {
        try {
            RetentionService::maybePurge();
        } catch (\Throwable $e) {
            error_log('Retention purge failed: ' . $e->getMessage());
        }
        Response::json(['data' => DisplayService::current()]);
    }

    /** POST /api/display/heartbeat */
    public function heartbeat(Request $r): void
    {
        RateLimiter::hit('heartbeat', 240, 60, $r->ip());
        $b = $r->body();
        $token = (string) ($b['client_token'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_\-]{20,128}$/', $token)) {
            throw new HttpException(422, 'Invalid client token.', ['client_token' => 'Invalid'], 'validation_failed');
        }
        $name = trim(mb_substr((string) ($b['name'] ?? ''), 0, 40));
        $name = preg_match('/^[\p{L}\p{N} _\-.]{1,40}$/u', $name) ? $name : null;

        $set = [
            'last_seen_at' => Util::now(),
            'active' => true,
            'current_version' => (int) ($b['version'] ?? 0),
            'current_mode' => substr((string) ($b['mode'] ?? ''), 0, 20),
        ];
        $onInsert = ['created_at' => Util::now()];
        if ($name !== null) {
            $set['display_name'] = $name;
        } else {
            $onInsert['display_name'] = 'Unnamed display';
        }
        DisplayClientModel::col()->updateOne(
            ['client_token_hash' => hash('sha256', $token)],
            ['$set' => $set, '$setOnInsert' => $onInsert],
            ['upsert' => true]
        );
        Response::json(['data' => ['ok' => true, 'server_time' => Util::iso(Util::now())]]);
    }

    /** GET /api/admin/displays */
    public function clients(Request $r): void
    {
        $now = Util::nowMs();
        $items = [];
        foreach (DisplayClientModel::col()->find([], ['sort' => ['last_seen_at' => -1], 'limit' => 200]) as $c) {
            $items[] = DisplayClientModel::view($c, $now);
        }
        Response::json(['data' => $items, 'meta' => ['server_time' => Util::iso(Util::now())]]);
    }

    public function deleteClient(Request $r): void
    {
        $oid = Util::oid($r->params['id'] ?? null);
        if (!$oid || DisplayClientModel::col()->deleteOne(['_id' => $oid])->getDeletedCount() === 0) {
            throw new HttpException(404, 'Display not found.', [], 'not_found');
        }
        Response::json(['data' => ['deleted' => true]]);
    }
}
