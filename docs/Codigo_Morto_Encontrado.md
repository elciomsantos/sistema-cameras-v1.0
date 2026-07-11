# Código Morto Encontrado

**Data:** 11 de Julho de 2026 | **Análise completa do projeto**

---

## 1. Arquivos PHP não referenciados

| # | Arquivo | Linhas | Descrição | Motivo |
|---|---------|--------|-----------|--------|
| 1.1 | `auth/logout.php` | 1-2 | Wrapper `require_once action_logout.php` | Rota em `index.php` aponta direto para `action_logout.php` |
| 1.2 | `auth/login_submit.php` | 1-2 | Wrapper `require_once action_login_submit.php` | Rota em `index.php` aponta direto para `action_login_submit.php` |
| 1.3 | `resources/403.php` | 1-14 | Template HTML "Acesso Negado" | Não está no whitelist `inc/pages.php`; sistema usa `accounts/nao_autorizado.php` |

## 2. Classe PHP inteira sem uso

| # | Arquivo | Linhas | Descrição | Motivo |
|---|---------|--------|-----------|--------|
| 2.1 | `src/Repository/LocaisRepository.php` | 1-459 | Classe completa (15 métodos públicos) | Nunca instanciada; `LocaisService` faz todas as operações de banco direto |

## 3. Funções PHP sem chamadores

| # | Arquivo:linha | Função | Descrição | Motivo |
|---|---------------|--------|-----------|--------|
| 3.1 | `config/sentry.php:79` | `captureSentryMessage()` | Captura de mensagens Sentry | Irmã `captureSentryException()` é usada; esta nunca chamada |
| 3.2 | `inc/check_access.php:72` | `verificarUsuarioSecretaria()` | Verificação de secretaria do usuário | As 4 funções irmãs (`verificarEscopoLocal/Equipamento/Alarme/Anexo`) são usadas; esta nunca chamada |

## 4. Constante PHP sem uso

| # | Arquivo:linha | Constante | Descrição | Motivo |
|---|---------------|-----------|-----------|--------|
| 4.1 | `config/app.php:18` | `CSP_ALLOW_INLINE_STYLES` | Flag de CSP para inline styles | Definida mas nunca lida; CSP usa `style-src-attr 'none'` hardcoded |

## 5. Arquivo de configuração órfão

| # | Arquivo | Descrição | Motivo |
|---|---------|-----------|--------|
| 5.1 | `config/DB/private/hash_password.php` | Utility CLI para hash de senhas com Argon2id | Nunca carregado pelo app; é script standalone sem integração |

## 6. TypeScript exports sem importadores

| # | Arquivo:linha | Export | Descrição | Motivo |
|---|---------------|--------|-----------|--------|
| 6.1 | `classificacao-endereco.ts:14` | `CLASSIFICACOES` | Array de 10 classificações de endereço | Apenas `CLASSIFICACAO_MAP` do mesmo arquivo é importado |
| 6.2 | `types.ts:34` | `ApiErrorResponse` | Interface de erro da API | Nunca importada em nenhum `.ts` |
| 6.3 | `types.ts:335` | `AnexosResponse` | Interface de resposta de anexos | Nunca importada em nenhum `.ts` |
| 6.4 | `types.ts:345` | `ModelosResponse` | Interface de resposta de modelos | Nunca importada em nenhum `.ts` |
| 6.5 | `types.ts:433` | `QueryFilters` | Interface de filtros de query | Nunca importada em nenhum `.ts` |
| 6.6 | `globals.d.ts:38` | `AnexoDataLegacy` | Interface legacy de anexo | Declarada globalmente mas nunca referenciada |

## 7. Classe TS sem uso

| # | Arquivo:linha | Classe | Descrição | Motivo |
|---|---------------|--------|-----------|--------|
| 7.1 | `theme-manager.ts:86-168` | `SkeletonLoader` | Loader de skeleton UI | Instanciada em `initThemeFeatures()` mas métodos (`create`/`show`/`remove`) nunca chamados |

---

## Resumo

| Categoria | Itens | Linhas removíveis |
|-----------|-------|-------------------|
| Arquivos PHP não referenciados | 3 | ~18 |
| Classe PHP sem uso | 1 | ~459 |
| Funções PHP sem chamadores | 2 | ~30 |
| Constante PHP sem uso | 1 | ~2 |
| Arquivo config órfão | 1 | ~18 |
| TS exports sem importadores | 6 | ~80 |
| Classe TS sem uso | 1 | ~83 |
| **Total** | **15** | **~690** |
