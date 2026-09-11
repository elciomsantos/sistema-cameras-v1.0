# ADR — Decisões de Segurança (F-102, F-103, F-104)

**Data:** 2026-09-11
**Contexto:** fechamento dos achados remanescentes da auditoria de segurança
(`docs/SECURITY_REPORT.md`, 2026-08-16) como parte da liberação para produção.

## F-102 — Regeneração de ID de sessão no login (CWE-384)

**Status: ACEITO (resolvido).**

- `src/Services/AuthService.php:271` executa `session_regenerate_id(true)` em
  `createSession()`, mitigando session fixation.
- O boot de sessão é ponto único em `inc/security.php`: `configureSessionSecurity()`
  (definida em `config/app.php`) é chamada **antes** de `session_start()`, garantindo
  que HttpOnly/SameSite/secure sejam sempre aplicados antes de qualquer uso.
- A remoção de `session.sid_length`/`session.sid_bits_per_character` (deprecated no
  PHP 8.4, ver C-6) não reduz segurança: o PHP 8.4 já usa SID com 96 bits de entropia.

**Decisão:** nenhuma ação adicional necessária.

## F-103 — Escopo global do supervisor (CWE-863 / BOLA)

**Status: ACEITO (decisão de negócio).**

- `inc/check_access.php:91,157,184`: `resolveUserAccessLevel()` retorna `supervisor`
  → `verificarEscopoEquipamento/Anexo/Alarme` retornam `true` (acesso global).
- O usuário comum (`user`) permanece escopado à própria secretaria.
- A hierarquia é cumulativa (`admin > supervisor > user`), documentada no README.

**Decisão:** o papel `supervisor` foi **intencionalmente** projetado para gerenciar
múltiplas secretarias (equipamentos/anexos/alarmes). A inconsistência apontada
(supervisor não pode anexar a local de outra secretaria via `api_upload_anexo`) é
residual e aceita. Registrar formalmente para fins de auditoria.

## F-104 — Senha temporária exibida em sessão (sem SMTP)

**Status: ACEITO (comportamento definido).**

- `auth/action_recuperar_senha.php` é restrito a `admin` (linha 5), com CSRF e rate
  limit (3 tentativas/15 min).
- A senha temporária é exibida **ao administrador logado** (não ao usuário-alvo) e o
  usuário é marcado `senha_temporaria=1`, forçando troca no próximo login.
- O fluxo é intencional pela ausência de infraestrutura SMTP.

**Decisão:** comportamento aceito. Melhoria futura (P2): notificar por e-mail quando
SMTP estiver disponível; mover a exibição da senha para um canal mais seguro.

---

## Resumo de risco residual

| ID | Risco residual | Aceitação |
|----|----------------|-----------|
| F-102 | Nenhum | Resolvido |
| F-103 | Supervisor com acesso amplo (por design) | Aceito por negócio |
| F-104 | Senha temporária em sessão (admin) | Aceito; SMTP como melhoria P2 |