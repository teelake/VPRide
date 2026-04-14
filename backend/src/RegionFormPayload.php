<?php

declare(strict_types=1);

namespace VprideBackend;

use InvalidArgumentException;

final class RegionFormPayload
{
    /** @var list<string> */
    public const KNOWN_LOCALES = ['en_CA', 'fr_CA', 'en_US', 'en_NG', 'en_GB'];

    /**
     * Build the mobile API payload array from POST (no JSON typing required in the browser).
     *
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    public static function buildFromPost(array $post): array
    {
        $errors = [];

        $version = max(1, (int) ($post['version'] ?? 1));

        $brand = trim((string) ($post['branding']['serviceAreaLabel'] ?? ''));
        if ($brand === '') {
            $errors[] = 'Service area label is required.';
        }

        $defLocale = trim((string) ($post['localization']['defaultLocale'] ?? 'en_CA'));
        if ($defLocale === '') {
            $defLocale = 'en_CA';
        }

        $supported = [];
        $locGroup = $post['localization']['loc'] ?? [];
        if (is_array($locGroup)) {
            foreach (self::KNOWN_LOCALES as $code) {
                if (! empty($locGroup[$code])) {
                    $supported[] = $code;
                }
            }
        }
        $extra = trim((string) ($post['localization']['extraLocales'] ?? ''));
        if ($extra !== '') {
            foreach (preg_split('/\s*,\s*/', $extra) as $part) {
                $part = trim($part);
                if ($part !== '' && ! in_array($part, $supported, true)) {
                    $supported[] = $part;
                }
            }
        }
        if (! in_array($defLocale, $supported, true)) {
            array_unshift($supported, $defLocale);
        }
        $supported = array_values(array_unique($supported));

        $countriesIn = $post['countries'] ?? [];
        if (! is_array($countriesIn)) {
            $countriesIn = [];
        }

        $countries = [];
        foreach ($countriesIn as $c) {
            if (! is_array($c)) {
                continue;
            }
            $code = strtoupper(trim((string) ($c['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            if (strlen($code) !== 2) {
                $errors[] = "Country “{$code}”: use a 2-letter ISO code.";

                continue;
            }

            $name = trim((string) ($c['name'] ?? ''));
            if ($name === '') {
                $errors[] = "Country {$code}: name is required.";
            }

            $currency = strtoupper(trim((string) ($c['currencyCode'] ?? '')));
            if (strlen($currency) !== 3) {
                $errors[] = "Country {$code}: currency must be 3 letters (e.g. CAD).";
            }

            $dist = strtolower(trim((string) ($c['distanceUnit'] ?? 'km')));
            if (! in_array($dist, ['km', 'mi'], true)) {
                $dist = 'km';
            }

            $citiesIn = $c['cities'] ?? [];
            if (! is_array($citiesIn)) {
                $citiesIn = [];
            }

            $cities = [];
            foreach ($citiesIn as $ct) {
                if (! is_array($ct)) {
                    continue;
                }
                $cid = trim((string) ($ct['id'] ?? ''));
                if ($cid === '') {
                    continue;
                }
                if (! preg_match('/^[a-z0-9_-]+$/i', $cid)) {
                    $errors[] = "City ID “{$cid}”: use only letters, numbers, hyphen, underscore.";
                }

                $cname = trim((string) ($ct['name'] ?? ''));
                if ($cname === '') {
                    $errors[] = "City {$cid}: name is required.";
                }

                $sub = trim((string) ($ct['subdivision'] ?? ''));

                $latRaw = $ct['latitude'] ?? '';
                $lngRaw = $ct['longitude'] ?? '';
                $lat = is_numeric($latRaw) ? (float) $latRaw : null;
                $lng = is_numeric($lngRaw) ? (float) $lngRaw : null;
                if ($lat === null || $lat < -90.0 || $lat > 90.0) {
                    $errors[] = "City {$cid}: latitude must be a number between -90 and 90.";

                    continue;
                }
                if ($lng === null || $lng < -180.0 || $lng > 180.0) {
                    $errors[] = "City {$cid}: longitude must be a number between -180 and 180.";

                    continue;
                }

                $active = ! empty($ct['isActive']);

                $cities[] = [
                    'id' => $cid,
                    'name' => $cname,
                    'subdivision' => $sub,
                    'isActive' => $active,
                    'center' => [
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ],
                ];
            }

            if ($cities === []) {
                $errors[] = "Country {$code}: add at least one city with coordinates.";
            }

            $countries[] = [
                'code' => $code,
                'name' => $name,
                'currencyCode' => $currency,
                'distanceUnit' => $dist,
                'cities' => $cities,
            ];
        }

        if ($countries === []) {
            $errors[] = 'Add at least one country.';
        }

        $defCountry = strtoupper(trim((string) ($post['defaults']['countryCode'] ?? '')));
        $defCity = trim((string) ($post['defaults']['cityId'] ?? ''));
        if ($defCountry === '' || $defCity === '') {
            $errors[] = 'Choose default country and default city.';
        } else {
            $found = false;
            foreach ($countries as $co) {
                if ($co['code'] !== $defCountry) {
                    continue;
                }
                foreach ($co['cities'] as $ct) {
                    if ($ct['id'] === $defCity) {
                        $found = true;
                        break 2;
                    }
                }
            }
            if (! $found) {
                $errors[] = 'Default city must exist under the default country.';
            }
        }

        $errors = array_values(array_unique(array_filter($errors)));
        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        return [
            'version' => $version,
            'updatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'branding' => [
                'serviceAreaLabel' => $brand,
            ],
            'localization' => [
                'defaultLocale' => $defLocale,
                'supportedLocales' => $supported,
            ],
            'countries' => $countries,
            'defaults' => [
                'countryCode' => $defCountry,
                'cityId' => $defCity,
            ],
        ];
    }
}
