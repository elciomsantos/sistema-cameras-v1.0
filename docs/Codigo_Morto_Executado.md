# Código Morto — Status de Execução

**Data:** 11 de Julho de 2026 | **Referência:** Codigo_Morto_Encontrado.md

---

## Status dos Itens

| # | Item | Arquivo | Status |
|---|------|---------|--------|
| 1.1 | `auth/logout.php` — wrapper órfão | `auth/logout.php` | **[EXECUTADO]** Deletado |
| 1.2 | `auth/login_submit.php` — wrapper órfão | `auth/login_submit.php` | **[EXECUTADO]** Deletado |
| 1.3 | `resources/403.php` — template não referenciado | `resources/403.php` | **[EXECUTADO]** Deletado |
| 2.1 | `LocaisRepository` — classe inteira sem uso (459 linhas) | `src/Repository/LocaisRepository.php` | **[EXECUTADO]** Deletado |
| 3.1 | `captureSentryMessage()` — zero chamadores | `config/sentry.php:79` | **[EXECUTADO]** Removido |
| 3.2 | `verificarUsuarioSecretaria()` — zero chamadores | `inc/check_access.php:72` | **[EXECUTADO]** Removido |
| 4.1 | `CSP_ALLOW_INLINE_STYLES` — constante não lida | `config/app.php:18` | **[EXECUTADO]** Removido |
| 5.1 | `hash_password.php` — utility CLI órfão | `config/DB/private/hash_password.php` | **[MANTIDO]** Utility CLI standalone, não é código morto do app |
| 6.1 | `CLASSIFICACOES` — export TS sem importador | `classificacao-endereco.ts:14` | **[EXECUTADO]** Removido |
| 6.2 | `ApiErrorResponse` — interface sem importador | `types.ts:34` | **[EXECUTADO]** Removido |
| 6.3 | `AnexosResponse` — interface sem importador | `types.ts:335` | **[EXECUTADO]** Removido |
| 6.4 | `ModelosResponse` — interface sem importador | `types.ts:345` | **[EXECUTADO]** Removido |
| 6.5 | `QueryFilters` — interface sem importador | `types.ts:433` | **[EXECUTADO]** Removido |
| 6.6 | `AnexoDataLegacy` — interface legacy sem referência | `globals.d.ts:38` | **[EXECUTADO]** Removido |
| 7.1 | `SkeletonLoader` — classe TS instanciada mas métodos nunca chamados | `theme-manager.ts:86-168` | **[EXECUTADO]** Removido |

---

## Resumo

| Status | Itens |
|--------|-------|
| ✅ Executado | 14 |
| 🔒 Mantido (utility CLI) | 1 |
| ⏳ Pendente | 0 |
| **Total** | **15** |

---

## Validação

- PHPStan: 0 erros (baseline atualizado)
- PHPUnit: 24/24 testes OK
- TypeScript: compila sem erros
