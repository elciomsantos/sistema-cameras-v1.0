<?php
/**
 * check-opcache.php
 *
 * Smoke test para garantir que OPcache esta ativo e configurado em producao.
 * Gera um warning claro se algo estiver OFF e imprime um diagnostico util.
 *
 * Uso: php scripts/check-opcache.php
 * Saida: diagnostico + exit code 0 (OK) ou 1 (OPcache desativado/misconfigurado)
 *
 * Recomendado rodar no CI/deploy apos checar que PHP-FPM/Apache esta no ar.
 * No Apache, rode via HTTP endpoint protegido ou via CLI apontando para o
 * mesmo binario/php.ini usado pelo web server.
 */

declare(strict_types=1);

$exit = 0;

echo "=== Smoke Test OPcache ===\n\n";

if (!function_exists('opcache_get_status')) {
    echo "[FAIL] Extensao OPcache nao carregada.\n";
    echo "       Instale/ativo em php.ini: zend_extension=opcache\n";
    exit(1);
}

if (PHP_SAPI === 'cli' && ini_get('opcache.enable_cli') !== '1') {
    echo "[WARN] opcache.enable_cli=0 (CLI nao usa OPcache).\n";
    echo "       No web server normal isto nao importa, verifique opcache.enable=1 no php.ini do FPM/Apache.\n\n";
}

$status = @opcache_get_status(false);
if (!is_array($status) || empty($status)) {
    echo "[FAIL] opcache_get_status() retornou vazio — OPcache provavelmente desativado.\n";
    echo "       Verifique opcache.enable=1 em php.ini.\n";
    exit(1);
}

$opcacheEnabled = (bool)($status['opcache_enabled'] ?? false);
if (!$opcacheEnabled) {
    echo "[FAIL] OPcache carregado mas nao ativo (opcache_enabled=false).\n";
    $exit = 1;
} else {
    echo "[OK]   OPcache ativo.\n";
}

// Configuracoes recomendadas
$configs = [
    'opcache.enable'               => ['expected' => '1',    'critical' => true,  'desc' => 'OPcache ligado'],
    'opcache.memory_consumption'   => ['expected' => '>=128','critical' => false, 'desc' => 'Memoria >= 128MB recomendada'],
    'opcache.max_accelerated_files'=> ['expected' => '>=10000','critical' => false, 'desc' => 'Max files >= 10000 (projeto tem ~130+ modulos PHP)'],
    'opcache.validate_timestamps'  => ['expected' => '1',    'critical' => false, 'desc' => 'Revalidar timestamps (dev). Em prod com deploy imutavel pode ser 0'],
    'opcache.revalidate_freq'      => ['expected' => '<=60','critical' => false, 'desc' => 'Revalidar a cada N seg (default 2)'],
    'opcache.interned_strings_buffer' => ['expected' => '>=8','critical' => false, 'desc' => 'Strings buffer >= 8MB'],
];

echo "\n--- Configuracoes ---\n";
foreach ($configs as $key => $info) {
    $value = ini_get($key);
    $display = ($value === false) ? '(not set)' : $value;
    $ok = validateConfig($key, $value, $info['expected']);
    $tag = $ok ? '[OK]  ' : '[WARN]';
    if (!$ok && $info['critical']) {
        $tag = '[FAIL]';
        $exit = 1;
    }
    echo "{$tag} {$key} = {$display}  — {$info['desc']}\n";
}

// Estatisticas de hit/miss
echo "\n--- Runtime ---\n";
$mem = $status['memory_usage'] ?? [];
$stats = $status['opcache_statistics'] ?? [];

if (!empty($stats)) {
    $hits   = (int)($stats['hits'] ?? 0);
    $misses = (int)($stats['misses'] ?? 0);
    $total  = $hits + $misses;
    $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    $usedMemMB = isset($mem['used_memory']) ? round((int)$mem['used_memory'] / 1024 / 1024, 2) : 0;
    $freeMemMB = isset($mem['free_memory']) ? round((int)$mem['free_memory'] / 1024 / 1024, 2) : 0;

    echo "Hit rate:    {$hitRate}% ({$hits} hits / {$misses} misses)\n";
    echo "Memoria:     {$usedMemMB} MB usada / {$freeMemMB} MB livre\n";
    echo "Scripts:     " . ($stats['num_scripts'] ?? '?') . " em cache\n";
    echo "Restarts:    " . ($stats['opcache_restart_pending'] ? 'PENDENTE' : 'nao') . "\n";

    if ($hitRate > 0 && $hitRate < 50 && $total > 50) {
        echo "\n[WARN] Hit rate < 50% — sistema esta recompilando scripts frequentemente.\n";
        echo "       Verifique opcache.memory_consumption e opcache.max_accelerated_files.\n";
    }
}

echo "\n=== " . ($exit === 0 ? "PASS" : "FAIL") . " ===\n";
exit($exit);

function validateConfig(string $key, $value, string $expected): bool
{
    if ($value === false || $value === null) return false;
    if (strpos($expected, '>=') === 0) {
        $min = (int)substr($expected, 2);
        return (int)$value >= $min;
    }
    if (strpos($expected, '<=') === 0) {
        $max = (int)substr($expected, 2);
        return (int)$value <= $max;
    }
    return (string)$value === (string)$expected;
}
