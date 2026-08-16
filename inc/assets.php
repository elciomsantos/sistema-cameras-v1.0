<?php
/**
 * Helper para carregar assets compilados pelo Vite.
 * Lê o manifest.json gerado na build e retorna os caminhos com hash.
 */

function vite_manifest(): array
{
    static $manifest = null;
    if ($manifest !== null) {
        return $manifest;
    }

    $manifestPath = __DIR__ . '/../dist/.vite/manifest.json';
    if (!file_exists($manifestPath)) {
        throw new RuntimeException(
            'Manifest do Vite nao encontrado em dist/.vite/manifest.json. '
            . 'Execute "npm run build" antes de acessar o sistema.'
        );
    }

    $content = file_get_contents($manifestPath);
    if ($content === false) {
        throw new RuntimeException('Falha ao ler dist/.vite/manifest.json.');
    }

    $manifest = json_decode($content, true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Manifest do Vite invalido em dist/.vite/manifest.json.');
    }
    return $manifest;
}

function vite_asset(string $entryName): ?string
{
    $manifest = vite_manifest();

    foreach ($manifest as $entry) {
        if (!empty($entry['isEntry']) && ($entry['name'] ?? '') === $entryName) {
            return $entry['file'];
        }
    }

    return null;
}

function vite_css(): ?string
{
    $manifest = vite_manifest();
    foreach ($manifest as $key => $entry) {
        if ($key === 'index.html' && !empty($entry['css'])) {
            return $entry['css'][0];
        }
    }
    return null;
}

function vite_page_css(string $entryName): void
{
    $manifest = vite_manifest();
    foreach ($manifest as $entry) {
        if (!empty($entry['isEntry']) && ($entry['name'] ?? '') === $entryName && !empty($entry['css'])) {
            foreach ($entry['css'] as $css) {
                echo '<link rel="stylesheet" href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/' . $css . '">' . "\n";
            }
            return;
        }
    }
}

function vite_entry_imports(string $entryName): array
{
    $manifest = vite_manifest();

    $imports = [];
    foreach ($manifest as $entry) {
        if (!empty($entry['isEntry']) && ($entry['name'] ?? '') === $entryName) {
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
    return $imports;
}

function vite_page_js(string $entryName): void
{
    $vitePageJs = vite_asset($entryName);
    $viteImports = vite_entry_imports($entryName);
    if ($vitePageJs) :
        foreach ($viteImports as $imp) : ?>
<link rel="modulepreload" href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/<?= $imp ?>">
        <?php endforeach; ?>
<script type="module" src="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/<?= $vitePageJs ?>"></script>
    <?php endif;
}

function vite_bootstrap_css(): ?string
{
    $manifest = vite_manifest();
    foreach ($manifest as $entry) {
        if (!empty($entry['isEntry']) && ($entry['name'] ?? '') === 'bootstrap' && !empty($entry['css'])) {
            return $entry['css'][0];
        }
    }
    return null;
}

function vite_fontawesome_css(): ?string
{
    $manifest = vite_manifest();
    foreach ($manifest as $entry) {
        if (!empty($entry['isEntry']) && ($entry['name'] ?? '') === 'fontawesome' && !empty($entry['css'])) {
            return $entry['css'][0];
        }
    }
    return null;
}

function vite_bootstrap_js(): ?string
{
    $manifest = vite_manifest();
    foreach ($manifest as $entry) {
        if (!empty($entry['isEntry']) && ($entry['name'] ?? '') === 'bootstrap-js') {
            return $entry['file'];
        }
    }
    return null;
}
