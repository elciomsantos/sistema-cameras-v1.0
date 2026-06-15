<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $query = trim((string)($_GET['q'] ?? ''));
    if ($query === '') {
        ApiResponse::error('BAD_REQUEST', 'Endereço não informado.');
    }

    function geocodeHttpGet(string $url): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'sistema-cameras/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $httpCode >= 400) {
                return ['ok' => false, 'error' => $curlError !== '' ? $curlError : ('HTTP ' . $httpCode)];
            }

            return ['ok' => true, 'raw' => $raw];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'header' => "Accept: application/json\r\nUser-Agent: sistema-cameras/1.0\r\n"
            ]
        ]);
        $raw = file_get_contents($url, false, $context);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'file_get_contents falhou'];
        }

        return ['ok' => true, 'raw' => $raw];
    }

    function parseGeocodeResponse($parsed): ?array {
        if (!is_array($parsed) || count($parsed) === 0) {
            return null;
        }

        $first = $parsed[0];
        $lat = isset($first['lat']) ? (float)$first['lat'] : null;
        $lon = isset($first['lon']) ? (float)$first['lon'] : null;
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return null;
        }

        return [
            'lat' => $lat,
            'lon' => $lon,
            'display_name' => $first['display_name'] ?? null,
        ];
    }

    function parsePhotonResponse($parsed): ?array {
        if (!is_array($parsed) || empty($parsed['features']) || !is_array($parsed['features'])) {
            return null;
        }

        $first = $parsed['features'][0] ?? null;
        $coords = $first['geometry']['coordinates'] ?? null;
        if (!is_array($coords) || count($coords) < 2) {
            return null;
        }

        $lon = is_numeric($coords[0]) ? (float)$coords[0] : null;
        $lat = is_numeric($coords[1]) ? (float)$coords[1] : null;
        if (!is_numeric($lat) || !is_numeric($lon)) {
            return null;
        }

        return [
            'lat' => $lat,
            'lon' => $lon,
            'display_name' => $first['properties']['name'] ?? null,
        ];
    }

    $db = db();

    // Verificar cache (TTL 30 dias)
    $cacheHash = md5(strtolower(trim($query)));
    $cacheResult = $db->query(
        "SELECT response, created_at FROM geocode_cache WHERE query_hash = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 1",
        [$cacheHash]
    );
    if ($cacheResult['status'] === 'success' && !empty($cacheResult['data'])) {
        $cached = json_decode($cacheResult['data'][0]->response, true);
        if (is_array($cached) && !empty($cached['lat'])) {
            ApiResponse::success($cached);
        }
    }

    $params = [
        'q' => $query,
        'format' => 'jsonv2',
        'countrycodes' => 'br',
        'addressdetails' => '1',
        'limit' => '1'
    ];

    $providers = [
        'https://nominatim.openstreetmap.org/search?' . http_build_query($params),
        'https://photon.komoot.io/api/?' . http_build_query(['q' => $query, 'limit' => 1]),
    ];

    $lastError = 'Falha ao geocodificar endereço';
    foreach ($providers as $url) {
        $result = geocodeHttpGet($url);
        if (!$result['ok']) {
            $lastError = $result['error'] ?? $lastError;
            continue;
        }

        $decoded = json_decode($result['raw'], true);
        if (!is_array($decoded)) {
            $lastError = 'Resposta inválida do geocodificador';
            continue;
        }

        if (strpos($url, 'photon.komoot.io') !== false) {
            $parsed = parsePhotonResponse($decoded);
        } else {
            $parsed = parseGeocodeResponse($decoded);
        }

        if ($parsed !== null) {
            // Salvar no cache
            try {
                $db->query(
                    "INSERT INTO geocode_cache (query_hash, query, response, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE response = VALUES(response), created_at = NOW()",
                    [$cacheHash, $query, json_encode($parsed)]
                );
            } catch (Throwable $cacheErr) {
                error_log('[api_geocode] Cache write failed: ' . $cacheErr->getMessage());
            }

            ApiResponse::success($parsed);
        }

        $lastError = 'Endereço não encontrado';
    }

    ApiResponse::error('NOT_FOUND', $lastError);

} catch (Throwable $e) {
    error_log('[api_geocode] ' . $e->getMessage());
    ApiResponse::internalError('Falha ao geocodificar endereço.');
}
