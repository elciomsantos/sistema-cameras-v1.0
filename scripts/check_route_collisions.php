<?php
/**
 * check_route_collisions.php
 *
 * Verifica se um mesmo nome de página existe em múltiplos diretórios
 * (auth/, accounts/, resources/), o que causaria shadowing silencioso
 * no roteador resolvePageScriptPath() em public/index.php.
 *
 * Uso: php scripts/check_route_collisions.php
 * Saída: lista de colisões + exit code 0 (sem colisões) ou 1 (colisões encontradas)
 */

$dirs = [
    'auth'      => __DIR__ . '/../auth',
    'accounts'  => __DIR__ . '/../accounts',
    'resources' => __DIR__ . '/../resources',
];

$filesByDir = [];
foreach ($dirs as $name => $dir) {
    $files = glob($dir . '/*.php');
    $map = [];
    foreach ($files as $f) {
        $base = basename($f);
        $map[$base][] = $name;
    }
    $filesByDir[$name] = $map;
}

$allFiles = [];
foreach ($filesByDir as $name => $map) {
    foreach ($map as $file => $dirsList) {
        $allFiles[$file][] = $name;
    }
}

$hasCollisions = false;
ksort($allFiles);

echo "=== Verificacao de colisoes de rota ===\n\n";

foreach ($allFiles as $file => $dirsList) {
    if (count($dirsList) > 1) {
        $hasCollisions = true;
        echo "COLISAO: {$file}\n";
        echo "  Presente em: " . implode(', ', $dirsList) . "\n";
        echo "  Vencedor (primeiro na ordem): {$dirsList[0]}\n";
        echo "  Shadowed: " . implode(', ', array_slice($dirsList, 1)) . "\n\n";
    }
}

if (!$hasCollisions) {
    echo "Nenhuma colisao encontrada.\n";
    exit(0);
}

echo "Total de " . count($allFiles) . " arquivos, " . count(array_filter($allFiles, fn($d) => count($d) > 1)) . " colisoes.\n";
echo "Corrija renomeando ou removendo duplicatas.\n";
exit(1);
