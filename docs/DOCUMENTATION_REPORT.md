# DOCUMENTATION_REPORT.md

Auditoria de Documentação — Projeto "Controle Share Videos" v1.0.
Data: 2026-08-16. Método: revisão estática de README.md, docs/SETUP.md, docs/DEPLOY.md, docs/SECURITY_ENV.md, public/README.md, .env.example e endpoint Swagger, comparando com o código real (composer.json, Dockerfiles, workflows, api/v2, config/). Referência: protocolo Fase 12.

## Resumo Executivo

A documentação é **extensa e de boa qualidade** (README de 517 linhas com funcionalidades, instalação, roteamento, API, segurança, performance, migrations; SETUP e DEPLOY dedicados). Porém há **divergências reais com o código**: a spec OpenAPI referenciada pelo Swagger UI **não existe** (F-801), a variável `DB_PORT` é documentada/configurada mas **ignorada pelo código** (F-802), versões de PHP divergem entre docs (F-803), números de testes desatualizados (F-804), DEPLOY descreve fluxo SSH inexistente (F-805), variável de senha root divergente (F-806) e **9 endpoints da API não documentados** (F-807).

## Achados

### F-801 — `docs/openapi.yaml` inexistente — Swagger UI quebrado

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/swagger.php:41,45` (lê `docs/openapi.yaml`); `docs/openapi.yaml` **ausente** |
| Evidência | `ls docs/openapi.yaml` → not found. O endpoint `/api/v2/swagger-spec` retorna `404 "OpenAPI specification not found"`; a UI Swagger (`public/assets/swagger/` com bundle+css) fica sem spec e é inútil. |
| Causa raiz | A spec foi planejada mas nunca versionada; `public/assets/swagger/` contém apenas os assets estáticos. |
| Risco | Médio — a documentação interativa da API (única fonte estruturada além do README) está quebrada; um desenvolvedor novo não tem referência dos contratos. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Criar `docs/openapi.yaml` cobrindo os endpoints reais (ver F-807) ou remover o endpoint Swagger até a spec existir. |

### F-802 — `DB_PORT` documentado/configurado mas ignorado pelo código

| Campo | Valor |
| ----- | ----- |
| Local | `config/database.php:43` — DSN `mysql:host=' . DB_HOST . ';dbname=' . DB_NAME` (sem porta) |
| Evidência | `grep -rn DB_PORT --include="*.php" .` retorna nada em código. Porém `docker-compose.yml:43`, `docker-compose.prod.yml:48` e docs definem `DB_PORT: 3306`. O PDO assume 3306 implícito; se o MySQL rodar em outra porta, a aplicação não conecta. |
| Causa raiz | A variável foi adicionada ao compose/env mas o DSN nunca passou a incluí-la. |
| Risco | Baixo (3306 é o padrão), mas é armadilha de config. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Incluir `;port=' . DB_PORT` no DSN (com default 3306) ou remover a variável dos composes. |

### F-803 — Versões de PHP divergentes entre docs

| Campo | Valor |
| ----- | ----- |
| Local | `README.md:56` (PHP 8.2+), `docs/SETUP.md:30` (PHP 8.3+), `composer.json:7` (`>=8.4.1`), Dockerfiles (php:8.4) |
| Evidência | Três versões mínimas diferentes documentadas; a real é 8.4. |
| Risco | Baixo — confunde instalação; quem seguir o README (8.2) pode falhar. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Padronizar "PHP 8.4+" em todos os docs. |

### F-804 — Números de testes desatualizados no README

| Campo | Valor |
| ----- | ----- |
| Local | `README.md:235` ("80 testes"), `:370` ("170+ testes, 0 erros PHPStan, 20 skipped, 1 incomplete"), `:372` (nota "28 erros Trait ParsesInput") |
| Evidência | Estado atual: **176 testes, 0 falhas, 36 skips, 0 incomplete**; PHPStan "No errors"; F-707 corrigiu o ParsesInput. |
| Risco | Baixo — métricas erradas desatualizam confiança no estado do projeto. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Atualizar os números e remover a nota obsoleta. |

### F-805 — DEPLOY.md descreve fluxo SSH/VPS que o CD não implementa

| Campo | Valor |
| ----- | ----- |
| Local | `docs/DEPLOY.md:3-7,89-93` (job `deploy` com SSH no VPS, pull + restart); `.github/workflows/cd.yml` (só build & push ghcr) |
| Evidência | O `cd.yml` tem apenas o job `deploy` que **builda e publica a imagem** no GHCR — não há job de SSH, secrets `SSH_HOST`/`SSH_USER`/`SSH_PRIVATE_KEY` ou `docker compose pull && up -d` em servidor remoto. A doc descreve um fluxo de deploy que não existe. |
| Risco | Médio — operador seguiria o guia e esperaria deploy automático que não acontece. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Corrigir o DEPLOY.md para refletir o CD real (push de imagem ao GHCR) OU implementar o job de SSH. |

### F-806 — SECURITY_ENV.md usa `MYSQL_ROOT_PASS` (nome errado)

| Campo | Valor |
| ----- | ----- |
| Local | `docs/SECURITY_ENV.md:45,51,60,65,97` (`MYSQL_ROOT_PASS`); `docker-compose.prod.yml:19` (`DB_ROOT_PASSWORD`) |
| Evidência | O compose espera `DB_ROOT_PASSWORD` (`${DB_ROOT_PASSWORD:?...}`); a doc usa `MYSQL_ROOT_PASS`. Seguir a doc e definir a senha com o nome errado faz o compose falhar na interpolação. |
| Risco | Baixo — confusão de setup. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Renomear para `DB_ROOT_PASSWORD` na doc. |

### F-807 — 9 endpoints reais ausentes na doc de API (README + openapi)

| Campo | Valor |
| ----- | ----- |
| Local | `README.md:267-314` (seção API); `api/v2/` tem 32 arquivos |
| Evidência | Não documentados: `api_auditoria_alarmes`, `api_auditoria_cameras`, `api_excluir_alarme`, `api_exportar_locais`, `api_manutencao_utils`, `api_notificacoes`, `limpar_sessoes_orfas`, `swagger`. (README lista ~26; código tem 32 incl. swagger.) |
| Risco | Baixo — desenvolvedor novo desconhece endpoints de auditoria/notificações/export. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Documentar os endpoints faltantes no README e na spec OpenAPI (quando criada). |

## Correções aplicadas (Fase 12)

| ID | Ação |
| -- | ---- |
| F-802 | `config/database.php`: DSN agora inclui `;port=' . DB_PORT` (default 3306). `.env.example`: adicionadas `DB_PORT` e `DB_SSL_CA`. |
| F-803 | README (8.2→8.4) e SETUP (8.3→8.4) alinhados com `composer.json`/Dockerfiles. |
| F-804 | README: "80 testes"→"176", "170+...20 skipped, 1 incomplete"→"176+, 0 falhas", nota obsoleta do ParsesInput substituída por nota sobre skips ambientais. |
| F-805 | DEPLOY.md reescrito para o fluxo real (build & push ghcr em tag `v*`; atualização manual via `pull`+`up -d`); removidos secrets SSH inexistentes e seção de "deploy automático". |
| F-806 | SECURITY_ENV.md: `MYSQL_ROOT_PASS` → `DB_ROOT_PASSWORD` (compose) em comandos, secrets e tabela. |
| F-807 | README: documentados `api_auditoria_alarmes/cameras`, `api_excluir_alarme`, `api_exportar_locais`, `api_manutencao_utils`, `api_notificacoes`, `api_limpar_sessoes_orfas`, `api/v2/swagger(-spec)`. |
| — | URLs do repositório (README, DEPLOY, labels OCI) alinhados ao remote real `elciomsantos`. |

## Pós-correção

- `php -l config/database.php` OK; PHPUnit 176 testes / 0 falhas; PHPStan `[OK] No errors`; composes dev/prod OK.

## Verificações OK

- **README funcionalidades/instalação**: cobertura boa (Windows/Linux, build frontend, servidor).
- **`.env.example`**: completo para as variáveis core (CAMERAS_ENV, DB_*, APP_*, sessão, CSP, Sentry, VITE_API_URL). Faltam apenas `DB_PORT` e `DB_SSL_CA` (correlatos ao F-802).
- **SETUP.md**: estrutura de config externa, pré-requisitos, Composer/npm, troubleshooting básico — bom.
- **public/README.md**: orientação de DocumentRoot e cache estático — correto.
- **Migrations README**: tabela consolidada em `2026_07_13_110000` já corrigida na Fase 8 (F-407).
- **Testes README**: comandos corretos.
- **Estrutura do projeto**: dirs principais (`accounts/`, `api/`, `auth/`, `config/`, `inc/`, `resources/`, `src/`, `public/`, `tests/`, `scripts/`) correspondem à realidade.

## Matriz Documentação

| ID | Área | Severidade | Status |
| -- | ---- | ---------- | ------ |
| F-801 | openapi.yaml ausente → Swagger 404 | Média | Aberto |
| F-802 | DB_PORT ignorado pelo DSN | Baixa | Corrigido |
| F-803 | Versão PHP divergente (8.2/8.3/8.4) | Baixa | Corrigido |
| F-804 | Números de testes desatualizados | Baixa | Corrigido |
| F-805 | DEPLOY descreve SSH inexistente | Média | Corrigido |
| F-806 | `MYSQL_ROOT_PASS` vs `DB_ROOT_PASSWORD` | Baixa | Corrigido |
| F-807 | 9 endpoints não documentados | Baixa | Corrigido |

## Conclusão

Documentação **abrangente e útil**, com divergências corrigidas nesta fase: porta do banco agora respeitada pelo código (F-802), versões PHP unificadas em 8.4 (F-803), métricas de teste atualizadas (F-804), guia de deploy alinhado ao CD real (F-805), variável de senha root corrigida (F-806) e 9 endpoints documentados (F-807). URLs de repositório alinhados ao remote real. Resta **F-801** (spec OpenAPI) — decisão de escopo para a Fase 13.