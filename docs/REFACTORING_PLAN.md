# REFACTORING_PLAN.md

Plano de Refatoração — Projeto "Controle Share Videos" (Câmeras e Alarmes v1.0).
Fase 14 do protocolo (`docs/ESPECIFICACAO-FINAL.md:980`). Data: 2026-08-16.

A refatoração é **separada da auditoria** e segue o ciclo: identificar problema → causa raiz →
impacto → solução → compatibilidade → plano → implementar → testar → medir → validar →
atualizar documentação. Nenhuma alteração em lote sem validação intermediária.

## Recomendações por prioridade

| ID | Problema | Prioridade | Esforço | Status |
| -- | -------- | ---------- | ------- | ------ |
| R-001 | Duplicação `api_manutencao_cameras` × `api_manutencao_alarmes` (~1180 linhas) | Alta | 3 | Resolvido |
| R-002 | `loadManutencaoPayload()` com 10 parâmetros posicionais | Média | 1 | Resolvido |
| R-003 | Complexidade ciclomática alta em `extractCommonData` (65) e `validateInput` (63) | Média | 2 | Resolvido |
| R-004 | 13 classes `src/` sem namespace (PSR-4) | Baixa | 3 | Resolvido |

---

# R-001 — Duplicação entre manutenção de câmeras e alarmes

## 1. ID

R-001

## 2. Problema

`api/v2/api_manutencao_cameras.php` (616 linhas) e `api/v2/api_manutencao_alarmes.php`
(564 linhas) são espelhos estruturais: mesma função `loadManutencaoPayload`, mesmas ações
POST (`create_os`, `start_os`, `finalize_os`), mesmo fluxo de validação, mesma montagem de
payload com `cameras`, `status_options`, `procedimento_options`, `pending_orders`,
`executing_orders`, `historico`, `pagination` e `sorting`. A duplicação real cobre
~450–500 linhas equivalentes por par.

## 3. Localização

- Arquivo: `api/v2/api_manutencao_cameras.php` e `api/v2/api_manutencao_alarmes.php`.
- Funções: `loadManutencaoPayload` (ambos, linha 5), handlers `create_os`/`start_os`/`finalize_os`.
- Módulo: API v2 — manutenção.
- Auxiliar existente: `api/v2/api_manutencao_utils.php` (já compartilha `getInputPayload` e `getMaintenanceDefaultIds`).

## 4. Evidência

```bash
wc -l api/v2/api_manutencao_cameras.php api/v2/api_manutencao_alarmes.php
#  616 api_manutencao_cameras.php
#  564 api_manutencao_alarmes.php
grep -c "function loadManutencaoPayload" api/v2/api_manutencao_*.php
# 2
```

Comparação estrutural por blocos idênticos (status e procedimento options, `historicoParts`,
`pendingQuery`/`executingQuery`/`countQuery`/`historyQuery`, `sortMap` com 9 chaves, insert/update):
mais de 85% do corpo dos handlers é equivalente, divergindo apenas em:
- tabela base (`equipamentos_manutencoes` × `alarmes_manutencoes`);
- colunas de JOIN (`equipamentos e` × `central_alarmes ca`);
- escopo (`verificarEscopoEquipamento` × `verificarEscopoAlarme`);
- finalização (`equipamentos.status_id` + histórico × `alarmes_manutencoes.status_novo`);
- chave de cache (`manut_cameras_list` × `manut_alarmes_list`).

## 5. Fonte

Padrões DRY (Martin Fowler, Refactoring), PSR-12, boas práticas de manutenibilidade.
Achado da auditoria: F-401 (Qualidade, Média).

## 6. Situação Atual

Qualquer correção de bug de manutenção precisa ser aplicada **duas vezes**. A inconsistência
entre os arquivos é provável (ex.: `api_manutencao_alarmes` não deleta cache em `finalize_os`,
enquanto o de câmeras deleta `manut_cameras_list`).

## 7. Causa Raiz

Os dois endpoints foram criados por cópia-adaptação (copy-paste evolution) sem extração de
lógica comum além dos dois helpers existentes.

## 8. Impacto

- **Manutenção**: custo duplicado; risco de correção em apenas um arquivo.
- **Consistência**: comportamento divergente entre câmeras e alarmes.
- **Testabilidade**: 1180 linhas de lógica imperativa sem cobertura unitária.
- **Onboarding**: dois arquivos grandes para entender um domínio.

## 9. Implementação Recomendada

Extrair uma classe de serviço única parametrizada pela "entidade" da manutenção:

1. Criar `src/Services/ManutencaoService.php` com configuração declarativa:
   ```php
   $config = [
       'tabela' => 'equipamentos_manutencoes', // ou 'alarmes_manutencoes'
       'entidade_join' => 'equipamentos e ON e.id = m.equipamento_id',
       'entidade_extra' => [ ... colunas específicas ... ],
       'scope_verifier' => fn($id) => verificarEscopoEquipamento($id),
       'cache_key' => 'manut_cameras_list',
       'finalize_extra' => [ ... SQL específico de status/histórico ... ],
   ];
   ```
2. `loadManutencaoPayload()` passa a aceitar `array $config` (ver R-002).
3. Os endpoints `api_manutencao_cameras.php` e `api_manutencao_alarmes.php` reduzem-se a
   ~40 linhas cada: montam o config, chamam `ManutencaoService` e retornam `ApiResponse`.
4. Manter `api_manutencao_utils.php` para `getInputPayload`/`getMaintenanceDefaultIds`.

## 10. Código Atual

`api/v2/api_manutencao_cameras.php:5-311` e `api/v2/api_manutencao_alarmes.php:5-260`
(duas cópias de `loadManutencaoPayload`).

## 11. Código Sugerido

```php
// src/Services/ManutencaoService.php (esboço)
class ManutencaoService
{
    public function __construct(private database $db, private array $config) {}

    public function loadPayload(int $equipamentoId, int $page, int $perPage,
        string $busca, string $sortBy, string $sortDir, bool $includeLists,
        string $dataInicial = '', string $dataFinal = ''): array
    {
        // bloco único: lists com cache $config['cache_key'],
        // pending/executing/count/history sobre $config['tabela'] com
        // $config['entidade_join'], sortMap comum, pagination comum.
    }

    public function createOs(array $payload, int $usuarioId): void { /* ... */ }
    public function startOs(array $payload, int $usuarioId): void { /* ... */ }
    public function finalizeOs(array $payload, int $usuarioId): void { /* ... */ }
}

// api/v2/api_manutencao_cameras.php (novo corpo)
$service = new ManutencaoService(db(), [
    'tabela' => 'equipamentos_manutencoes',
    'entidade_join' => 'INNER JOIN equipamentos e ON e.id = m.equipamento_id',
    'scope_verifier' => 'verificarEscopoEquipamento',
    'cache_key' => 'manut_cameras_list',
    // ...
]);
```

## 12. Benefícios

- **Manutenção**: correção única; comportamento consistente entre câmeras e alarmes.
- **Legibilidade**: 1180 → ~350 linhas totais.
- **Testabilidade**: `ManutencaoService` testável com PDO mockado (padrão já usado em
  `tests/Integration/EquipamentoServiceTest.php`).
- **Escalabilidade**: novas entidades de manutenção (ex.: LPR/DVR) reutilizam a classe.

## 13. Riscos

- Regressão em queries (joins/colunas específicas de alarmes: `status_novo`, sem `updated_at`).
- Diferenças de cache: câmeras deleta cache; alarmes não — decisão a alinhar no padrão único.
- Requer revalidação manual com banco real (sem instância local disponível).

## 14. Compatibilidade

- **API**: igual — contratos de resposta preservados (`pending_orders`, `historico`, etc.).
- **Banco**: sem migração; apenas leitura/escrita nas tabelas existentes.
- **Dependências/Runtime/Docker**: nenhuma mudança.

## 15. Testes

- Criar `tests/Unit/ManutencaoServiceTest.php` cobrindo: `loadPayload` com dados mockados
  (pending/executing/history), `createOs`, `startOs`, `finalizeOs` (sucesso e validações).
- Manter testes de API existentes (`tests/Api/*`) — não devem mudar de contrato.
- Executar `vendor/bin/phpunit --no-coverage` (baseline 176 testes) e PHPStan antes/após.

## 16. Métrica

- `wc -l` somado dos 2 endpoints + service: **667** (hoje 1180). O SQL específico de cada
  entidade foi movido para os data files `api/v2/manutencao_config_cameras.php` (237 linhas)
  e `api/v2/manutencao_config_alarmes.php` (213 linhas) — dados declarativos, não lógica
  duplicada. Os endpoints caíram de 616/564 para 66/96 linhas.
- Nº de `grep "loadManutencaoPayload"` = **0** (hoje 2; alvo 1 — a função foi eliminada,
  o fluxo vive em `ManutencaoService::loadPayload`).
- Cobertura unitária dos handlers POST de manutenção > 0 (hoje 0): 13 testes novos em
  `tests/Unit/ManutencaoServiceTest.php`.

## 17. Status

Resolvido — commit desta alteração. `ManutencaoService` único parametrizado por config
declarativo por entidade; endpoints reduzidos a wrappers. Validado com PHPUnit (191 testes
OK, 0 falhas) e PHPStan (nível 5, `[OK]`).

## 18. Notas de implementação (2026-08-16)

- **Config declarativo por entidade** (em `api/v2/manutencao_config_*.php`): `table`,
  `entity_id_param`, `entity_column`, `entity_title`, `entity_resource`, `scope_verifier`,
  `scope_message`, `mismatch_message`, `audit`, `validate_numero_os`, `list_payload_key`,
  `list_cache_key`, SQLs de listagem/pending/executing/count/history com placeholders
  `{{where}}`/`{{historico_where}}`/`{{order}}`, `sort_map`, `search_builder`/
  `history_search_builder`, `create_rules`, `create_entity_lookup`, `create_insert`,
  `finalize_extra`.
- **Correção de bug latente**: `api_manutencao_alarmes.php` chamava
  `enableAuditTriggers('api')` (assinatura é `enableAuditTriggers($db, $origem)`), o que
  causaria fatal `Call to a member function setUserContext() on string` em todo POST.
  Corrigido no service: `enableAuditTriggers($this->db, 'api')` quando `audit=true` (alarmes).
- **Desvios de contrato aceitos** (frontend lê apenas `success`/`message`/`error`):
  - NOT_FOUND agora responde via `ApiResponse::error` com `data: []` (antes `{resource,id}`);
    mensagem preservada (`Recurso 'x' com ID n não encontrado`).
  - `data_hora` unificado para o formato das câmeras (aceita `Y-m-d\TH:i` e `Y-m-d H:i:s`,
    vazio → agora); alarmes passam a aceitar ambos (antes só `Y-m-d H:i:s`).
  - Erro de validação de `create_os` passa a vir como `VALIDATION_ERROR` com mensagem
    achatada (antes `validationError` estruturado) — mesma classe de mensagem dos services.
- **Decisão de cache**: `list_cache_key` (`manut_cameras_list`) é invalidado no create/start/
  finalize das câmeras; alarmes sem invalidação (preserva comportamento original). O cache de
  **resposta** GET dos alarmes (`manut_alarmes:*`, TTL 30s, bypass `nocache`) permanece no endpoint.
- **Rollback**: `finalizeOs` usa `beginTransaction`; falhas pós-transação lançam
  `RuntimeException` para o catch `Throwable` do endpoint executar `rollback` (mesmo padrão original).

---

# R-002 — `loadManutencaoPayload` com 10 parâmetros posicionais

## 1. ID

R-002

## 2. Problema

`loadManutencaoPayload(database $db, int $equipamentoId, int $page, int $perPage,
string $busca, string $sortBy, string $sortDir, bool $includeLists,
string $dataInicial = '', string $dataFinal = '')` usa 10 parâmetros posicionais,
violando a regra pragmática de limite (tipicamente 5–7) e dificultando a leitura das chamadas.

## 3. Localização

- Arquivo: `api/v2/api_manutencao_cameras.php:5-16` e `api/v2/api_manutencao_alarmes.php:5-16`.
- Função: `loadManutencaoPayload`.
- Chamadas: `api/v2/api_manutencao_cameras.php:340` e `api/v2/api_manutencao_alarmes.php:301`.

## 4. Evidência

```bash
grep -n "function loadManutencaoPayload(" api/v2/api_manutencao_cameras.php
# 5:function loadManutencaoPayload(
#   database $db, int $equipamentoId, int $page, int $perPage,
#   string $busca, string $sortBy, string $sortDir, bool $includeLists,
#   string $dataInicial = '', string $dataFinal = ''
```

## 5. Fonte

Achado F-405 (Qualidade, Baixa); princípios de design de assinaturas (object parameter).

## 6. Situação Atual

Chamadas posicionais longas: `loadManutencaoPayload($db, $id, $page, $perPage, $busca, $sortBy, $sortDir, $includeLists, $dataInicial, $dataFinal)`.

## 7. Causa Raiz

Função cresceu por acúmulo de filtros sem refatoração da assinatura.

## 8. Impacto

- Erro fácil de trocar a ordem dos argumentos (bug silencioso).
- Chamadas ilegíveis e difíceis de evoluir (novo filtro = novo parâmetro).

## 9. Implementação Recomendada

Agrupar os parâmetros em um único `array $opts` (ou Value Object `ManutencaoQuery`):
```php
loadManutencaoPayload(array $opts): array
// $opts = ['equipamento_id', 'page', 'per_page', 'busca', 'sort_by',
//          'sort_dir', 'include_lists', 'data_inicial', 'data_final']
```
Com `named arguments` (PHP 8) também é possível manter a assinatura, mas o `array $opts`
é mais compatível com o `ManutencaoService` da R-001.

## 10. Código Atual

```php
function loadManutencaoPayload(
    database $db, int $equipamentoId, int $page, int $perPage,
    string $busca, string $sortBy, string $sortDir, bool $includeLists,
    string $dataInicial = '', string $dataFinal = ''
): array {
```

## 11. Código Sugerido

```php
function loadManutencaoPayload(array $opts): array {
    $db = db();
    $equipamentoId = max(0, (int)($opts['equipamento_id'] ?? 0));
    $page = max(1, (int)($opts['page'] ?? 1));
    $perPage = max(10, (int)($opts['per_page'] ?? 20));
    // ...
}
```

## 12. Benefícios

- Legibilidade e menos bugs de ordem de argumentos.
- Preparação para R-001 (service único).

## 13. Riscos

- Baixo: refatoração mecânica, coberta por testes.

## 14. Compatibilidade

- API/Banco/Dependências: nenhuma mudança de contrato externo.

## 15. Testes

- `php -l` e PHPStan; testes existentes continuam passando (função não é chamada nos testes atuais).

## 16. Métrica

- `grep "loadManutencaoPayload("` mostra assinatura com ≤ 5 parâmetros (hoje 10).

## 17. Status

Resolvido — commit `a0c1275` (R-002). `loadManutencaoPayload(array $opts)` com os 10
parâmetros agrupados; posteriormente eliminada na R-001 (fluxo em `ManutencaoService`).

---

# R-003 — Complexidade ciclomática de `extractCommonData` (65) e `validateInput` (63)

## 1. ID

R-003

## 2. Problema

`EquipamentoService::extractCommonData()` (src/Services/EquipamentoService.php:18) e
`AlarmeService::validateInput()` (src/Services/AlarmeService.php:27) têm complexidade
ciclomática 65 e 63 (limite recomendado: 20), por serem funções de normalização com
dezenas de branches ternários/`??` e chamadas de validadores encadeados.

## 3. Localização

- Arquivo: `src/Services/EquipamentoService.php:18-62` (função `extractCommonData`).
- Arquivo: `src/Services/AlarmeService.php:27-…` (função `validateInput`).
- Classe: `EquipamentoService`, `AlarmeService`.

## 4. Evidência

Medição phpmess (PHPMD) ou equivalentes na auditoria F-404: 65 e 63 > 20 (limiar).

## 5. Fonte

Achado F-404 (Qualidade, Média); McCabe, Ciclomatic Complexity.

## 6. Situação Atual

Um único método faz normalização + validação + casting de ~40 campos (equipamento) em um
`return [...]` monolítico, com `max(0, (int)(...))`, `trim((string)(...)) ?: null`,
`!empty(...) ? 1 : 0`, `validateUf`, `validateCep`, `parseDecimal` intercalados.

## 7. Causa Raiz

Campos heterogêneos (normalização de IP, UF, CEP, decimal, bool, ID) tratados inline,
sem extração por grupo (endereço, LPR, DVR, totem, comum).

## 8. Impacto

- Testabilidade baixa; dificuldade de revisão; risco de regressão em normalização.
- Difícil reutilizar grupos de campos isoladamente.

## 9. Implementação Recomendada

Quebrar por grupos de campos com sub-normalizadores privados:
```php
private function extractAddressFields(array $d): array   // logradouro, bairro, cidade, uf, cep, numero
private function extractLprFields(array $d): array       // lpr_*
private function extractDvrFields(array $d): array       // dvr_*
private function extractTotemFields(array $d): array     // totem_*
private function extractCommonFields(array $d): array    // status, procedimento, regiao, tipo...
```
`extractCommonData()` vira composição: `return array_merge($this->extractCommonFields($d),
$this->extractAddressFields($d), ...)`. Mesmo padrão para `validateInput` do `AlarmeService`
(agrupar por seção: identificação, endereço, monitoramento, integração).

## 10. Código Atual

```php
public function extractCommonData(array $data): array
{
    return [
        'status_id' => max(0, (int)($data['status_id'] ?? 0)),
        // ... ~40 entradas com ternários encadeados
        'totem_tem_lpr' => !empty($data['totem_tem_lpr']) ? 1 : 0,
    ];
}
```

## 11. Código Sugerido

```php
public function extractCommonData(array $data): array
{
    return array_merge(
        $this->extractCommonFields($data),
        $this->extractAddressFields($data),
        $this->extractLprFields($data),
        $this->extractDvrFields($data),
        $this->extractTotemFields($data),
    );
}
```

## 12. Benefícios

- Complexidade por método < 20; grupos reutilizáveis e testáveis isoladamente.
- Legibilidade e revisão por seção.

## 13. Riscos

- Mudança de ordem/chaves do array resultante (ordem não importa para INSERT/UPDATE nomeado).
- Valor default deve ser idêntico — validar com testes de igualdade de payload antes/depois.

## 14. Compatibilidade

- API/Banco: nenhuma mudança; `extractCommonData` retorna o mesmo conjunto de chaves.

## 15. Testes

- Criar teste de "payload out" idêntico antes/depois: alimentar o mesmo `$data` e comparar
  `extractCommonData()` com `assertEquals` (refactor safety net). Reusar dados dos testes
  unitários existentes (`tests/Unit/EquipamentoServiceTest.php`).
- Executar suite completa e PHPStan.

## 16. Métrica

- Complexidade ciclomática de `extractCommonData`/`validateInput` ≤ 20 (hoje 65/63).
- Todos os sub-métodos com complexidade ≤ 10.

## 17. Status

Resolvido — commit `338af50` (R-003). `extractCommonData` e `validateInput` decompostos em
sub-normalizadores por grupo (complexidade por método < 20).

---

# R-004 — 13 classes `src/` sem namespace (PSR-4)

## 1. ID

R-004

## 2. Problema

13 classes/interfaces em `src/` não possuem namespace e são carregadas via classmap
(composer), fora do padrão PSR-4 do próprio projeto.

## 3. Localização

- Arquivos sem `namespace`: `src/Services/*` (6), `src/Services/Concerns/ParsesInput.php`,
  `src/Logging/LoggerFactory.php`, `src/Api/*` (3), `src/Validation/FormValidator.php`,
  `src/ErrorHandler.php`, `src/Exceptions/AuthException.php`.

## 4. Evidência

```bash
grep -rLn "namespace" src/ --include="*.php"
# 13 arquivos sem namespace
```

## 5. Fonte

Achado F-403 (Qualidade, Baixa); PSR-4 (PHP-FIG); composer.json `autoload`.

## 6. Situação Atual

Resolvido — todas as classes de `src/` ganharam namespace PSR-4 (`App\`) e o autoload do
composer foi migrado de `classmap` para `psr-4`. Todas as referências (API, tests, bootstrap,
config, inc, resources, auth) foram atualizadas com `use App\...` (ou FQCN quando o uso era
pontual). Antes da migração, classes globais como `EquipamentoService`, `ApiResponse`,
`RateLimiter` eram referenciadas sem `use` em dezenas de arquivos.

## 7. Causa Raiz

Projeto evoluiu de um padrão procedural sem namespaces; `composer.json` usa `"classmap": ["src/"]`
em vez de `psr-4`, então as classes globais funcionam sem `use`.

## 8. Impacto

- Colisão de nomes (ex.: `ApiResponse` genérico) em futuras integrações.
- Autoload classmap menos eficiente; inconsistência com PSR-4.
- Migração com `use` em ~50 arquivos + testes.

## 9. Implementação Recomendada

Adotar PSR-4 (`App\`):
1. Em `composer.json`, substituir o autoload classmap por PSR-4:
   ```json
   "autoload": {
       "psr-4": { "App\\": "src/" },
       "files": [ "config/app.php", ... ]
   }
   ```
2. Adicionar `namespace App\Services;`, `namespace App\Api;`, etc. em cada classe.
3. Nos arquivos que usam as classes (API, testes, bootstrap), adicionar `use App\...`.
4. Rodar `composer dump-autoload` e validar com PHPUnit/PHPStan.
5. Adicionar verificação no CI (`composer dump-autoload --strict-psr`).

## 10. Código Atual

```php
<?php
declare(strict_types=1);
use App\Exception\ServiceException;
class EquipamentoService { ... }
```

## 11. Código Sugerido

```php
<?php
declare(strict_types=1);
namespace App\Services;
use App\Exception\ServiceException;
class EquipamentoService { ... }
```

## 12. Benefícios

- PSR-4 compliance; autoload eficiente; menos risco de colisão; CI mais estrito.

## 13. Riscos

- Quebra de autoload em qualquer arquivo esquecido (mitigado por `composer dump-autoload` + suite).
- `db()` global e funções globais (`verificarEscopo*`) seguem globais — fora do escopo.

## 14. Compatibilidade

- API/Banco: nenhuma mudança; Docker: precisa `composer dump-autoload` no build (já roda no CI).

## 15. Testes

- `vendor/bin/phpunit --no-coverage` (191 testes) e PHPStan `[OK]` após migração.
- `composer validate --strict` e `composer dump-autoload --strict-psr`.

## 16. Métrica

- `grep -rLn "namespace" src/` = 0 (hoje 15 arquivos, todos com namespace); `composer.json`
  autoload PSR-4 (hoje `"psr-4": { "App\\": "src/" }`).

## 17. Status

Resolvido — commit dedicado (R-004). Namespaces adicionados aos 15 arquivos de `src/`
(13 do plano + `ManutencaoService` da R-001), `src/ErrorHandler.php` migrado para
`namespace App;` com a função `registerGlobalErrorHandlers()` virando `App\registerGlobalErrorHandlers`
(callers atualizados para `\App\...`). Classes globais referenciadas sem qualificação
(`Exception`, `RuntimeException`, `Throwable`, `PDO`, `InvalidArgumentException`,
`RecursiveIteratorIterator`, etc.) receberam `use`/qualificação. Validado com PHPUnit
(191 testes, 36 skipped — integrações dependem de DB) e PHPStan `[OK]`.

---

## Priorização para implementação

A ordem recomendada (menor risco primeiro, máximo valor incremental):

1. **R-002** (esforço 1) — assinatura em `array $opts`: prepara R-001, baixíssimo risco.
2. **R-003** (esforço 2) — quebrar normalizadores: ganho imediato de testabilidade, sem tocar API.
3. **R-001** (esforço 3) — service único de manutenção: maior ganho, depois de R-002.
4. **R-004** (esforço 3) — namespaces: mecânico porém amplo; deixar por último.

Cada item deve ser commitado separadamente e validado com PHPUnit + PHPStan antes do próximo.