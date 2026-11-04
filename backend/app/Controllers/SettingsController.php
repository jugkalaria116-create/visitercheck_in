<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\{DisplayService, HttpException, Request, Response, SettingsService};
use App\Validators\{PhoneValidator, SettingsValidator};

final class SettingsController
{
    public function show(Request $r): void
    {
        Response::json(['data' => SettingsService::get(), 'meta' => [
            'countries' => array_map(static fn($c) => $c['name'], PhoneValidator::COUNTRIES),
        ]]);
    }

    public function update(Request $r): void
    {
        $body = $r->body();
        [$changes, $errors] = SettingsValidator::validate($body);
        if ($errors) {
            throw new HttpException(422, 'Please correct the highlighted settings.', $errors, 'validation_failed');
        }
        if (!$changes) {
            throw new HttpException(422, 'No settings to update.', [], 'validation_failed');
        }
        $settings = SettingsService::update($changes);
        // Duration changes apply to future visitors; the running timer is only touched on explicit request.
        if (isset($changes['display_duration_seconds'])
            && filter_var($body['apply_to_current'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            DisplayService::applyDurationToCurrent($changes['display_duration_seconds']);
        }
        Response::json(['data' => $settings]);
    }
}
