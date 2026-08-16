# DEPENDENCIES_REPORT.md

Auditoria de Dependências — Projeto "Controle Share Videos" v1.0.
Data: 2026-08-16. Método: `composer audit`, `composer outdated`, `npm audit`, `npm outdated`, inspeção de uso no código (imports/autoload), compatibilidade.

## Resumo Executivo

**Nenhuma vulnerabilidade ativa** após a correção de hoje. A auditoria inicial encontrou **5 vulnerabilidades high** na dependência transitiva `undici` (via `jsdom`, usada somente em testes Vitest); corrigidas com `npm audit fix` (`undici 7.28.0 → 7.29.0`). Não há CVEs pendentes nem dependências abandonadas. Há atualizações de versão disponíveis (a maioria major, com esforço de migração não trivial).

## Achados

### F-501 — (RESOLVIDO) 5 vulnerabilidades `high` em `undici` (transitiva via `jsdom`)

| Campo | Valor |
| ----- | ----- |
| Local | `node_modules/undici@7.28.0` (via `jsdom@29.1.1`, devDependency para testes) |
| Evidência | `npm audit`: GHSA-8xcm-r25x-g524, GHSA-4cwx-7wf7-3272, GHSA-m8rv-5g2x-5cg5, GHSA-jr45-8vmc-qm54, GHSA-v3r7-h72x-cjcm — todas de severidade high, todas patched em `undici` >= 7.29.0. |
| Causa raiz | `jsdom` (devDependency) pinava `undici` na faixa 7.0.0–7.28.0. |
| Risco | Baixo na prática — `undici` só executa no ambiente de testes (nunca em produção), mas a severidade era alta. |
| Classificação | **Confirmado** |
| Severidade | Alta (runtime de teste) |
| Ação | `npm audit fix` — `undici 7.28.0 → 7.29.0`; `npm audit` agora reporta **0 vulnerabilidades**. Vitest 88/88 e `vite build` OK após a atualização. |

### F-502 — Composer: PHPStan 1.x desatualizado (major disponível)

| Campo | Valor |
| ----- | ----- |
| Pacote | `phpstan/phpstan` |
| Versão atual | 1.12.33 |
| Versão recomendada | 2.2.8 |
| Vulnerabilidade | Nenhuma |
| Severidade | Baixa |
| Compatibilidade | Major upgrade; PHPStan 2.x muda comportamento de algumas regras e exige ajustes de config/erros |
| Impacto | Sem risco funcional; atualização opcional para ter regras mais novas |
| Ação | Postergar; atualizar em janela dedicada (exigirá revisão de erros novos). |

### F-503 — Composer: PHPUnit 10.x → 13.x (major, 3 saltos)

| Campo | Valor |
| ----- | ----- |
| Pacote | `phpunit/phpunit` |
| Versão atual | 10.5.64 |
| Versão recomendada | 13.3.1 |
| Vulnerabilidade | Nenhuma |
| Compatibilidade | Major upgrade (11→12→13); sintaxe de atributos/asserts majoritariamente estável, mas require PHP 8.3+ |
| Impacto | Sem risco; suite atual roda 172 testes com 0 erros |
| Ação | Postergar; PHPUnit 10 é suportado. Atualizar junto com PHPStan. |

### F-504 — Composer: `squizlabs/php_codesniffer` 3.13.6 → 4.0.4 (major)

| Campo | Valor |
| ----- | ----- |
| Pacote | `squizlabs/php_codesniffer` |
| Versão atual | 3.13.6 |
| Versão recomendada | 4.0.4 |
| Vulnerabilidade | Nenhuma |
| Compatibilidade | PHPCS 4 muda padrões/remoção de sniffs legados; PSR-12 mantido |
| Impacto | Baixo |
| Ação | Postergar; 3.13.6 atende ao fluxo atual. |

### F-505 — Composer: `sentry/sentry` 4.29.0 → 4.30.0 (patch, recomendado)

| Campo | Valor |
| ----- | ----- |
| Pacote | `sentry/sentry` |
| Versão atual | 4.29.0 |
| Versão recomendada | 4.30.0 |
| Vulnerabilidade | Nenhuma |
| Compatibilidade | Patch (sem breaking) |
| Impacto | Baixo |
| Ação | Atualizar em próxima manutenção. Nota: Sentry está **inativo** em runtime (DSN não configurado — `config/sentry.php:26` retorna se `SENTRY_DSN` vazio). |

### F-506 — npm: atualizações disponíveis (major + minor)

| Pacote | Atual | Recomendada | Nota |
| ------ | ----- | ----------- | ---- |
| `@fortawesome/fontawesome-free` | 6.7.2 | 7.3.1 | Major; ícones podem mudar |
| `jsdom` | 29.1.1 | 30.0.1 | Major; somente testes |
| `typescript` | 5.9.3 | 7.0.2 | Major |
| `rimraf` | 5.0.10 | 6.1.3 | Major |
| `vite` | 8.0.16 | 8.2.1 | Minor |
| `vitest` | 4.1.7 | 4.1.10 | Minor |
| `sass` / `sass-embedded` | 1.101/1.100 | 1.102.0 | Minor |
| `postcss` | 8.5.15 | 8.5.26 | Minor |
| `autoprefixer` | 10.5.0 | 10.5.4 | Minor |
| `cssnano` | 8.0.2 | 8.0.5 | Minor |
| `esbuild` | 0.28.1 | 0.28.2 | Minor |
| `@types/node` | 26.0.0 | 26.2.0 | Minor |

| Vulnerabilidade | Não |
| Compatibilidade | Minors são seguras; majors exigem teste de build |
| Ação | Aplicar minors na próxima manutenção (`npm update`); majors sob demanda. |

### F-507 — Dependências sem uso direto (verificação)

| Campo | Valor |
| ----- | ----- |
| Local | `package.json` |
| Evidência | `@popperjs/core` não tem import direto, mas é **peer dependency do Bootstrap** (`bootstrap@5.x` usa `@popperjs/core` em dropdowns) — mantido e necessário. `@types/bootstrap` sem referência explícita, porém o `tsc --noEmit` falha sem ela (tipos do Bootstrap) — necessária. `@vitejs/plugin-basic-ssl` e `@fullhuman/postcss-purgecss`: referenciados em config (postcss.config.js) — necessários. `rimraf` usado no script `clean`. **Nenhuma dependência npm obsoleta detectada.** |
| Composer | `sentry/sentry` é a única de runtime; usada condicionalmente (inativa sem DSN) — aceitável. `roave/security-advisories` ativa (dev). `monolog/monolog` usada (`LoggerFactory`). |

### F-508 — Conflitos / licenças / compatibilidade

| Campo | Valor |
| ----- | ----- |
| Verificação | `composer validate` e `npm ls` sem erros de conflito de dependência. |
| Licenças | Composer: `composer validate --strict` OK (license MIT — commit `8cf95d0`). npm: sem licenças copyleft críticas no conjunto direto. |
| Compatibilidade PHP | PHPUnit 13 exige PHP 8.3+; ambiente atual é PHP 8.4.24 (CI usa 8.3) — OK para o major, mas postergado. |

## Registro de dependências (formato solicitado)

### Composer

| Pacote | Versão atual | Versão recomendada | Vulnerabilidade | Severidade | Compatibilidade | Impacto | Ação |
| ------ | ------------ | ------------------ | --------------- | ---------- | --------------- | ------- | ---- |
| phpstan/phpstan | 1.12.33 | 2.2.8 | — | — | Major | Baixo | Postergar (janela dedicada) |
| phpunit/phpunit | 10.5.64 | 13.3.1 | — | — | Major (3 saltos) | Baixo | Postergar |
| squizlabs/php_codesniffer | 3.13.6 | 4.0.4 | — | — | Major | Baixo | Postergar |
| sentry/sentry | 4.29.0 | 4.30.0 | — | — | Patch | Baixo | Atualizar na próxima manutenção |

### npm

| Pacote | Versão atual | Versão recomendada | Vulnerabilidade | Severidade | Compatibilidade | Impacto | Ação |
| ------ | ------------ | ------------------ | --------------- | ---------- | --------------- | ------- | ---- |
| undici (via jsdom) | 7.28.0 | 7.29.0 | 5 advisories (GHSA-8xcm…) | Alta | Patch | Baixo (só testes) | **Corrigido hoje** |
| vite | 8.0.16 | 8.2.1 | — | — | Minor | Baixo | `npm update` |
| vitest | 4.1.7 | 4.1.10 | — | — | Minor | Baixo | `npm update` |
| sass / sass-embedded | 1.101/1.100 | 1.102.0 | — | — | Minor | Baixo | `npm update` |
| jsdom | 29.1.1 | 30.0.1 | — | — | Major | Baixo (testes) | Sob demanda |
| typescript | 5.9.3 | 7.0.2 | — | — | Major | Médio | Sob demanda |
| @fortawesome/fontawesome-free | 6.7.2 | 7.3.1 | — | — | Major | Baixo | Sob demanda |

## Conclusão

- **Segurança**: 0 vulnerabilidades ativas (5 high corrigidas — F-501). `composer audit` limpo.
- **Abandonadas**: nenhuma detectada.
- **Sem uso**: nenhuma; `@popperjs/core`, `@types/bootstrap`, `sentry` têm justificativa.
- **Desatualizadas**: majors de tooling dev (PHPStan/PHPUnit/PHPCS) postergados; minors npm disponíveis para `npm update`.
- **Licenciamento**: OK (`composer validate --strict`).