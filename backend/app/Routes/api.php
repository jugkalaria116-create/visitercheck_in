<?php
declare(strict_types=1);

use App\Controllers\{AuthController, DisplayController, GalleryController, HealthController, MediaController,
    PublicController, QrController, SettingsController, StatsController, VisitorController};
use App\Middleware\{AuthMiddleware, CsrfMiddleware};
use App\Routes\Router;

return static function (Router $r): void {
    $admin = [AuthMiddleware::class, CsrfMiddleware::class]; // CSRF is a no-op for GET

    // Public
    $r->get('/api/health', [HealthController::class, 'show']);
    $r->get('/api/public/config', [PublicController::class, 'config']);
    $r->post('/api/visitors', [VisitorController::class, 'store']);
    $r->get('/api/display/current', [DisplayController::class, 'current']);
    $r->post('/api/display/heartbeat', [DisplayController::class, 'heartbeat']);
    $r->get('/api/media/visitor/:id', [MediaController::class, 'visitor']);
    $r->get('/api/media/gallery/:id', [MediaController::class, 'gallery']);

    // Admin auth
    $r->get('/api/admin/me', [AuthController::class, 'me']);
    $r->post('/api/admin/login', [AuthController::class, 'login'], [CsrfMiddleware::class]);
    $r->post('/api/admin/logout', [AuthController::class, 'logout'], $admin);

    // Admin data
    $r->get('/api/admin/stats', [StatsController::class, 'show'], $admin);
    $r->get('/api/admin/visitors', [VisitorController::class, 'index'], $admin);
    $r->get('/api/admin/visitors/:id', [VisitorController::class, 'show'], $admin);
    $r->delete('/api/admin/visitors/:id', [VisitorController::class, 'destroy'], $admin);
    $r->get('/api/admin/gallery', [GalleryController::class, 'index'], $admin);
    $r->post('/api/admin/gallery', [GalleryController::class, 'store'], $admin);
    $r->patch('/api/admin/gallery/:id', [GalleryController::class, 'update'], $admin);
    $r->delete('/api/admin/gallery/:id', [GalleryController::class, 'destroy'], $admin);
    $r->get('/api/admin/settings', [SettingsController::class, 'show'], $admin);
    $r->patch('/api/admin/settings', [SettingsController::class, 'update'], $admin);
    $r->get('/api/admin/displays', [DisplayController::class, 'clients'], $admin);
    $r->delete('/api/admin/displays/:id', [DisplayController::class, 'deleteClient'], $admin);
    $r->get('/api/admin/qr-code', [QrController::class, 'show'], $admin);
};
