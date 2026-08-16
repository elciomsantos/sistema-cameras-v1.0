# QUALITY_REPORT.md

Auditoria de Qualidade de Código — Projeto "Controle Share Videos" v1.0.
Data: 2026-08-16. Método: PHP_CodeSniffer (PSR-12 + métricas), PHPStan (nível 5), análise estática manual (duplicação, código morto, coesão/acoplamento).

## Resumo Executivo

O código tem **boa organização geral** (separação Services/inc/Api, validação centralizada via `RequestValidator`/`FormValidator`, rate limiting reutilizável). A principal dívida de qualidade é a **duplicação massiva entre os endpoints gêmeos de manutenção** (`api_manutencao_cameras.php` × `api_manutencao_alarmes.php`) e a **alta complexidade ciclomática** em serviços de normalização/validação de payload. Também há **código morto** e **conformidade PSR-12 parcial** (grande parte autocorrigível com `phpcbf`).

## Achados

### F-401 — Duplicação massiva entre `api_manutencao_cameras.php` e `api_manutencao_alarmes.php` (DRY)

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/api_manutencao_cameras.php` (611 linhas) e `api/v2/api_manutencao_alarmes.php` (557 linhas) |
| Evidência | Ambos declaram `loadManutencaoPayload()` idêntico; blocos POST espelhados: 11 `SELECT` cada, fluxo `create_os`/`update_os`/`delete_os` com mesmas validações (usuário, escopo, `status`/`procedimento` MANUTENCAO, `numero_os` regex, datas). Normalização das diferenças (nomes de tabela/variáveis) deixa ~94 de 131 linhas do bloco POST idênticas. A extração atual (`api_manutencao_utils.php`) só removeu 2 helpers. |
| Causa raiz | As duas entidades (equipamentos × central_alarmes) têm fluxos de manutenção funcionalmente idênticos; a extração de helpers foi incompleta. |
| Risco | Médio — qualquer correção de bug ou política (ex. validação, escopo) precisa ser feita em **2 lugares**, risco de divergência. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Consolidar o fluxo de manutenção em um service genérico (`ManutencaoService`) parametrizado por entidade (tabela/alias/campos), reduzindo ~500 linhas duplicadas. Benefício técnico real: correção única e testável. |

### F-402 — Código morto: `getInputPayload()` nunca utilizado

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/api_manutencao_utils.php:6-22` |
| Evidência | `grep` do nome em todo o repo: só a definição; `api_manutencao_cameras.php:341` e `api_manutencao_alarmes.php` fazem o parse do body inline (`file_get_contents('php://input')`). |
| Causa raiz | Helper criado durante extração mas os endpoints continuaram com o código inline original. |
| Risco | Baixo — confusão de manutenção (função aparentemente disponível, mas não usada). |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Remover a função (e usar `getInputPayload()` no lugar do parse inline) ou remover a função morta. |

### F-403 — Classes `src/` sem namespace (conformidade PSR-4)

| Campo | Valor |
| ----- | ----- |
| Local | 13 classes em `src/` (ex.: `src/Services/AlarmeService.php`, `src/Api/RateLimiter.php`, `src/Api/RequestValidator.php`, `src/Exceptions/AuthException.php`) |
| Evidência | phpcs PSR1 `MissingNamespace` em 13 arquivos; `composer.json` usa `classmap: ["src/"]` (funciona, mas sem namespace). |
| Causa raiz | Projeto evoluiu de script procedural para classes sem adotar namespaces. |
| Risco | Baixo hoje (classmap resolve), mas impede autoload PSR-4, colisões futuras e dificulta testes/mocks por FQCN. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Refatoração de namespaces (`App\...`) + autoload PSR-4. Aplicável em janela própria; hoje o custo/benefício é baixo. |

### F-404 — Alta complexidade ciclomática em métodos de normalização/validação

| Campo | Valor |
| ----- | ----- |
| Local | `src/Services/EquipamentoService.php:18` `extractCommonData` (complexidade **65**); `src/Services/AlarmeService.php:27` `validateInput` (**63**); `api/bootstrap-api.php:4` (**35**); `api/v2/api_manutencao_cameras.php:5` (**34**); `api/v2/api_manutencao_alarmes.php` (**32**); `inc/single_session.php` (**21**); `src/Services/LocaisService.php:207` `create` (23); `inc/security.php` (18) |
| Evidência | phpcs `Generic.Metrics.CyclomaticComplexity` (limite 20): 6 funções acima do limite absoluto; mais 5 warnings acima de 10. |
| Causa raiz | `extractCommonData`/`validateInput` encadeiam dezenas de ternários/`max()`/`?:` por campo do payload; endpoints de manutenção e bootstrap acumulam `if` de validação + fallback em bloco único. |
| Risco | Médio — métodos de 45-114 linhas com muitos ramos são difíceis de testar isoladamente e propensos a regressão. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Quebrar `extractCommonData` em helpers por grupo de campos (identificação, endereço, link) e `validateInput` em regras declarativas (já existe `RequestValidator`); para endpoints, usar os validators existentes em vez de `if` sequenciais. |

### F-405 — `loadManutencaoPayload()` com 10 parâmetros

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/api_manutencao_cameras.php:5` e `api/v2/api_manutencao_alarmes.php` (assinatura idêntica) |
| Evidência | Assinatura com 10 parâmetros escalares (dados de filtro + página/ordenação). |
| Causa raiz | Payload de listagem montado por parâmetros posicionais. |
| Risco | Baixo — legibilidade/call-site frágil. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Substituir por array de filtros (`array $filters`) ou objeto DTO. |

### F-406 — Conformidade PSR-12 parcial (226 erros + 187 warnings; 212 autocorrigíveis)

| Campo | Valor |
| ----- | ----- |
| Local | 60 arquivos em `src/`, `inc/`, `api/` |
| Evidência | phpcs PSR-12: 226 erros + 187 warnings. Principais: `LineLength` (177), `InlineControlStructure` (57), `ControlSignature.SpaceAfterCloseParenthesis` (32), `ControlStructureSpacing` (27), `ScopeClosingBrace` (17+11), `SuperfluousWhitespace` (16), `MissingNamespace` (13), `SideEffects.FoundWithSymbols` (10). 212 violações autocorrigíveis via `phpcbf`. |
| Causa raiz | Código evoluiu sem tooling de estilo; `inc/` e `api/` procedurais têm convenção própria (controle inline, linhas longas). |
| Risco | Baixo — estilo; 177 das 187 warnings são linhas > 120 chars (apenas legibilidade). |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Rodar `phpcbf` para as 212 autocorrigíveis; decidir política de `LineLength` (ex. `max 140`) para as linhas longas restantes. |

### F-407 — README.md com tabela de migrations desatualizada

| Campo | Valor |
| ----- | ----- |
| Local | `README.md:403-411` |
| Evidência | A tabela lista `2026_07_10_*`, `2026_07_11_100000`, `2026_07_12_100000`, `2026_07_13_100000`, `2026_07_14_100000` como "incorporadas", mas o commit `de909e9` removeu esses arquivos e a única migration PHP restante é `2026_07_13_110000`. |
| Causa raiz | Documentação não acompanhou a reorganização do commit `de909e9`. |
| Risco | Baixo — documentação enganosa para quem opera migrations. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Atualizar a tabela: remover entradas obsoletas, registrar `2026_07_13_110000` e explicar que as demais foram consolidadas no schema. |

## Verificações detalhadas

### Clean Code / SOLID / KISS / YAGNI
- **Coesão**: Services coesos por domínio; `EquipamentoService` agrega normalização+validação+persistência (alto volume, porém organizado em métodos pequenos na maioria).
- **Acoplamento**: Baixo — classes recebem `$db` e dependências injetadas; `ApiResponse`/`RequestValidator`/`RateLimiter` sem dependência de globals.
- **YAGNI**: `RateLimiter` suporta 2 backends (arquivo/DB) e 2 algoritmos (sliding window/token bucket) — justificável (dev sem APCu); `AuthService` sem excesso.
- **Parâmetros**: apenas `loadManutencaoPayload` (10) destoa; resto <= 4.

### Naming
- Nomes claros e descritivos em pt-BR (domínio) e inglês (código). Sem abreviações obscuras.

### Complexidade / Métodos gigantes / Aninhamento
- Métodos grandes com alta complexidade: **F-404**.
- Aninhamento: `inc/security.php:154` (5 níveis) e `inc/assets.php:71` (5) — borderline, aceitável.

### Duplicação
- **F-401** (endpoints de manutenção) é a duplicação dominante.
- Menor: parse de body inline duplicado nos mesmos endpoints (F-402), padrões `array_map(fn($row)=>(array)$row,...)` repetidos.

### Código morto
- **F-402** `getInputPayload()`. Sem outras funções/classes mortas detectadas (todas as públicas de `inc/` e `src/` têm uso).

### Comentários obsoletos
- **F-407** tabela de migrations no README. Sem `TODO`/`FIXME`/`HACK` no código (0 ocorrências).

### Testabilidade / Qualidade de testes
- A alta complexidade de `extractCommonData`/`validateInput` dificulta cenários isolados; testes existentes cobrem Services principais. Detalhe na Fase 11.

## Matriz de Qualidade

| ID | Área | Severidade | Status | Observação |
| -- | ---- | ---------- | ------ | ---------- |
| F-401 | Duplicação endpoints manutenção (DRY) | Média | Aberto | Consolidar em ManutencaoService |
| F-402 | Código morto `getInputPayload()` | Baixa | Corrigido | Usado em ambos endpoints (parse inline removido) |
| F-403 | Classes src sem namespace | Baixa | Aberto | PSR-4 em janela própria |
| F-404 | Complexidade ciclomática alta | Média | Aberto | Quebrar extractCommonData/validateInput |
| F-405 | `loadManutencaoPayload` 10 params | Baixa | Aberto | Usar array de filtros |
| F-406 | PSR-12: 226 erros / 187 warnings | Baixa | Corrigido | phpcbf: 14 erros restantes (todos MissingNamespace, F-403) |
| F-407 | README migrations desatualizado | Baixa | Corrigido | Tabela consolidada p/ 2026_07_13_110000 |

## Conclusão

Qualidade **geral boa com dívida concentrada**: a duplicação de manutenção (F-401) e a complexidade de normalização (F-404) são os pontos com maior benefício técnico de refatoração. F-402, F-406 e F-407 foram corrigidos em 2026-08-16; restam F-401, F-403, F-404 e F-405. Nenhum problema de integridade ou segurança foi identificado nesta fase.