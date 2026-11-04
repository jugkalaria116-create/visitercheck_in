<?php
declare(strict_types=1);

namespace App\Validators;

final class SettingsValidator
{
    /** @return array{0:array,1:array} [changes, errors] (partial update) */
    public static function validate(array $in): array
    {
        $c = [];
        $e = [];
        $int = static function (string $key, int $min, int $max, string $label) use (&$c, &$e, $in): void {
            if (!array_key_exists($key, $in)) {
                return;
            }
            $v = filter_var($in[$key], FILTER_VALIDATE_INT);
            if ($v === false || $v < $min || $v > $max) {
                $e[$key] = "$label must be a whole number between $min and $max.";
            } else {
                $c[$key] = $v;
            }
        };
        $int('display_duration_seconds', 10, 86400, 'Visitor display duration (seconds)');
        $int('polling_interval_seconds', 1, 30, 'Polling interval (seconds)');
        $int('gallery_rotation_seconds', 5, 3600, 'Gallery rotation interval (seconds)');
        $int('visitor_retention_days', 0, 3650, 'Retention (days)');

        if (array_key_exists('fallback_mode', $in)) {
            if (in_array($in['fallback_mode'], ['random_image', 'branded'], true)) {
                $c['fallback_mode'] = $in['fallback_mode'];
            } else {
                $e['fallback_mode'] = 'Choose random gallery image or branded screen.';
            }
        }
        if (array_key_exists('registration_enabled', $in)) {
            $b = filter_var($in['registration_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($b === null) {
                $e['registration_enabled'] = 'Must be true or false.';
            } else {
                $c['registration_enabled'] = $b;
            }
        }
        if (array_key_exists('registration_url', $in)) {
            $u = trim((string) $in['registration_url']);
            $parts = parse_url($u);
            if (strlen($u) > 500 || !filter_var($u, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
                $e['registration_url'] = 'Enter a full http(s) URL.';
            } else {
                $c['registration_url'] = $u;
            }
        }
        if (array_key_exists('phone_country', $in)) {
            if (isset(PhoneValidator::COUNTRIES[(string) $in['phone_country']])) {
                $c['phone_country'] = (string) $in['phone_country'];
            } else {
                $e['phone_country'] = 'Unsupported country.';
            }
        }
        return [$c, $e];
    }
}
