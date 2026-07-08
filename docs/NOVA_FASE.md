# NOVA FASE — Melhorias e Complementos ✅

## 🔧 CRUD Faltante

| # | Item | Status |
|---|---|---|
| 1 | `api_excluir_alarme` — Endpoint DELETE para central_alarmes (soft delete) | ✅ |
| 2 | Delete de locais — Página + action_handler para soft delete | ✅ |
| 3 | API de exportação — Endpoint `LocaisService::getAllLocaisExport()` (CSV/JSON) | ✅ |

## 📦 Infraestrutura

| # | Item | Status |
|---|---|---|
| 4 | Monolog — Substituir `error_log()` por Logger PSR-3 estruturado | ✅ |
| 5 | Tabela `rate_limits` no schema — Adicionar ao cftv_gml.sql | ✅ |
| 6 | Testes frontend — Testes Vitetest para utils TS | ✅ |

## 🚀 Funcionalidades

| # | Item | Status |
|---|---|---|
| 7 | UI admin lookup tables — CRUD secretarias, regiões, tipos | ✅ |
| 8 | Search full-text alarmes — FULLTEXT + IP validation em api_alarmes | ✅ |
| 9 | Notificações — Sistema de pendências/alertas (manutenções vencidas) | ✅ |

## 🧹 Organização

| # | Item | Status |
|---|---|---|
| 10 | Limpeza TS entries vs raiz — Unificar arquivos, remover inconsistências | ✅ |
| 11 | Padronizar naming — `manutencao_alarmes_v2.ts` → `manutencao_alarmes.ts` | ✅ |
