<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    $cep = preg_replace('/\D+/', '', (string)($_GET['cep'] ?? ''));
    if (strlen($cep) !== 8) {
        ApiResponse::error('VALIDATION_ERROR', 'CEP inválido.');
    }

    $cacheKey = 'cep_' . $cep;

    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey);
        if ($cached !== false) {
            ApiResponse::success($cached);
        }
    }

    $cacheDir = defined('APP_ROOT') ? APP_ROOT . '/storage/cep_cache/' : (sys_get_temp_dir() . '/cftv_gml_cep_cache/');
    $cacheFile = $cacheDir . hash('sha256', $cep) . '.json';
    $cacheTtl = 86400;

    if (is_file($cacheFile) && filemtime($cacheFile) > (time() - $cacheTtl)) {
        $cached = file_get_contents($cacheFile);
        if ($cached) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                $result = [
                    'cep' => $data['cep'] ?? null,
                    'logradouro' => $data['logradouro'] ?? null,
                    'complemento' => $data['complemento'] ?? null,
                    'bairro' => $data['bairro'] ?? null,
                    'cidade' => $data['localidade'] ?? null,
                    'uf' => $data['uf'] ?? null,
                ];
                if (function_exists('apcu_store')) {
                    apcu_store($cacheKey, $result, 86400);
                }
                ApiResponse::success($result);
            }
        }
    }

    $url = 'https://viacep.com.br/ws/' . $cep . '/json/';

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'sistema-cameras/1.0'
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode >= 400) {
            ApiResponse::error('SERVICE_UNAVAILABLE', 'Falha ao consultar CEP.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\nUser-Agent: sistema-cameras/1.0\r\n"
            ]
        ]);
        $raw = file_get_contents($url, false, $context);
        if ($raw === false) {
            ApiResponse::error('SERVICE_UNAVAILABLE', 'Falha ao consultar CEP.');
        }
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        ApiResponse::error('SERVICE_UNAVAILABLE', 'Resposta inválida do serviço de CEP.');
    }

    if (!empty($data['erro'])) {
        ApiResponse::notFound('CEP');
    }

    @file_put_contents($cacheFile, $raw, LOCK_EX);

    $result = [
        'cep' => $data['cep'] ?? null,
        'logradouro' => $data['logradouro'] ?? null,
        'complemento' => $data['complemento'] ?? null,
        'bairro' => $data['bairro'] ?? null,
        'cidade' => $data['localidade'] ?? null,
        'uf' => $data['uf'] ?? null,
    ];
    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $result, 86400);
    }
    ApiResponse::success($result);
} catch (Throwable $e) {
    error_log('[api_cep_lookup] ' . $e->getMessage());
    ApiResponse::internalError('Falha ao consultar CEP.');
}
