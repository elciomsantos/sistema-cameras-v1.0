# RELATÓRIO DE ANÁLISE DO SISTEMA

## 🔴 BUGS CORRIGIDOS

| # | Arquivo | Problema | Severidade |
|---|---|---|---|
| 1 | resources/cadastro_cameras.php:203 | $formData['modelo_id'] sem htmlspecialchars() (hidden input) | Média |
| 2 | resources/relatorios_cameras.php:163 | $CSP_NONCE sem htmlspecialchars(), inconsistente com o resto | Baixa |

## 🔴 BUGS/SECURANÇA NÃO CORRIGIDOS (REQUEREM ATENÇÃO)

| # | Arquivo | Problema | Severidade |
|---|---|---|---|
| 1 | auth/action_recuperar_senha.php | Senha temporária armazenada em plaintext na session | ALTA |
| 2 | api/v2/api_upload_anexo.php | Upload sem verificar escopo (usuário pode anexar arquivo em equipamento de outra secretaria) | ALTA |
| 3 | resources/action_listar_locais.php | Exportação CSV sem sanitização — vulnerável a CSV injection | Média |
| 4 | api/v2/api_cameras.php:44-48 | Filtro de escopo só para 'user'; supervisores veem todas as secretarias | Média |
| 5 | api/v2/api_excluir_anexo.php | Delete só checa public/uploads/, upload salva em storage/uploads/ → arquivos órfãos | Média |
| 6 | api/v2/renovar_sessao.php:52 | session_regenerate_id(false) — arquivos de sessão antigos acumulam | Baixa |
| 7 | auth/action_recuperar_senha.php | Falta session_regenerate_id() após reset de senha | Baixa |

## 🟡 CÓDIGO DUPLICADO (OPORTUNIDADES DE REFATORAÇÃO)

| # | Arquivos | % Duplicado | Linhas | Recomendação |
|---|---|---|---|---|
| 1 | cadastro_locais.php vs editar_locais.php | ~80% | ~330 | Criar inc/partials/local-form-common.php |
| 2 | cadastro_alarmes.php vs editar_alarmes.php | ~85% | ~180 | Criar inc/partials/alarme-form-fields.php |
| 3 | cadastro_cameras.php vs editar_cameras.php | ~40% | ~250 | Extrair seções LPR/DVR/Totem/Anexos |
| 4 | controle_cameras.php vs controle_alarmes.php | ~95% | ~110 | Unificar em controle.php com parâmetro $modulo |
| 5 | JS inline: cadastro_locais.php + editar_locais.php | ~90% | ~345 | Mover para Vite TS entries + utils |
| 6 | JS autocomplete: cadastro_cameras.php + listar_locais.php | ~70% | ~190 | Criar src/assets/js/utils/autocomplete-utils.ts |
| 7 | Dropzone anexos (6 arquivos) | ~90% | ~23 cada | Criar inc/partials/anexos-section.php |
| 8 | Vite asset footer (14+ arquivos) | 100% | ~8 cada | Criar inc/partials/vite-footer.php |

## 🟠 HARDCODED VALUES INCONSISTENTES

| # | Arquivo | Problema |
|---|---|---|
| 1 | src/assets/js/cadastro_cameras.ts:605-609 | classificacaoMap hardcoded (10 tipos com IDs) |
| 2 | src/assets/js/editar_cameras.ts:293-297 | Mesmo map mas faltando 'ESTRADA': 8 — inconsistência |
| 3 | src/assets/js/modules/dashboard-charts.ts:114 | counts hardcoded com 4 tipos de câmera |
| 4 | resources/cadastro_cameras.php:454-475 | Dropdown estático de tipo_logradouro com 8 opções (db tem 10) |

## ✅ PONTOS FORTES IDENTIFICADOS

- SQL Injection: todas queries usam prepared statements
- XSS: htmlspecialchars(ENT_QUOTES) usado consistentemente
- CSRF: implementado em todas as ações state-changing
- Auth: sessão com timeout, regeneração de ID no login, rate limiter em recovery
- CSP: nonce-based, bem configurado
- LFI/RFI: page resolution com whitelist
- Secrets: .env file fora do webroot
