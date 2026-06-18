<?php
/**
 * Helper para carregar assets compilados pelo Vite.
 * Lê o manifest.json gerado na build e retorna os caminhos com hash.
 */

function vite_manifest(): ?array
{
    static $manifest = null;
    if ($manifest !== null) {
        return $manifest;
    }

    $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';
    if (!file_exists($manifestPath)) {
        return null;
    }

    $content = file_get_contents($manifestPath);
    if ($content === false) {
        return null;
    }

    $manifest = json_decode($content, true);
    return is_array($manifest) ? $manifest : null;
}

function vite_asset(string $entryName, string $type = 'js'): ?string
{
    $manifest = vite_manifest();
    if ($manifest === null) {
        return null;
    }

    // Procura por isEntry=true com src contendo o nome
    foreach ($manifest as $key => $entry) {
        if (!empty($entry['isEntry'])) {
            if (str_ends_with($key, $entryName . '.ts') || $entry['name'] === $entryName) {
                if ($type === 'css' && !empty($entry['css'])) {
                    return $entry['css'][0];
                }
                return $entry['file'];
            }
        }
    }

    // Fallback: arquivo raw (ex: "style.css")
    if (isset($manifest[$entryName . '.css'])) {
        return $manifest[$entryName . '.css']['file'];
    }

    return null;
}

function vite_css(): ?string
{
    $manifest = vite_manifest();
    if ($manifest === null) {
        return null;
    }
    foreach ($manifest as $key => $entry) {
        if ($key === 'style.css' && !empty($entry['file'])) {
            return $entry['file'];
        }
    }
    return null;
}

function vite_entry_imports(string $entryName): array
{
    $manifest = vite_manifest();
    if ($manifest === null) {
        return [];
    }

    $imports = [];
    foreach ($manifest as $key => $entry) {
        if (!empty($entry['isEntry'])) {
            if (str_ends_with($key, $entryName . '.ts') || $entry['name'] === $entryName) {
                if (!empty($entry['imports'])) {
                    foreach ($entry['imports'] as $impKey) {
                        if (isset($manifest[$impKey])) {
                            $imports[] = $manifest[$impKey]['file'];
                        }
                    }
                }
                break;
            }
        }
    }
    return $imports;
}
