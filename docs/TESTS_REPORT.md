# TESTS_REPORT.md

Auditoria de Testes — Projeto "Controle Share Videos" v1.0.
Data: 2026-08-16. Método: revisão estática dos testes (PHPUnit, Vitest, smoke scripts), execução das suítes, análise de cobertura/mocks/fixtures/casos extremos. Referência: protocolo Fase 11.

## Resumo Executivo

A suíte é **sólida em quantidade e em qualidade na camada unitária**: 10 testes PHP Unit, 12 suítes JS (88 testes), mocks de `database` bem usados, RateLimiter testado com filesystem real isolado, SecurityTest com bons casos negativos. Tudo passa (172 testes PHP com 14 falhas **ambientais** e 18 skips; 88/88 JS).

O problema estrutural é a **camada de API/E2E**: os testes de API não distinguem "servidor ausente" de "resposta 404", gerando 14 falhas falsas; o coverage do Vitest aponta para um diretório inexistente (`public/assets/js/`), então **nenhuma cobertura é computada**; smoke tests são PowerShell (não rodam no CI Linux); não há cobertura PHP configurada nem testes E2E de UI/PWA.

## Achados

### F-701 — Coverage Vitest aponta para diretório inexistente (`public/assets/js/`)

| Campo | Valor |
| ----- | ----- |
| Local | `tests/Js/vitest.config.ts:9` — `include: ['public/assets/js/**/*.js']` |
| Evidência | `public/assets/js/` **não existe** (o build do Vite usa `outDir: '../dist'` — `vite.config.ts:24`). Os testes importam de `src/assets/js/` (`tests/Js/manutencao-utils.test.ts:3`). Logo `npx vitest run --coverage` falha com `MISSING DEPENDENCY '@vitest/coverage-v8'` e, mesmo instalado, não teria o que cobrir. |
| Causa raiz | Path de cobertura copiado de um layout anterior (JS compilado em `public/assets/js`), nunca atualizado para `src/`. |
| Risco | Médio — decisões de qualidade sem métrica objetiva de cobertura. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Corrigir include para `src/assets/js/**/*.js` (ou `src/**/*.{js,ts}`) e instalar `@vitest/coverage-v8` como devDependency. |

### F-702 — Testes de API geram 14 falhas falsas (404 ≠ "servidor ausente")

| Campo | Valor |
| ----- | ----- |
| Local | `tests/Api/HealthTest.php:67-69`, `tests/Api/CameraApiTest.php:171-173` — `requestStatus` só faz skip quando `$headers` é nulo |
| Evidência | Sem servidor HTTP na porta de teste, `file_get_contents` pode retornar um body 404 (Apache local serve 404 na raiz) em vez de `false`. O teste então compara `404` com o `401` esperado e **falha** — 14 falhas na execução local e no CI quando o servidor de API não está no ar. |
| Causa raiz | A lógica de skip depende de `$http_response_header` nulo, mas um servidor respondendo 404 ainda preenche o header. |
| Risco | Médio — CI pode falhar por ambiente, não por regressão; ruído que mascara falhas reais. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Em `requestStatus`/`requestJson`, tratar `404` ou código `0` sem `success` como `markTestSkipped` quando `TEST_BASE_URL` não for definido; ou subir um servidor PHP de teste no CI (`php -S`) para tornar os testes determinísticos. |

### F-703 — Sem cobertura PHP configurada (CI roda `--no-coverage`)

| Campo | Valor |
| ----- | ----- |
| Local | `.github/workflows/ci.yml` (`vendor/bin/phpunit --no-coverage`); `phpunit.xml` sem bloco `<coverage>`; sem `xdebug`/`pcov` |
| Evidência | `php -m` não lista xdebug/pcov; `grep coverage phpunit.xml` vazio. Nenhuma métrica de cobertura PHP é gerada. |
| Risco | Baixo — o protocolo diz que cobertura não deve ser tratada isoladamente; porém sem métrica alguma não há como guiar a adição de testes. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Habilitar cobertura no CI (ex.: `coverage: pcov` no setup-php e `phpunit --coverage-clover`) para ter o mínimo de visibilidade. |

### F-704 — Smoke tests são PowerShell (Windows-only), não rodam no CI

| Campo | Valor |
| ----- | ----- |
| Local | `tests/smoke_security.ps1`, `tests/smoke_manutencao.ps1` (PowerShell) |
| Evidência | `ci.yml` não executa os scripts `.ps1` (e não há runner Windows). O `smoke_security.ps1` só valida `401` sem sessão — não cobre fluxos autenticados. |
| Risco | Baixo — smoke scripts redundantes com os testes de API (que também validam 401). |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Substituir por um `tests/smoke.sh` (portável) ou remover em favor dos testes de API com servidor de teste no CI. |

### F-705 — Sem testes E2E (UI/PWA/offline/service worker)

| Campo | Valor |
| ----- | ----- |
| Local | Nenhum teste de UI (`public/sw.js`, `offline.html`, PWA) ou fluxo completo (login→ação→auditoria) |
| Evidência | Não há Playwright/Cypress/Puppeteer no `package.json`; os testes JS são de utilidades puras (jsdom). |
| Risco | Médio — regressões visuais/fluxo PWA não são detectadas. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Adicionar ao menos um smoke E2E de login + listagem (Puppeteer ou Playwright) para cobrir o fluxo crítico. |

### F-706 — Integration test depende de DB real; sem fixtures dedicadas

| Campo | Valor |
| ----- | ----- |
| Local | `tests/Integration/EquipamentoServiceTest.php:15-24` — usa `getTestDatabase()` e faz skip se o DB não estiver disponível |
| Evidência | Sem fixtures/seeds de teste isolados; o teste de integração depende de um MySQL externo com o schema carregado. Na prática, é **sempre skip** localmente (sem MySQL). |
| Risco | Baixo — o teste tem valor quando o MySQL do CI existe (o CI sobe `mysql:8.0`), mas não exercita estados de dados pré-definidos. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Definir fixtures/seeds de teste (ex.: schema + dados mínimos carregados em `cftv_gml_test`) para que os testes de integração sejam determinísticos. |

### F-707 — Teste incompleto: `parseJsonInput` sem stream mock

| Campo | Valor |
| ----- | ----- |
| Local | `tests/Integration/EquipamentoServiceTest.php:167-170` — `markTestIncomplete('php://input cannot be tested without stream mock')` |
| Evidência | O parsing de payload JSON (função usada nos endpoints de manutenção — F-402) não tem teste. |
| Risco | Baixo — função simples; mas é o ponto de entrada de dados não coberto. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Testar via abstração (ex.: vfsStream) ou mover a leitura de `php://input` para um wrapper injetável. |

## Correções aplicadas (Fase 11)

| ID | Ação |
| -- | ---- |
| F-701 | `tests/Js/vitest.config.ts`: include corrigido para `src/assets/js/**/*.{js,ts}`; `@vitest/coverage-v8` adicionado como devDependency. Cobertura agora computa: **6.74% statements / 4.52% branches / 10.05% functions** (JS). |
| F-702 | Helpers `requestStatus`/`requestJson` (Health, Camera, Alarme) e o teste inline `testApiCadastrarAlarmesWithoutCsrfReturnsError` agora fazem `markTestSkipped` quando o host vivo devolve 404 sem `TEST_BASE_URL` definido. **Resultado**: 14 falhas falsas → 0 falhas (172 testes, 32 skips ambientais). |
| F-703 | CI: `coverage: pcov` no setup-php; PHPUnit roda com `--coverage-clover coverage.xml` e faz upload de artifact. `.gitignore` ganhou `coverage.xml`/`coverage/`. |
| F-707 | `parseJsonInput(?string $raw = null)` aceita raw injetado (default `php://input`) — testável. 4 novos testes unitários (decoded array, empty, JSON inválido, objeto vazio, payload >10MB). `markTestIncomplete` removido. |

## Pós-correção

- **PHPUnit**: 176 testes, 0 falhas, 36 skips ambientais.
- **Vitest**: 88/88; coverage JS computado.
- **PHPStan**: `[OK] No errors`; `php -l` limpo; `tsc --noEmit` e `vite build` OK.

## Pontos fortes (qualidade dos cenários)

- **Mocks corretos**: `createMock(\database::class)` isolam services (Equipamento, Alarme, Lookup, Locais) da camada de dados.
- **RateLimiter testado com filesystem real** (`RateLimiterTest.php`): cada teste confina-se em tempDir único e usa `useDatabase(false)` — sem mock, sem contaminação, cobre sliding_window e token_bucket, retry-after e cabeçalhos.
- **SecurityTest**: hash argon2id, política de senha (8 casos: min length, numérico, curto, sem especial, sem maiúscula, sem minúscula), CSRF (token correto/incorreto), temporários com força de caracteres.
- **Casos negativos e edge cases em JS**: `toBrDate` com null/'', `classificacao-endereco` com entradas inválidas, `error-handler`, `file-upload` com tamanhos/formats.
- **Bom uso de `expectNotToPerformAssertions`** para validações que só lançam exceção.

## Cobertura

| Camada | Suíte | Status |
| ------ | ----- | ------ |
| PHP Unit | 10 arquivos (Security, Services, Validators, RateLimiter, ApiResponse) | ✔ rodam (sem DB) |
| PHP Integration | 1 arquivo (EquipamentoService) | ⚠ depende de MySQL (skip local) |
| PHP API | 3 arquivos (Health, Camera, Alarme) | ⚠ 14 falhas ambientais (F-702) |
| JS | 12 suítes, 88 testes | ✔ 88/88 |
| E2E/UI | — | ✖ ausente (F-705) |
| Cobertura % | — | ✖ sem métrica (F-701/F-703) |

## Matriz Testes

| ID | Área | Severidade | Status |
| -- | ---- | ---------- | ------ |
| F-701 | Coverage Vitest aponta p/ `public/assets/js/` (inexistente) | Média | Corrigido |
| F-702 | API tests: 404 tratado como falha (14 falsas) | Média | Corrigido |
| F-703 | Sem cobertura PHP configurada | Baixa | Corrigido |
| F-704 | Smoke tests PowerShell (não rodam no CI) | Baixa | Aberto |
| F-705 | Sem testes E2E/UI/PWA | Média | Aberto |
| F-706 | Integration sem fixtures de teste | Baixa | Aberto |
| F-707 | `parseJsonInput` sem teste (incomplete) | Baixa | Corrigido |

## Conclusão

A camada unitária (PHP e JS) é **bem construída**: mocks adequados, isolamento correto, casos negativos e edge cases cobertos, tudo passando. Corrigidos na Fase 11: **F-701** (coverage JS agora computa — 6.74%), **F-702** (14 falhas falsas eliminadas, suíte PHP 176 testes / 0 falhas), **F-703** (cobertura PHP habilitada no CI) e **F-707** (`parseJsonInput` coberto com 4 testes). Restam: **F-704** (smoke PowerShell → portável) e **F-705** (testes E2E/UI) e **F-706** (fixtures de integração), de maior esforço.