<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingController extends Controller
{
    /**
     * Reverse geocode latitude and longitude to a human-readable Indonesian address.
     * Caches results using coordinate rounding to optimize performance and prevent rate limiting.
     */
    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];

        // Grid clustering (~11 meter precision) for caching
        $cacheLat = round($lat, 4);
        $cacheLng = round($lng, 4);
        $cacheKey = "geo_reverse_{$cacheLat}_{$cacheLng}";

        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            return response()->json([
                'success' => true,
                'data' => array_merge($cachedResult, [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_cached' => true,
                ]),
            ]);
        }

        $address = null;

        // 1. Optional Google Maps Geocoding if API key is provided
        $googleKey = config('services.google.maps_key', env('GOOGLE_MAPS_API_KEY'));
        if (!empty($googleKey)) {
            $address = $this->reverseWithGoogle($lat, $lng, $googleKey);
        }

        // 2. OpenStreetMap Nominatim fallback
        if (!$address) {
            $address = $this->reverseWithNominatim($lat, $lng);
        }

        // 3. Fallback string if all providers fail
        if (!$address) {
            $address = "Koordinat: {$lat}, {$lng}";
        }

        $resultData = [
            'address' => $address,
            'latitude' => $lat,
            'longitude' => $lng,
            'is_cached' => false,
        ];

        // Cache 24 hours
        Cache::put($cacheKey, ['address' => $address], 86400);

        return response()->json([
            'success' => true,
            'data' => $resultData,
        ]);
    }

    /**
     * Reverse geocode via Google Maps API
     */
    private function reverseWithGoogle(float $lat, float $lng, string $apiKey): ?string
    {
        try {
            $response = Http::timeout(3)
                ->get('https://maps.googleapis.com/maps/api/geocode/json', [
                    'latlng' => "{$lat},{$lng}",
                    'key' => $apiKey,
                    'language' => 'id',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['results'][0]['formatted_address'])) {
                    return $data['results'][0]['formatted_address'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Google Reverse Geocoding failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Reverse geocode via OpenStreetMap Nominatim
     */
    private function reverseWithNominatim(float $lat, float $lng): ?string
    {
        try {
            $response = Http::timeout(4)
                ->withHeaders([
                    'User-Agent' => 'SiBintangKurir/1.0 (courier-tracking@bintangsempurna.co.id)',
                    'Accept-Language' => 'id,en;q=0.9',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['address'])) {
                    return $this->formatNominatimAddress($data['address'], $data['display_name'] ?? null);
                }

                if (!empty($data['display_name'])) {
                    return $data['display_name'];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Nominatim Reverse Geocoding failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Clean and format address components into standard Indonesian hierarchy
     */
    private function formatNominatimAddress(array $addr, ?string $fallback): string
    {
        $parts = [];

        // Road / Street
        $road = $addr['road'] ?? $addr['pedestrian'] ?? $addr['street'] ?? null;
        $houseNumber = $addr['house_number'] ?? null;
        if ($road) {
            $parts[] = $houseNumber ? "{$road} No.{$houseNumber}" : $road;
        }

        // Suburb / Kelurahan / Desa
        $suburb = $addr['neighbourhood'] ?? $addr['suburb'] ?? $addr['village'] ?? null;
        if ($suburb && $suburb !== $road) {
            $parts[] = $suburb;
        }

        // District / Kecamatan
        $district = $addr['city_district'] ?? $addr['district'] ?? null;
        if ($district && !in_array($district, $parts)) {
            $parts[] = "Kecamatan " . ltrim($district, 'Kecamatan ');
        }

        // City / Kabupaten
        $city = $addr['city'] ?? $addr['county'] ?? $addr['municipality'] ?? null;
        if ($city && !in_array($city, $parts)) {
            $parts[] = $city;
        }

        // Province / State & Postcode
        $state = $addr['state'] ?? null;
        $postcode = $addr['postcode'] ?? null;

        if ($state && !in_array($state, $parts)) {
            $parts[] = $state . ($postcode ? " {$postcode}" : '');
        } elseif ($postcode) {
            $parts[] = $postcode;
        }

        if (!empty($parts)) {
            return implode(', ', $parts);
        }

        return $fallback ?? 'Lokasi Kurir Terdeteksi';
    }
}
