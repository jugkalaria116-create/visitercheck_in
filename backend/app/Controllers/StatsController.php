<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\{DisplayClientModel, GalleryModel, VisitorModel};
use App\Services\{DisplayService, Request, Response, SettingsService, Util};
use MongoDB\BSON\ObjectId;

final class StatsController
{
    public function show(Request $r): void
    {
        $settings = SettingsService::get();
        $display = DisplayService::current();       // also reconciles due transitions
        $state = DisplayService::col()->findOne(['_id' => DisplayService::ID]);

        $startOfDay = new \DateTimeImmutable('today');
        $now = Util::nowMs();
        $online = 0;
        $recent = 0;
        $total = 0;
        foreach (DisplayClientModel::col()->find([], ['projection' => ['last_seen_at' => 1]]) as $c) {
            $total++;
            $age = ($now - (Util::ms($c['last_seen_at'] ?? null) ?? 0)) / 1000;
            if ($age <= DisplayClientModel::ONLINE_SECONDS) {
                $online++;
            } elseif ($age <= DisplayClientModel::RECENT_SECONDS) {
                $recent++;
            }
        }

        $visitor = null;
        $imageTitle = null;
        if (($state['mode'] ?? '') === 'visitor' && ($state['visitor_id'] ?? null) instanceof ObjectId) {
            if ($v = VisitorModel::find($state['visitor_id'])) {
                $visitor = ['id' => (string) $v['_id'], 'name' => $v['name'], 'photo_url' => '/api/media/visitor/' . $v['_id'],
                    'registered_at' => Util::iso($v['created_at'] ?? null)];
            }
        } elseif (($state['image_id'] ?? null) instanceof ObjectId) {
            $imageTitle = GalleryModel::find($state['image_id'])['title'] ?? null;
        }

        Response::json(['data' => [
            'total_visitors' => VisitorModel::col()->countDocuments([]),
            'today_visitors' => VisitorModel::col()->countDocuments(['created_at' => ['$gte' => Util::fromMs($startOfDay->getTimestamp() * 1000)]]),
            'enabled_gallery_images' => GalleryModel::col()->countDocuments(['enabled' => true]),
            'total_gallery_images' => GalleryModel::col()->countDocuments([]),
            'tvs' => ['online' => $online, 'recently_disconnected' => $recent, 'total' => $total],
            'registration_enabled' => $settings['registration_enabled'],
            'display_duration_seconds' => $settings['display_duration_seconds'],
            'display' => [
                'mode' => $display['mode'], 'version' => $display['version'], 'expires_at' => $display['expires_at'],
                'visitor' => $visitor, 'image_title' => $imageTitle, 'has_image' => $display['image_url'] !== null,
            ],
            'database' => Database::ping() ? 'up' : 'down',
            'server_time' => Util::iso(Util::now()),
        ]]);
    }
}
