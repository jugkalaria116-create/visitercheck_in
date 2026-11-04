<?php
declare(strict_types=1);

namespace App\Validators;

final class PhoneValidator
{
    /** Country rules for the national significant number. */
    public const COUNTRIES = [
        'IN' => ['name' => 'India', 'code' => '91', 'pattern' => '/^[6-9]\d{9}$/', 'trunk' => '0'],
        'US' => ['name' => 'United States', 'code' => '1', 'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/', 'trunk' => ''],
        'CA' => ['name' => 'Canada', 'code' => '1', 'pattern' => '/^[2-9]\d{2}[2-9]\d{6}$/', 'trunk' => ''],
        'GB' => ['name' => 'United Kingdom', 'code' => '44', 'pattern' => '/^(7\d{9}|[1-3]\d{8,9})$/', 'trunk' => '0'],
        'AU' => ['name' => 'Australia', 'code' => '61', 'pattern' => '/^(4\d{8}|[2378]\d{8})$/', 'trunk' => '0'],
        'AE' => ['name' => 'United Arab Emirates', 'code' => '971', 'pattern' => '/^(5\d{8}|[2-9]\d{7})$/', 'trunk' => '0'],
        'ANY' => ['name' => 'Any country (international)', 'code' => '', 'pattern' => '/^\d{7,15}$/', 'trunk' => ''],
    ];

    /** @return string|null normalised number (e.g. +919876543210) or null when invalid */
    public static function normalize(string $input, string $country): ?string
    {
        $rule = self::COUNTRIES[$country] ?? self::COUNTRIES['IN'];
        $s = preg_replace('/[\s().\-]/', '', trim($input)) ?? '';
        if ($s === '' || !preg_match('/^\+?\d+$/', $s)) {
            return null;
        }
        if (str_starts_with($s, '00')) {
            $s = '+' . substr($s, 2);
        }
        $code = $rule['code'];

        if ($country === 'ANY') {
            $digits = ltrim($s, '+');
            return preg_match($rule['pattern'], $digits) ? '+' . $digits : null;
        }
        if (str_starts_with($s, '+')) {
            $digits = substr($s, 1);
            if (!str_starts_with($digits, $code)) {
                return null;
            }
            $national = substr($digits, strlen($code));
        } else {
            $national = $s;
            if ($rule['trunk'] !== '' && str_starts_with($national, $rule['trunk'])) {
                $national = substr($national, strlen($rule['trunk']));
            } elseif (str_starts_with($national, $code) && strlen($national) > 10) {
                $national = substr($national, strlen($code));
            }
        }
        return preg_match($rule['pattern'], $national) ? '+' . $code . $national : null;
    }
}
