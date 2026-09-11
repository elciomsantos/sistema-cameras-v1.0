# Plano de Execução para Produção — Sistema de Câmeras e Alarmes v1.0

**Data:** 2026-09-11
**Referência:** `docs/PROD_READINESS_REPORT.md`

## Priorização

- **P0** — Bloqueador: deve ser resolvido antes do go-live.
- **P1** — Obrigatório, mas não bloqueia o início do staging.
- **P2** — Recomendado (melhorias/validações adicionais).

## Fase 1 — Validar ambiente de produção (P0)

**Objetivo:** provar que o stack prod (Nginx/FPM) funciona de ponta a ponta.

- [ ] 1.1 Criar `.env.prod.example` (derivado de `.env.docker`) com variáveis
      reais obrigatórias e documentação consistente com `docker-compose.prod.yml:8`.
- [ ] 1.2 Subir em staging: `docker compose -f docker-compose.prod.yml up -d --build`.
- [ ] 1.3 Validar health: `curl -sf http://localhost/` e `index.php?page=api/api_ping` (200).
- [ ] 1.4 Validar `api_health` retorna 401 sem sessão (controle de acesso correto).
- [ ] 1.5 Smoke-test de login (com credenciais de staging), navegação, upload de anexo.
- [ ] 1.6 Confirmar headers: HSTS, CSP, X-Frame-Options, X-Content-Type-Options.
- [ ] 1.7 Confirmar `read_only`/`cap_drop` não quebram escrita em `storage/` e `public/uploads`
      (volumes nomeados `app_storage`/`app_uploads` já declarados — validar permissões www-data).
- [ ] 1.8 Confirmar migrations: `php database/migrate.php --status` → `Pending: 0`.

## Fase 2 — Reconciliar frontend self-host vs CDN (P0)

**Objetivo:** eliminar fallbacks CDN e alinhar a CSP com a estratégia self-host.

- [ ] 2.1 Diagnosticar por que `vite_manifest()` não resolve em runtime no container
      (verificar `dist/.vite/manifest.json` presente, permissões, bind-mount). Logs:
      o container dev atual serve o fallback CDN.
- [ ] 2.2 Garantir que `dist/` e `public/sw-assets.json` sejam produzidos por `npm run build`
      **antes** de construir o estágio prod do Dockerfile (já há `COPY --from=build`).
- [ ] 2.3 Remover os fallbacks CDN em `inc/header.php` (linhas 20, 27, 53) ou mantê-los
      apenas como fallback de *último recurso* em ambiente dev — decisão a documentar.
- [ ] 2.4 Remover/limitar as entradas CDN da CSP em `public/index.php:47-54`
      (`cdn.jsdelivr.net`, `cdnjs.cloudflare.com`, `code.jquery.com`, `cdn.datatables.net`).
- [ ] 2.5 Validar no DevTools que não há mais requests para CDNs externas.

## Fase 3 — Segredos e configuração de produção (P0)

- [ ] 3.1 Gerar senhas fortes para `DB_ROOT_PASSWORD`, `DB_PASS` (production).
- [ ] 3.2 Garantir que `.env` de produção NÃO esteja no repositório (`.gitignore` já bloqueia — confirmar).
- [ ] 3.3 Confirmar que `docker-compose.prod.yml` falha com `DB_ROOT_PASSWORD required`/
      `DB_PASS required` quando ausentes.
- [ ] 3.4 (Opcional P2) Configurar TLS/terminação HTTPS — hoje o compose prod só expõe porta 80;
      HSTS já está preparado mas exige HTTPS real.
- [ ] 3.5 Definir `APP_ALLOWED_ORIGINS` explícito (não deixar vazio `*` implícito).

## Fase 4 — Reproduzir suíte de testes e análise estática (P0)

- [ ] 4.1 Prover ambiente com PHP 8.4 + composer (container de build ou CI) e `composer install`
      **com dev-deps** (o `vendor/` local atual foi instalado como root sem `require-dev`).
- [ ] 4.2 Executar `vendor/bin/phpunit` e registrar resultado real (comparar com os "176+ testes").
- [ ] 4.3 Executar `vendor/bin/phpstan analyse` e confirmar nível 5 / 0 erros.
- [ ] 4.4 Executar `composer audit --no-dev` e `npm audit` (registrar resultados).
- [ ] 4.5 Executar smoke-test de segurança ativo (BOLA/IDOR) com MySQL disponível —
      desbloquear os itens marcados "Bloqueado" no `SECURITY_REPORT.md`.

## Fase 5 — Segurança: pendências e validações (P1)

- [ ] 5.1 Revalidar F-102 (boot duplicado de `session_start`) — confirmar caminho único.
- [ ] 5.2 Documentar formalmente a decisão de negócio do F-103 (supervisor escopo global)
      em um ADR/doc, já que está apenas em comentários de código.
- [ ] 5.3 Decidir/melhorar F-104 (senha temporária em sessão) — avaliar SMTP ou outro
      canal em produção.

## Fase 6 — Observabilidade e operação (P2)

- [ ] 6.1 Definir `SENTRY_DSN` real e `send_default_pii`/`traces_sample_rate` adequados.
- [ ] 6.2 Confirmar logging: diretórios de log graváveis em `read_only` (tmpfs `/var/log/nginx`).
- [ ] 6.3 Configurar backup diário do MySQL (`scripts/backup.sh`) + política de retenção.
- [ ] 6.4 Configurar monitoramento de recursos (alerts CPU/mem/disco) no host prod.

## Definição de "pronto para produção" (DoD)

O sistema estará liberado quando:

1. O stack `docker-compose.prod.yml` subir em staging e passar no smoke-test completo (Fase 1).
2. O frontend servir assets self-host sem requests a CDN e a CSP estiver alinhada (Fase 2).
3. Não houver segredos default/versionados e o compose falhar na ausência dos obrigatórios (Fase 3).
4. A suíte `phpunit`/`phpstan`/audit for reproduzida e não houver falhas bloqueantes (Fase 4).
5. Os pontos de segurança em aberto (F-102, F-103, F-104) estiverem fechados ou formalmente aceitos (Fase 5).
6. (Recomendado) HTTPS em produção configurado antes de expor ao público.