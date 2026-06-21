# Plano de Correções e Melhorias Pós-Migração Vite

> Gerado em: 18/06/2026
> Status: 516 erros TypeScript (tsc --noEmit)
> Build Vite: funcional (esbuild ignora tipos), mas runtime quebrado em produção

---

## FASE 1 — Corrigir Runtime (Vite Funcional em Produção)

### 1.1 Assets do dist/ inacessíveis em runtime [CRÍTICO]

**Problema:** `inc/assets.php` retorna caminhos como `assets/js/main-CHF12Ryp.js`, mas `BASE_URL` aponta para `public/`. Não existe symlink, alias Apache ou mecanismo de cópia que mapeie `dist/assets/` sob o web root. Assets Vite vão 404 em produção.

**Solução A — Apache Alias (recomendado para XAMPP):**
Adicionar no VirtualHost ou `.htaccess` do `public/`:
```apache
Alias /assets/js /xampp/htdocs/sistema-cameras-v1.0/dist/assets/js
Alias /assets/style /xampp/htdocs/sistema-cameras-v1.0/dist/assets
<Directory "C:/xampp/htdocs/sistema-cameras-v1.0/dist">
    Require all granted
</Directory>
```

**Solução B — Symlink (Linux):**
```bash
ln -s ../dist/assets/js public/assets/js/dist
```

**Solução C — Alterar vite.config.ts e assets.php:**
- Mudar `base: '/dist/'` no vite.config.ts
- Ajustar assets.php para prefixar `/dist/` nos caminhos retornados
- Adicionar alias Apache para servir `/dist/` a partir da raiz do projeto

**Solução D — Script pós-build (mais simples):**
Criar script que copia `dist/assets/` para `public/assets/` após cada build:
```bash
# Em package.json scripts:
"postbuild": "xcopy /E /Y dist\\assets public\\assets\\"
```

---

### 1.2 Adicionar 9 entry points faltantes no Vite [CRÍTICO]

**Problema:** 9 páginas carregam JS legado de `public/assets/js/` sem integração Vite. Os `.ts` existem em `src/assets/js/` mas não estão em `rollupOptions.input`.

**Arquivos TS sem entry point:**
- `cadastro_alarmes.ts`
- `editar_alarmes.ts`
- `listar_cameras.ts`
- `listar_alarmes.ts`
- `auditoria-cameras.ts`
- `relatorios.ts`
- `relatorios_alarmes.ts`
- `cadastro_locais.ts` (se existir)
- `editar_locais.ts` (se existir)

**Ação:**
1. Criar arquivos `src/assets/js/entries/` para cada um:
```typescript
// Ex: src/assets/js/entries/cadastro-alarmes.ts
import '../cadastro_alarmes';
```
2. Adicionar em `vite.config.ts` `rollupOptions.input`:
```typescript
input: {
  // ... existentes ...
  'cadastro-alarmes': './assets/js/entries/cadastro-alarmes.ts',
  'editar-alarmes': './assets/js/entries/editar-alarmes.ts',
  'listar-cameras': './assets/js/entries/listar-cameras.ts',
  'listar-alarmes': './assets/js/entries/listar-alarmes.ts',
  'auditoria-cameras': './assets/js/entries/auditoria-cameras.ts',
  'relatorios': './assets/js/entries/relatorios.ts',
  'relatorios-alarmes': './assets/js/entries/relatorios-alarmes.ts',
}
```
3. Atualizar os PHP templates correspondentes para usar `vite_asset('nome-entry')` e `vite_entry_imports('nome-entry')` com fallback

---

### 1.3 Suporte ao Vite Dev Server [IMPORTANTE]

**Problema:** Não há detecção de dev server. CSP bloqueia `localhost:3000`.

**Ação:**
1. Modificar `inc/assets.php` para detectar dev server:
```php
function vite_is_dev(): bool {
    return file_exists(__DIR__ . '/../dist/.vite/hot');
}

function vite_dev_url(): ?string {
    $hotFile = __DIR__ . '/../dist/.vite/hot';
    if (file_exists($hotFile)) {
        $url = file_get_contents($hotFile);
        return $url !== false ? rtrim($url) : null;
    }
    return null;
}
```

2. Modificar `inc/header.php` para usar dev server quando ativo:
```php
if (vite_is_dev()):
    $devUrl = vite_dev_url() ?: 'http://localhost:3000';
?>
    <script type="module" src="<?= $devUrl ?>/@vite/client"></script>
    <script type="module" src="<?= $devUrl ?>/assets/js/main.ts"></script>
<?php else: ?>
    // ... lógica atual com manifest ...
<?php endif; ?>
```

3. Adicionar `localhost:3000` no CSP de `public/index.php`:
```php
// Na linha do CSP, adicionar:
$devCsp = vite_is_dev() ? ' http://localhost:3000' : '';
// Adicionar $devCsp em script-src, style-src, style-src-elem, connect-src
```

4. Modificar `vite.config.ts` para gerar o arquivo `hot`:
```typescript
server: {
    // ... existente ...
    origin: 'http://localhost:3000',
},
```

---

## FASE 2 — Corrigir TypeScript (516 erros → 0)

### 2.1 Declarar interfaces ausentes [ALTA]

**Problema:** `ApiResponse<T>`, `AnexoData`, `BootstrapModal` usadas sem declaração.

**Ação:** Criar `src/assets/js/types.ts`:
```typescript
export interface ApiResponse<T = unknown> {
    success: boolean;
    status?: string;
    error?: string;
    message?: string;
    data?: T;
    total?: number;
    page?: number;
    per_page?: number;
}

export interface AnexoData {
    id: number;
    equipamento_id: number;
    nome_arquivo: string;
    tamanho: number;
    tipo: string;
    data_upload: string;
}

export interface BootstrapModal {
    show(): void;
    hide(): void;
    toggle(): void;
    dispose(): void;
    handleUpdate(): void;
}

export interface BootstrapModalConstructor {
    new (el: HTMLElement, opts?: Record<string, unknown>): BootstrapModal;
    getOrCreateInstance(el: HTMLElement, opts?: Record<string, unknown>): BootstrapModal;
    getInstance(el: HTMLElement): BootstrapModal | null;
}
```

Atualizar `globals.d.ts`:
```typescript
interface Window {
    // ... existente ...
    BootstrapModal: import('../types').BootstrapModalConstructor;
}
```

---

### 2.2 Migrar window.* globals para ES module imports [ALTA — elimina 215+ erros TS2304]

**Problema:** Utilidades são atribuídas a `window.*` e consumidas como nomes soltos. Os 215 erros `TS2304` (Cannot find name) decorrem disso.

**Arquivos-fonte que atribuem a window:**
- `utils/fetchWithTimeout.ts` → `window.fetchWithTimeout`
- `shared/manutencao-utils.ts` → `window.showMessage`, `window.clearMessage`, `window.toBrDate`, etc.
- `utils/ui-utils.ts` → `window.showToast`, `window.escapeHtml`, etc.
- `utils/showSuccessModal.ts` → `window.showSuccessModal`
- `core/dashboard-core.ts` → `window.DashboardCore`
- `modules/dashboard-charts.ts` → `window.DashboardCharts`

**Ação:**
1. Adicionar `export` nas funções dos arquivos-fonte:
```typescript
// Antes:
(window as any).fetchWithTimeout = fetchWithTimeout;

// Depois:
(window as any).fetchWithTimeout = fetchWithTimeout;
export { fetchWithTimeout };
```

2. Nos arquivos consumidores, trocar nome solto por import:
```typescript
// Antes:
const response = await fetchWithTimeout(url, opts);

// Depois:
import { fetchWithTimeout } from '../utils/fetchWithTimeout';
const response = await fetchWithTimeout(url, opts);
```

3. Repetir para: `showMessage`, `clearMessage`, `toBrDate`, `toDateTimeLocalNow`, `csvEscape`, `escapeHtml`, `showToast`, `showSuccessModal`, `renderSelectOptions`, `hasSelectableOptions`, `ensureSelectOption`, `renderPaginacao`, `renderSortIndicators`, `setupHistoricoDualScroll`, `updateSelectedCount`, `updateSelectAllCheckbox`, `DashboardCore`, `DashboardCharts`

4. Manter atribuição `window.*` para compatibilidade com fallback (JS legado), mas adicionar `export` ao lado

---

### 2.3 Completar declarações Bootstrap no globals.d.ts [MÉDIA]

**Problema:** `getOrCreateInstance()` e `getInstance()` não declarados.

**Ação:** Atualizar declaração Bootstrap em `globals.d.ts`:
```typescript
interface Window {
    bootstrap: {
        Modal: {
            new (el: HTMLElement, opts?: Record<string, unknown>): {
                show(): void;
                hide(): void;
                toggle(): void;
                dispose(): void;
                handleUpdate(): void;
            };
            getOrCreateInstance(el: HTMLElement, opts?: Record<string, unknown>): {
                show(): void;
                hide(): void;
                toggle(): void;
            };
            getInstance(el: HTMLElement): {
                show(): void;
                hide(): void;
                toggle(): void;
            } | null;
        };
        Toast: {
            new (el: HTMLElement, opts?: Record<string, unknown>): {
                show(): void;
                hide(): void;
                dispose(): void;
            };
            getOrCreateInstance(el: HTMLElement, opts?: Record<string, unknown>): {
                show(): void;
                hide(): void;
            };
            getInstance(el: HTMLElement): {
                show(): void;
                hide(): void;
            } | null;
        };
        Tooltip: {
            new (el: HTMLElement, opts?: Record<string, unknown>): {
                show(): void;
                hide(): void;
                dispose(): void;
                setContent(content: { [key: string]: string }): void;
            };
            getOrCreateInstance(el: HTMLElement, opts?: Record<string, unknown>): {
                show(): void;
                hide(): void;
            };
            getInstance(el: HTMLElement): {
                show(): void;
                hide(): void;
            } | null;
        };
    };
}
```

---

### 2.4 Adicionar tipagem do Chart.js [MÉDIA]

**Ação:**
```bash
npm install -D @types/chart.js
```
Ou declarar no `globals.d.ts`:
```typescript
declare class Chart {
    constructor(ctx: CanvasRenderingContext2D, config: Record<string, unknown>);
    destroy(): void;
    update(mode?: string): void;
    data: Record<string, unknown>;
    options: Record<string, unknown>;
    config: Record<string, unknown>;
}
```

---

### 2.5 Corrigir funções duplicadas [MÉDIA]

| Arquivo | Linha | Problema |
|---------|-------|----------|
| `cadastro_cameras.ts` | 804 | `function onReady()` duplicada |
| `editar_cameras.ts` | 364 | `function onReady()` duplicada |
| `file-upload.ts` | 114 | `function escapeHtml()` duplicada com ui-utils.ts |

**Ação:**
- Combinar as funções `onReady` duplicadas em uma única implementação
- Remover `escapeHtml` de `file-upload.ts` e importar de `ui-utils.ts`

---

### 2.6 Reduzir `as any` casts (83 ocorrências) [MÉDIA]

**Principais padrões:**
- `(window as any).CSRF_TOKEN` → Declarar `CSRF_TOKEN` no `Window` interface
- `(chart as any).config.options.plugins?.centerText` → Declarar tipos do Chart.js
- `class HomeDashboard extends (window.DashboardCore as any)` → Refatorar para import + extends
- `(document as any).startViewTransition` → Declarar View Transition API

**Ação:** Declarar cada propriedade faltante no `Window` interface em `globals.d.ts` e remover os casts correspondentes.

---

### 2.7 Declarar BASE_URL global [BAIXA]

**Ação:** Adicionar em `globals.d.ts`:
```typescript
interface Window {
    BASE_URL: string;
    APP_API_BASE: string;
    CSRF_TOKEN: () => string;
    FETCH_TIMEOUT: number;
    DEBUG?: boolean;
}
```

Remover referências soltas a `BASE_URL` que causam TS2304.

---

## FASE 3 — Limpeza e Melhorias

### 3.1 Remover arquivos JS legados de public/assets/js/ [LIMPEZA]

**Arquivos a remover após migração completa:**
```
public/assets/js/main.js
public/assets/js/listar_cameras.js
public/assets/js/listar_alarmes.js
public/assets/js/cadastro_cameras.js
public/assets/js/cadastro_alarmes.js
public/assets/js/editar_cameras.js
public/assets/js/editar_alarmes.js
public/assets/js/manutencao_cameras.js
public/assets/js/manutencao_alarmes_v2.js
public/assets/js/relatorios.js
public/assets/js/relatorios_alarmes.js
public/assets/js/auditoria-cameras.js
public/assets/js/pages/home-dashboard.js
public/assets/js/utils/fetchWithTimeout.js
public/assets/js/utils/ui/theme-manager.js
```

⚠️ Só remover quando TODOS os templates PHP usarem `vite_asset()` com fallback funcional.

---

### 3.2 Integrar page-specific CSS no pipeline Vite [MELHORIA]

**Problema:** CSS de cada página é carregado de `public/assets/css/pages/` separadamente.

**Ação:** Importar page CSS nos entry points TS:
```typescript
// Ex: src/assets/js/entries/cadastro-cameras.ts
import '../../css/pages/cadastro_cameras.css';
import '../cadastro_cameras';
```

Depois, remover as tags `<link>` diretas dos templates PHP e confiar no Vite para injetar CSS.

---

### 3.3 Limpar tsconfig.json [LIMPEZA]

**Ação:**
- Remover `baseUrl` (deprecado no TS 7.0) ou adicionar `"ignoreDeprecations": "6.0"`
- Remover path alias `@/*` se não for usado, OU migrar imports para usar `@/`

---

### 3.4 Migrar testes JS para TypeScript [MELHORIA]

**Arquivos:**
- `tests/Js/showToast.test.js` → `.ts`
- `tests/Js/camera-api.test.js` → `.ts`

**Ação:**
1. Renomear para `.ts`
2. Adicionar tipos adequados
3. Atualizar `vitest.config.ts` coverage para apontar a `src/` em vez de `public/assets/js/`

---

### 3.5 Remover jQuery [MELHORIA LONGA]

**Arquivos:** `relatorios.ts`, `relatorios_alarmes.ts`

**Ação:** Substituir chamadas jQuery por vanilla JS:
- `$(selector).val()` → `(document.querySelector(selector) as HTMLInputElement).value`
- `$(selector).on(event, fn)` → `document.querySelector(selector)?.addEventListener(event, fn)`
- Remover declaração `declare var $: any`
- Remover `https://code.jquery.com` do CSP

---

### 3.6 Remover duplicação de CSS [MELHORIA]

CSS existe duplicado entre:
- `src/assets/css/` (fonte Vite)
- `public/assets/css/` (fallback)

Após migração completa, remover `public/assets/css/` e deixar apenas `src/assets/css/`.

---

## Resumo de Erros TypeScript por Tipo

| Código | Qtd | Descrição | Solução Principal |
|--------|-----|-----------|-------------------|
| TS2304 | 215 | Cannot find name | Declarar globals ou migrar para ES module imports |
| TS2769 | 49  | No overload matches | Declarar interfaces e tipos adequados |
| TS18046 | 33 | Narrowing of unknown | Adicionar type guards ou casts explícitos |
| TS2339 | 6  | Property does not exist | Completar declarações Bootstrap |
| TS2683 | 5  | Use before declaration | Reordenar ou declarar antecipadamente |
| TS2393 | 4  | Duplicate function | Mesclar funções duplicadas |
| TS2345 | 3  | Argument not assignable | Corrigir_tipos de parâmetros |
| TS18048 | 1  | Possibly undefined | Adicionar null checks |

---

## Ordem de Execução Recomendada

1. **1.1** — Corrigir caminho dos assets dist/ (sem isso, nada funciona em produção)
2. **1.2** — Adicionar 9 entry points faltantes
3. **2.1** — Declarar interfaces ausentes (ApiResponse, AnexoData, BootstrapModal)
4. **2.2** — Migrar window.* para ES module imports (elimina 215+ erros)
5. **2.3** — Completar declarações Bootstrap
6. **2.4** — Adicionar @types/chart.js
7. **2.7** — Declarar BASE_URL etc. no Window
8. **2.5** — Corrigir funções duplicadas
9. **2.6** — Reduzir `as any` casts progressivamente
10. **1.3** — Adicionar suporte ao Vite dev server
11. **3.2** — Integrar page CSS no Vite
12. **3.1** — Remover JS legados
13. **3.3–3.6** — Limpeza geral
