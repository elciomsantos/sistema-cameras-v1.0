import { describe, it, expect } from 'vitest';

// Smoke Test - Confirmação de artefatos criados
// Estes testes validam que os scripts de smoke test foram criados
// como parte do plano de correções da auditoria.

describe('Smoke Test - Artefatos de Teste', () => {
  it('confirma criação do smoke test bash', () => {
    // Script foi criado em tests/smoke_basico.sh durante o trabalho de F-705
    const fs = require('fs');
    const exists = fs.existsSync('./tests/smoke_basico.sh');
    expect(exists).toBe(true);
  });

  it('confirma criação do smoke test PowerShell', () => {
    // Script foi criado em tests/smoke_basico.ps1 durante o trabalho de F-705
    const fs = require('fs');
    const exists = fs.existsSync('./tests/smoke_basico.ps1');
    expect(exists).toBe(true);
  });

  it('confirma criação do teste Vitest', () => {
    // Teste Vitest foi criado em tests/Js/smoke-api.test.js durante o trabalho de F-705
    const fs = require('fs');
    const exists = fs.existsSync('./tests/Js/smoke-api.test.js');
    expect(exists).toBe(true);
  });
});
