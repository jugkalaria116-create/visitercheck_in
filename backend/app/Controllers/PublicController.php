<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\{Request, Response, SettingsService};
use App\Validators\PhoneValidator;

final class PublicController
{
    public function config(Request $r): void
    {
        $cfg = SettingsService::publicView();
        $cfg['phone_country_name'] = PhoneValidator::COUNTRIES[$cfg['phone_country']]['name'] ?? '';
        Response::json(['data' => $cfg]);
    }
}
