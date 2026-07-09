/**
 * Post-build script: copia dist/assets/* para public/assets/
 * Garante que os arquivos hasheados do Vite fiquem acessíveis pelo Apache.
 * JS vai para public/assets/js/ | CSS vai para public/assets/ (flat)
 * Arquivos .gz e subpastas são ignorados.
 *
 * Uso: node scripts/copy-assets.js
 * Integrado automaticamente ao `npm run build`.
 */

import { existsSync, mkdirSync, copyFileSync, readdirSync, unlinkSync, rmSync, readFileSync, writeFileSync } from 'fs';
import { join, resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const DIST_ASSETS = resolve(__dirname, '../dist/assets');
const PUBLIC_ASSETS = resolve(__dirname, '../public/assets');
const JS_DIR = join(PUBLIC_ASSETS, 'js');

function ensureDir(dir) {
    if (!existsSync(dir)) {
        mkdirSync(dir, { recursive: true });
    }
}

function removeViteAssets(dir) {
    if (!existsSync(dir)) return;
    const vitePattern = /^[a-z0-9]+(?:-[a-zA-Z0-9]+)*-[\w-]{6,}\.\w+$/i;
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
        if (entry.isDirectory()) continue;
        const name = entry.name;
        if (vitePattern.test(name) && !name.endsWith('.gz')) {
            unlinkSync(join(dir, name));
        }
    }
}

function copyFlatFiles(srcDir, destDir, extensions) {
    ensureDir(destDir);
    for (const entry of readdirSync(srcDir, { withFileTypes: true })) {
        if (entry.isDirectory()) continue;
        const ext = '.' + entry.name.split('.').pop();
        if (extensions.includes(ext) && !entry.name.endsWith('.gz')) {
            copyFileSync(join(srcDir, entry.name), join(destDir, entry.name));
        }
    }
}

console.log('[postbuild] Copiando assets do Vite para public/assets...');

if (!existsSync(DIST_ASSETS)) {
    console.error('[postbuild] ERRO: dist/assets não encontrado. Execute "npm run build" primeiro.');
    process.exit(1);
}

removeViteAssets(JS_DIR);
removeViteAssets(PUBLIC_ASSETS);

const distJs = join(DIST_ASSETS, 'js');
if (existsSync(distJs)) {
    copyFlatFiles(distJs, JS_DIR, ['.js']);
}

const distCss = join(DIST_ASSETS, 'css');
if (existsSync(distCss)) {
    copyFlatFiles(distCss, PUBLIC_ASSETS, ['.css']);
} else {
    copyFlatFiles(DIST_ASSETS, PUBLIC_ASSETS, ['.css']);
}

const jsFiles = readdirSync(JS_DIR);
const cssFiles = readdirSync(PUBLIC_ASSETS).filter(f => f.endsWith('.css'));

console.log(`[postbuild] OK — ${jsFiles.length} JS | ${cssFiles.length} CSS copiados.`);
if (jsFiles.length > 0) {
    console.log('[postbuild] JS:', jsFiles.join(', '));
}
if (cssFiles.length > 0) {
    console.log('[postbuild] CSS:', cssFiles.join(', '));
}

console.log('[postbuild] Gerando sw-assets.json...');
const MANIFEST_PATH = resolve(__dirname, '../dist/.vite/manifest.json');
const SW_ASSETS_PATH = resolve(__dirname, '../public/sw-assets.json');

if (existsSync(MANIFEST_PATH)) {
    const manifest = JSON.parse(readFileSync(MANIFEST_PATH, 'utf8'));
    const precacheUrls = [];

    precacheUrls.push('./index.php');
    precacheUrls.push('./images/favicon-32x32.png');
    precacheUrls.push('./images/favicon-16x16.png');
    precacheUrls.push('./images/apple-touch-icon.png');

    for (const key of Object.keys(manifest)) {
        const entry = manifest[key];
        const file = entry.file;
        if (file && !file.endsWith('.gz')) {
            precacheUrls.push('./' + file);
        }
        if (entry.css) {
            for (const cssFile of entry.css) {
                if (!cssFile.endsWith('.gz')) {
                    precacheUrls.push('./' + cssFile);
                }
            }
        }
    }

    writeFileSync(SW_ASSETS_PATH, JSON.stringify(precacheUrls, null, 2));
    console.log(`[postbuild] sw-assets.json gerado com ${precacheUrls.length} URLs para pré-cache.`);
} else {
    console.warn('[postbuild] AVISO: manifest.json não encontrado. SW pode não cachear corretamente.');
}