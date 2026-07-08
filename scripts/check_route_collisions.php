#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Verificador de colisão de rotas
 * 
 * Detecta arquivos com mesmo nome em pastas concorrentes no resolvePageScriptPath:
 * auth/, accounts/, resources/
 * 
 * Uso: php scripts/check_route_collisions.php
 *      php scripts/check_route_collisions.php --strict (exit code 1 se houver colisões)
 */

$baseDir = __DIR__ . '/..';
$folders = ['auth', 'accounts', 'resources'];
$strictMode = in_array('--strict', $argv);

$files = [];
$collisions = [];

foreach ($folders as $folder) {
    $path = $baseDir . '/' . $folder;
    if (!is_dir($path)) {
        continue;
    }
    $files = glob($path . '/*.php');
    foreach ($files as $file) {
        $basename = basename($file);
        $routes[$basename][] = $folder . '/' . $basename;
    }
}

foreach ($routes as $name => $locations) {
    if (count($locations) > 1) {
        $collisions[$name] = $locations;
    }
}

if (empty($collisions)) {
    echo "✅ Nenhuma colisão de rotas detectada.\n";
    exit(0);
}

echo "⚠️  Colisões de rotas detectadas (" . ($strictMode ? 'strict' : 'warning') . "):\n\n";
foreach ($collisions as $name => $locations) {
    echo "  {$name}:\n";
    foreach ($locations as $loc) {
        echo "    - {$loc}\n";
    }
    echo "\n";
}

echo "Total: " . count($collisions) . " colisão(ões).\n";
echo "\nRegra de precedência em public/index.php:\n";
echo "  1. auth/\n";
echo "  2. accounts/\n";
echo "  3. resources/\n";
echo "\nO primeiro encontrado vence (pode ocultar funcionalidade intencional).\n";

if ($strictMode) {
    exit(1);
}

exit(0);