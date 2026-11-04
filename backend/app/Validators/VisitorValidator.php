<?php
declare(strict_types=1);

namespace App\Validators;

final class VisitorValidator
{
    /** @return array{0:array,1:array} [clean, errors] */
    public static function validate(array $in, array $settings): array
    {
        $errors = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($in['name'] ?? '')));
        if ($name === '') {
            $errors['name'] = 'Please enter your full name.';
        } elseif (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s.\'’\-]{1,79}$/u', $name)) {
            $errors['name'] = 'Use letters only (2–80 characters).';
        }

        $phone = null;
        $rawPhone = trim((string) ($in['phone'] ?? ''));
        if ($rawPhone === '') {
            $errors['phone'] = 'Please enter your phone number.';
        } else {
            $phone = PhoneValidator::normalize($rawPhone, $settings['phone_country']);
            if ($phone === null) {
                $c = PhoneValidator::COUNTRIES[$settings['phone_country']]['name'] ?? 'the configured country';
                $errors['phone'] = 'Enter a valid phone number for ' . $c . '.';
            }
        }

        $consent = $in['consent'] ?? null;
        if (!in_array($consent, [true, 1, '1', 'true', 'on', 'yes'], true)) {
            $errors['consent'] = 'You must agree to the display notice to continue.';
        }

        return [['name' => $name, 'phone' => $phone], $errors];
    }
}
