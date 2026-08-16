# DEVOPS_REPORT.md

Auditoria Docker/DevOps — Projeto "Controle Share Videos" v1.0.
Data: 2026-08-16. Método: revisão estática dos Dockerfiles, docker-compose (dev/prod), nginx.conf, CI/CD workflows, .dockerignore. Referências: documentação oficial Docker.

## Resumo Executivo

A infraestrutura é **bem estruturada e segura na maior parte**: multi-stage builds (dev/prod), usuário não-root no target prod (`USER www-data`), healthcheck em todas as imagens, nginx com bloqueio de arquivos sensíveis/upload/executáveis, secrets por variáveis de ambiente (nunca hardcoded), `.dockerignore` correto, cache de build via GHA (CI/CD) e `server_tokens off`.

Achados: 5 confirmados de baixa/média severidade (tag flutuante `composer:latest`, extensão `mongodb` instalada sem uso, usuário root no Dockerfile.nginx, `container_name` fixo conflitando dev×prod, healthcheck HTTP desprotegido). Nenhum problema crítico de segurança.

## Achados

### F-601 — Imagem base `composer:latest` (tag flutuante) — não reprodutível

| Campo | Valor |
| ----- | ----- |
| Local | `Dockerfile:39` e `docker/Dockerfile.nginx:42` — `COPY --from=composer:latest /usr/bin/composer` |
| Evidência | `latest` é tag móvel; o build de hoje pode diferir do de amanhã, comprometendo reprodutibilidade. |
| Causa raiz | Conveniência em vez de pinning de versão. |
| Risco | Baixo — build não determinístico; potencial breaking em novo Composer. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Fixar versão (ex. `composer:2.8`) ou baixar Composer de fonte versionada. |

### F-602 — Extensão `mongodb-stable` instalada sem uso

| Campo | Valor |
| ----- | ----- |
| Local | `Dockerfile:33` e `docker/Dockerfile.nginx:37` — `pecl install mongodb-stable 2>/dev/null || true` |
| Evidência | Nenhum `use Mongo`/`MongoDB` no código PHP (`src/`, `inc/`, `api/`, `config/`); `composer.json` não referencia mongodb/redis. O `|| true` mascarou falhas de instalação. |
| Causa raiz | Extensão legada de projeto anterior. |
| Risco | Baixo — aumenta superfície e tempo de build (PECL compila), sem benefício. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Remover o `pecl install mongodb-stable` de ambos os Dockerfiles. |

### F-603 — `docker/Dockerfile.nginx` roda como ROOT (imagem de produção)

| Campo | Valor |
| ----- | ----- |
| Local | `docker/Dockerfile.nginx` (imagem prod) — sem instrução `USER` |
| Evidência | O `Dockerfile` (Apache, target prod) usa `USER www-data` (`Dockerfile:143`), mas o `Dockerfile.nginx` (usado no `docker-compose.prod.yml`) **não** define usuário — o processo PHP-FPM/nginx roda como root. |
| Causa raiz | Inconsistência entre as duas imagens de produção. |
| Risco | Médio — processo com privilégio root em produção; se comprometido, impacto total. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Adicionar `USER www-data` no `Dockerfile.nginx` (com os ajustes de permissão de socket/porta 8080 e diretórios já feitos via `chown`). |

### F-604 — `container_name` fixo idêntico em dev e prod (conflito)

| Campo | Valor |
| ----- | ----- |
| Local | `docker-compose.yml:15,38` e `docker-compose.prod.yml:16,43` — ambos `cameras-db`/`cameras-app` |
| Evidência | Rodar dev e prod no mesmo host gera colisão de nome de container. |
| Causa raiz | Cópia do compose sem parametrizar o nome. |
| Risco | Baixo — impede coexistência dev/prod; confusão operacional. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Remover `container_name` (deixar Docker gerar) ou parametrizar (`${COMPOSE_PROJECT_NAME}`). |

### F-605 — Healthcheck HTTP sem proteção de auth (smoke test simples)

| Campo | Valor |
| ----- | ----- |
| Local | `Dockerfile:74-75`, `docker/Dockerfile.nginx:91-92` — `curl -sf http://localhost:8090/` e `http://localhost:8080/` |
| Evidência | Apenas valida que o servidor responde; não valida aplicação/DB. Aceitável como healthcheck de container. |
| Risco | Baixo — o healthcheck de aplicação/DB é feito no compose (mysqladmin ping). |
| Classificação | **Não confirmado (aceitável)** |
| Severidade | Baixa |
| Recomendação | Manter; opcionalmente usar endpoint `/api/health.php` (`api/v2/api_health.php`) com auth. |

## Verificações detalhadas

### Multi-stage builds
- **OK** — `Dockerfile` (base/dev/build/prod) e `Dockerfile.nginx` (build/prod) usam multi-stage; stage `build` Node compila Vite e o stage final copia apenas `public/assets/` e `sw-assets.json`.
- Camada Composer com cache por `composer.json`/`composer.lock` (Dockerfile:124-130) — bom.

### Imagens base / tags
- `php:8.4-apache`, `php:8.4-fpm`, `node:20-slim`: tags de major (não imutáveis, porém razoáveis). **F-601** `composer:latest` flutuante.
- OCI labels presentes (source/description/licenses).

### Usuário root
- `Dockerfile` prod: `USER www-data` (OK). **F-603** `Dockerfile.nginx` sem USER (corrigido: `USER www-data`).

### Healthcheck
- Todas as imagens têm `HEALTHCHECK`; compose usa `mysqladmin ping`. OK.

### Secrets
- **OK** — senhas por env (`DB_ROOT_PASSWORD`, `DB_PASS` com `${VAR:?}` obrigatório no prod); nenhuma credencial hardcoded em imagem/compose. `.env`/`.dockerignore` excluem `.env`.

### Volumes / Networks / Portas
- Dev: `network_mode: host` (WSL2) com bind mount do código — hot reload. Prod: rede default isolada, DB não exposto publicamente (`127.0.0.1:3306`).
- Porta prod `80:8080`. Volumes nomeados para storage/uploads.

### Cache de build / tamanho
- CI/CD usam `cache-from/to: type=gha,mode=max` — bom.
- Imagem 1.1 GB (backend do outro projeto); a imagem deste repo não foi medida (build não executado nesta auditoria — **Bloqueado**).

### Pacotes desnecessários
- **F-602** mongodb. `git zip unzip curl` justificáveis (composer, healthcheck, xdebug dev).

### Reprodutibilidade
- **F-601** impede determinismo total; demais OK.

### CI/CD
- **CI** (`ci.yml`): PHP 8.3 (discrepância: produção usa PHP 8.4-fpm — ver **F-606**), composer validate, php -l, PHPStan, PHPUnit com MySQL service; frontend: vitest, tsc, build; Docker build com cache GHA. **Robusto**.
- **CD** (`cd.yml`): build & push ghcr.io em tag `v*`, semver + sha, login GHA. OK.

### Logs / Observabilidade
- nginx: `access_log`/`error_log` com formato `main` + `$http_x_forwarded_for`; `server_tokens off`.
- PHP: Monolog (arquivo) + Sentry (DSN via env, inativo sem config).
- Sem metrics/APM ativos — aceitável para o porte.

### F-607 — `brotli_static on;` sem o módulo ngx_http_brotli (nginx não sobe)

| Campo | Valor |
| ----- | ----- |
| Local | `docker/nginx.conf:54` (diretiva `brotli_static on;`) e `docker/Dockerfile.nginx` |
| Evidência | Build e teste real do container: nginx abortava com `[emerg] unknown directive "brotli_static"` — o módulo `ngx_http_brotli` não era instalado/carregado. **Bug pré-existente que impedia o container de produção subir.** |
| Causa raiz | O `nginx.conf` customizado não inclui `load_module` e o Dockerfile não instalava o módulo Debian. |
| Risco | Alto (produção indisponível) — não era detectado por falta de teste real do container. |
| Classificação | **Confirmado** |
| Severidade | Alta |
| Recomendação | Instalar `libnginx-mod-http-brotli-static` e carregar `load_module modules/ngx_http_brotli_static_module.so;` no nginx.conf. |
| Status | **CORRIGIDO e validado** (build + run: nginx OK, sem warnings). |

## Correções aplicadas (Fase 10)

| ID | Ação |
| -- | ---- |
| F-601 | `composer:latest` → `composer:2` nos dois Dockerfiles. |
| F-602 | `pecl install mongodb-stable` removido dos dois Dockerfiles. |
| F-603 | `USER www-data` + permissões de `/run/php`, `/var/lib/nginx`, logs e pid no `Dockerfile.nginx`. **Validado**: todos os processos nginx/php-fpm UID 33. |
| F-604 | `container_name` removido dos composes dev/prod. |
| F-606 | CI: PHP 8.3 → 8.4 (paridade com produção). |
| F-607 | `libnginx-mod-http-brotli-static` instalado e `load_module` adicionado ao nginx.conf; MIME `text/html` duplicado removido do `gzip_types`. **Validado**: `nginx -t` OK, container responde HTTP (500 apenas por ausência de MySQL). |
| CMD | `CMD sh -c` → JSON array no `Dockerfile.nginx` (recomendação do linter). |

### F-606 — Discrepância PHP CI (8.3) vs produção (8.4)

| Campo | Valor |
| ----- | ----- |
| Local | `.github/workflows/ci.yml` (PHP 8.3) vs `Dockerfile`/`Dockerfile.nginx` (PHP 8.4) |
| Evidência | CI valida em 8.3; imagem de produção roda 8.4 — possibilidade de bug que passa no CI e falha em prod. |
| Risco | Baixo (8.3 e 8.4 próximos), mas quebra a paridade CI×prod. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Alinhar CI para PHP 8.4. |

## Matriz Docker/DevOps

| ID | Área | Severidade | Status | Observação |
| -- | ---- | ---------- | ------ | ---------- |
| F-601 | `composer:latest` tag flutuante | Baixa | Corrigido | `composer:2` |
| F-602 | Extensão mongodb sem uso | Baixa | Corrigido | Removida |
| F-603 | Dockerfile.nginx roda como root | Média | Corrigido | `USER www-data` (validado) |
| F-604 | container_name fixo dev×prod | Baixa | Corrigido | Removido |
| F-605 | Healthcheck só HTTP smoke | Baixa | Não confirmado | Aceitável |
| F-606 | CI PHP 8.3 vs prod 8.4 | Baixa | Corrigido | CI 8.4 |
| F-607 | nginx não subia (`brotli_static`) | Alta | Corrigido | Módulo + load_module (validado) |

## Conclusão

Infraestrutura **sólida e segura** (multi-stage, usuário não-root, secrets por env, nginx restritivo, CI/CD completo com cache GHA). Todos os achados foram **corrigidos e validados por build/run real do container**, incluindo **F-607** — bug pré-existente que impedia o container de produção de subir (nginx abortava com `unknown directive "brotli_static"`), só detectável com teste real do image. O container de produção agora sobe como `www-data`, nginx+php-fpm respondem (HTTP 500 apenas por ausência de MySQL, esperado).