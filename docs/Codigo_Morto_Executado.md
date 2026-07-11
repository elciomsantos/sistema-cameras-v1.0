# Código Morto — Status de Execução

**Data:** 11 de Julho de 2026 | **Referência:** Codigo_Morto_Encontrado.md

---

## Status dos Itens

| # | Item | Arquivo | Status |
|---|------|---------|--------|
| 1.1 | `auth/logout.php` — wrapper órfão | `auth/logout.php` | **[PENDENTE]** |
| 1.2 | `auth/login_submit.php` — wrapper órfão | `auth/login_submit.php` | **[PENDENTE]** |
| 1.3 | `resources/403.php` — template não referenciado | `resources/403.php` | **[PENDENTE]** |
| 2.1 | `LocaisRepository` — classe inteira sem uso (459 linhas) | `src/Repository/LocaisRepository.php` | **[PENDENTE]** |
| 3.1 | `captureSentryMessage()` — zero chamadores | `config/sentry.php:79` | **[PENDENTE]** |
| 3.2 | `verificarUsuarioSecretaria()` — zero chamadores | `inc/check_access.php:72` | **[PENDENTE]** |
| 4.1 | `CSP_ALLOW_INLINE_STYLES` — constante não lida | `config/app.php:18` | **[PENDENTE]** |
| 5.1 | `hash_password.php` — utility CLI órfão | `config/DB/private/hash_password.php` | **[PENDENTE]** |
| 6.1 | `CLASSIFICACOES` — export TS sem importador | `classificacao-endereco.ts:14` | **[PENDENTE]** |
| 6.2 | `ApiErrorResponse` — interface sem importador | `types.ts:34` | **[PENDENTE]** |
| 6.3 | `AnexosResponse` — interface sem importador | `types.ts:335` | **[PENDENTE]** |
| 6.4 | `ModelosResponse` — interface sem importador | `types.ts:345` | **[PENDENTE]** |
| 6.5 | `QueryFilters` — interface sem importador | `types.ts:433` | **[PENDENTE]** |
| 6.6 | `AnexoDataLegacy` — interface legacy sem referência | `globals.d.ts:38` | **[PENDENTE]** |
| 7.1 | `SkeletonLoader` — classe TS instanciada mas métodos nunca chamados | `theme-manager.ts:86-168` | **[PENDENTE]** |

---

## Resumo

| Status | Itens |
|--------|-------|
| ✅ Executado | 0 |
| ⏳ Pendente | 15 |
| **Total** | **15** |

---

## Notas

- Itens 1.1-1.3: Deletar arquivos (3 arquivos, ~18 linhas)
- Item 2.1: Deletar classe inteira (459 linhas)
- Itens 3.1-3.2: Remover funções (2 funções, ~30 linhas)
- Item 4.1: Remover constante (2 linhas)
- Item 5.1: Manter como utility CLI (não é código morto do app, é ferramenta standalone)
- Itens 6.1-6.6: Remover exports/types (6 itens, ~80 linhas)
- Item 7.1: Remover classe e instanciamento (83 linhas)
