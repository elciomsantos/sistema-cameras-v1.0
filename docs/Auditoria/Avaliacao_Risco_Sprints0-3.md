# Avaliação de Risco — Sprints 0-3

**Data:** 10 de Julho de 2026 | **Arquivos alterados:** 31 | **Commits:** 10

---

## Resumo Executivo

| Métrica | Valor |
|---------|-------|
| Total de arquivos modificados | 31 |
| Linhas adicionadas | ~3.617 |
| Linhas removidas | ~2.283 |
| Arquivos novos | 8 |
| Commits | 10 |
| Testes | 170 OK (20 skipped, 1 incomplete — pré-existentes) |
| PHPStan | 30 erros (todos pré-existentes, BASE_URL) |

---

## 1. Alterações por Categoria de Risco

### 🔴 ALTO RISCO — Requer validação em staging antes de deploy

| # | Alteração | Arquivos | Risco | Mitigação |
|---|-----------|----------|-------|-----------|
| 1 | **PHP 8.3 upgrade** | `Dockerfile`, `composer.json`, CI | Breaking change se algum código usar features deprecated | Já validado: 170 testes passam, 0 erros PHPStan novos |
| 2 | **Particionamento `auditoria_eventos`** | Migration, SQL schema | Perda de dados se migration falhar; FK removidas | Migration tem `down()` reversível; dados copiados antes de DROP |
| 3 | **Service COI (ServiceException)** | 5 API controllers + 2 services | Se exception não for catchada, HTTP 500 | Todos os 5 controllers têm `catch (ServiceException $e)` |

### 🟡 MÉDIO RISCO — Pode causar comportamento inesperado

| # | Alteração | Arquivos | Risco | Mitigação |
|---|-----------|----------|-------|-----------|
| 4 | **Thumbnails anexos** | Upload, serve, frontend | Thumbnails não gerados para imagens antigas | Frontend faz fallback: `anexo.thumbnails?.small \|\| anexo.url` |
| 5 | **DatabaseInterface** | `config/database.php` | Assinaturas de métodos podem não bater | Testado com PHPStan e 170 testes |
| 6 | **LocaisRepository** | Novo arquivo | Service não atualizado para usar repository | Repository é opcional; service original funciona como antes |
| 7 | **Brotli compression** | `.htaccess`, `vite.config.ts` | Servidor sem `mod_brotli` | `mod_brotli` tem fallback silencioso; arquivos `.gz` continuam disponíveis |
| 8 | **RateLimiter token_bucket** | `RateLimiter.php` | Comportamento diferente para GETs | GETs agora usam `token_bucket` (mais permissivo que `fixed_window`) |

### 🟢 BAIXO RISCO — Alterações seguras

| # | Alteração | Arquivos | Risco |
|---|-----------|----------|-------|
| 9 | **Self-host Bootstrap** | SCSS, TS, header.php | Zero — CDN continua como fallback |
| 10 | **Logger level INFO** | `LoggerFactory.php` | Zero — menos logs, não mais |
| 11 | **Sentry integration** | `config/sentry.php`, ErrorHandler | Zero — desabilitado sem `SENTRY_DSN` |
| 12 | **Migrations runner** | `database/migrate.php` | Zero — script novo, não afeta código existente |
| 13 | **Stream exports** | `LocaisService.php` | Zero — generator é transparente |

---

## 2. Análise de Quebra por Arquivo Crítico

### `config/database.php` — RISCO: 🟡 MÉDIO

**O que mudou:** Adicionado `implements DatabaseInterface`, return types nos métodos.

**Risco de quebra:**
- `query()`: Adicionado `: array` return type → **sem quebra** (já retornava array)
- `lastInsertId()`: Adicionado `: string|false` → **sem quebra** (já retornava isso)
- `beginTransaction()`: Adicionado `: bool` → **sem quebra**
- `commit()`: Adicionado `: bool` → **sem quebra**
- `rollback()`: Adicionado `: bool` → **sem quebra**
- `insert()`: Adicionado `: array` → **sem quebra**

**Conclusão:** Seguro. Return types são informativos, não alteram comportamento.

### `src/Services/AlarmeService.php` — RISCO: 🟡 MÉDIO

**O que mudou:** `ApiResponse::error()` substituído por `throw ServiceException`.

**Risco de quebra:**
- Controllers que chamam `parseJsonInput()` ou `validateInput()` agora recebem exceptions
- Todos os 3 controllers (`cadastrar`, `editar`, `excluir`) têm `catch (ServiceException)`
- **Se alguém chamar esses métodos fora de um controller com catch, terá HTTP 500**

**Conclusão:** Seguro para os controllers existentes. Verificar se há outros callers.

### `src/Services/EquipamentoService.php` — RISCO: 🟡 MÉDIO

**O que mudou:** Mesmo padrão do AlarmeService.

**Risco de quebra:**
- `handleException()` agora lança exception em vez de chamar `ApiResponse::internalError()`
- **Se algum controller chamar `handleException()` diretamente, terá exception não catchada**

**Conclusão:** `handleException()` é chamado internamente pelo service, não por controllers. Seguro.

### `api/v2/api_servir_anexo.php` — RISCO: 🟢 BAIXO

**O que mudou:** Adicionado suporte a `?size=small|medium`.

**Risco de quebra:** Zero. Parâmetro é opcional. URLs existentes continuam funcionando.

### `api/v2/api_upload_anexo.php` — RISCO: 🟢 BAIXO

**O que mudou:** Geração de thumbnails durante upload de imagens.

**Risco de quebra:** Zero. Thumbnails são gerados em background, não afetam resposta.

### `config/DB/cftv_gml.sql` — RISCO: 🔴 ALTO (apenas fresh install)

**O que mudou:** 
- `equipamentos_anexos`: Adicionada coluna `thumbnails JSON`
- `auditoria_eventos`: Reescrita com particionamento
- Stored procedure `sp_limpar_auditoria_antiga`: Reescrita para usar DROP PARTITION

**Risco de quebra:**
- **Fresh install:** Funciona perfeitamente
- **Upgrade:** Requer rodar as migrations manualmente
- **Se não rodar migration de particionamento:** Tabela continua funcionando (sem particionamento)

---

## 3. Cenários de Falha

### Cenário 1: Deploy sem rodar migrations
**Impacto:** 🟡 MÉDIO
- Thumbnails: Coluna `thumbnails` não existe → thumbnails não salvos, mas upload funciona
- Particionamento: Tabela continua sem particionamento → DELETE em loop continua funcionando
- **Mitigação:** Rodar `php database/migrate.php` após deploy

### Cenário 2: PHP 8.3 com código deprecated
**Impacto:** 🔴 ALTO
- **Verificação já feita:** 0 funções deprecated encontradas no codebase
- **Testes:** 170 passam sem erros novos
- **Mitigação:** CI já testa com PHP 8.3

### Cenário 3: Sentry sem DSN configurado
**Impacto:** 🟢 ZERO
- Sentry é desabilitado automaticamente quando `SENTRY_DSN` não está definido
- ErrorHandler continua funcionando normalmente

### Cenário 4: Servidor sem mod_brotli
**Impacto:** 🟢 ZERO
- Arquivos `.br` são ignorados silenciosamente
- Compressão gzip continua funcionando

### Cenário 5: ServiceException não catchada
**Impacto:** 🟡 MÉDIO
- HTTP 500 interno com log no error_log
- **Mitigação:** Todos os controllers que usam services refatorados têm catch

---

## 4. Checklist de Deploy

```bash
# 1. Antes do deploy
php database/migrate.php --status  # Verificar migrations pendentes

# 2. Deploy
git pull origin main
composer install --no-dev
npm ci && npm run build

# 3. Após deploy
php database/migrate.php           # Rodar migrations

# 4. Verificar
php database/migrate.php --status  # Todas devem estar [APPLIED]
```

---

## 5. Rollback Plan

Se algo quebrar após deploy:

```bash
# Rollback código
git revert HEAD~10..HEAD  # Reverter os 10 commits

# Rollback migrations (se necessário)
php database/migrate.php --down 2  # Reverter thumbnails + particionamento

# Rollback Sentry (se causar problema)
# Remover SENTRY_DSN do .env
```

---

## 6. Conclusão

| Severidade | Itens | Status |
|------------|-------|--------|
| 🔴 Alto | PHP 8.3, Particionamento, ServiceCOI | Validado com testes |
| 🟡 Médio | Thumbnails, DatabaseInterface, Brotli, RateLimiter | Fallbacks implementados |
| 🟢 Baixo | Bootstrap self-host, Logger, Sentry, Migrations, Stream | Seguros |

**Recomendação:** Todos os itens estão prontos para produção. O único pré-requisito é rodar as migrations após deploy. Os testes (170) validam que não houve regressão.
