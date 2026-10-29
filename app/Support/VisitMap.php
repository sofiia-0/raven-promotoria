<?php

namespace App\Support;

use App\Models\Visit;

class VisitMap
{
    public static function url(Visit $visit): ?string
    {
        $raw = $visit->getAttributes();
        if (! is_numeric($raw['latitude'] ?? null) || ! is_numeric($raw['longitude'] ?? null)) {
            return null;
        }
        $latitude = (float) $raw['latitude'];
        $longitude = (float) $raw['longitude'];
        if (! is_finite($latitude) || ! is_finite($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }
        $bbox = implode(',', [max(-180, $longitude - .006), max(-90, $latitude - .004), min(180, $longitude + .006), min(90, $latitude + .004)]);

        return 'https://www.openstreetmap.org/export/embed.html?'.http_build_query(['bbox' => $bbox, 'layer' => 'mapnik', 'marker' => $latitude.','.$longitude], '', '&', PHP_QUERY_RFC3986);
    }
}
