# Projeto Finalização: Especificação Completa de Auditoria de Código

## 1. Visão Geral

Este documento define o protocolo oficial para auditoria completa, sistemática, reproduzível e baseada em evidências do projeto.

A auditoria deverá avaliar código-fonte, arquitetura, segurança, banco de dados, performance, dependências, infraestrutura, testes, documentação e manutenibilidade.

O processo deverá produzir evidências verificáveis para cada conclusão e estabelecer uma relação rastreável entre:

```text
Problema
    ↓
Evidência
    ↓
Causa raiz
    ↓
Risco
    ↓
Prioridade
    ↓
Recomendação
    ↓
Implementação
    ↓
Teste
    ↓
Validação
    ↓
Documentação
```

A auditoria não deverá presumir a existência de problemas. Um problema somente poderá ser registrado como confirmado quando houver evidência suficiente no código, configuração, execução, teste, dependência, infraestrutura ou documentação.

Quando não houver evidência suficiente, o achado deverá ser classificado como:

* Não confirmado
* Necessita evidência adicional
* Não aplicável
* Não auditado
* Bloqueado

---

## 2. Objetivos

A auditoria tem como objetivos:

* Identificar problemas reais e verificáveis.
* Avaliar riscos de segurança.
* Identificar gargalos de performance.
* Avaliar arquitetura e organização do sistema.
* Reduzir dívida técnica.
* Melhorar manutenibilidade.
* Melhorar testabilidade.
* Avaliar dependências e cadeia de software.
* Avaliar infraestrutura e Docker/DevOps.
* Verificar compatibilidade entre componentes.
* Estabelecer métricas objetivas.
* Produzir recomendações reproduzíveis.
* Preservar o comportamento funcional existente.
* Evitar alterações desnecessárias.
* Estabelecer um plano de evolução sustentável.

---

# 3. Princípios Fundamentais

## 3.1 Auditoria baseada em evidências

Toda conclusão deverá possuir evidência verificável.

São consideradas evidências válidas:

* Código-fonte.
* Arquivo de configuração.
* Linha específica de código.
* Log.
* Stack trace.
* Teste.
* Resultado de benchmark.
* Métrica.
* Consulta SQL.
* Resultado de ferramenta de análise.
* Vulnerabilidade conhecida.
* CVE.
* CWE.
* Documentação oficial.
* RFC.
* Configuração Docker.
* Manifesto de dependências.
* Histórico Git.

Não deverá ser utilizada como evidência apenas uma opinião, preferência pessoal ou "boa prática" sem justificativa técnica.

---

## 3.2 Fontes oficiais

Sempre que possível, as recomendações deverão utilizar fontes primárias:

* Documentação oficial da linguagem.
* Documentação oficial do framework.
* Documentação oficial das bibliotecas.
* Documentação oficial do banco de dados.
* Documentação oficial do Docker.
* OWASP.
* NIST.
* MITRE/CWE.
* RFCs.
* Documentação oficial do sistema operacional.
* Documentação oficial das ferramentas utilizadas.

Para segurança web, a auditoria deverá considerar o OWASP Top 10 vigente. Atualmente, a versão publicada pelo OWASP é a **Top 10:2025**.

Para verificação técnica de controles de segurança, deverá ser utilizado o OWASP ASVS, que fornece requisitos para avaliação de segurança de aplicações web.

Para desenvolvimento seguro, deverá ser considerado o NIST Secure Software Development Framework (SSDF). A versão final publicada é a 1.1, enquanto a revisão 1.2 estava em draft público em 2025.

---

# 4. Escopo

A auditoria poderá abranger:

```text
Código-fonte
Arquitetura
Backend
Frontend
Banco de dados
APIs
Autenticação
Autorização
Sessões
Uploads
Integrações
Dependências
Docker
DevOps
CI/CD
Logs
Testes
Documentação
Configuração
Sistema operacional
Performance
Histórico Git
```

O escopo efetivamente auditado deverá ser registrado no relatório de descoberta.

---

# 5. Fora do Escopo

Qualquer componente que não possa ser analisado deverá ser explicitamente registrado.

Exemplos:

* Serviço externo indisponível.
* Código proprietário não fornecido.
* Ambiente de produção inacessível.
* Banco de dados sem acesso.
* Dependência sem código-fonte disponível.
* Funcionalidade impossível de reproduzir.
* Ausência de credenciais de teste.
* Limitação de ferramenta.

Nenhum componente deverá ser considerado seguro apenas porque não pôde ser auditado.

---

# 6. Matriz do Ambiente

Antes da auditoria, deverá ser criada uma matriz contendo as tecnologias e versões utilizadas.

| Componente          | Tecnologia | Versão | Status | Fonte   |
| ------------------- | ---------- | ------ | ------ | ------- |
| Linguagem           | TBD        | TBD    | TBD    | Oficial |
| Framework           | TBD        | TBD    | TBD    | Oficial |
| Banco               | TBD        | TBD    | TBD    | Oficial |
| Runtime             | TBD        | TBD    | TBD    | Oficial |
| Docker              | TBD        | TBD    | TBD    | Oficial |
| Sistema operacional | TBD        | TBD    | TBD    | Oficial |

Também deverão ser identificados:

* versões suportadas;
* versões em fim de vida;
* incompatibilidades conhecidas;
* dependências críticas;
* versões mínimas necessárias;
* versões utilizadas em produção.

---

# 7. Baseline

Antes de qualquer alteração no código, deverá ser criada uma baseline.

A baseline deverá registrar, quando aplicável:

### Qualidade

* Complexidade ciclomática.
* Quantidade de arquivos.
* Quantidade de linhas.
* Quantidade de funções.
* Quantidade de classes.
* Código duplicado.
* Warnings do compilador/linter.
* Resultado de análise estática.

### Testes

* Quantidade de testes.
* Cobertura.
* Testes aprovados.
* Testes falhos.
* Testes ignorados.

### Performance

* Tempo de resposta.
* CPU.
* Memória.
* Consultas SQL.
* Quantidade de consultas.
* Latência.
* Throughput.

### Segurança

* Vulnerabilidades conhecidas.
* Dependências vulneráveis.
* Secrets encontrados.
* Configurações inseguras.

### Infraestrutura

* Tamanho das imagens Docker.
* Quantidade de camadas.
* Containers executando como root.
* Healthchecks.
* Volumes.
* Exposição de portas.

A baseline deverá permitir comparar o estado anterior e posterior às alterações.

---

# 8. Identificação dos Achados

Todo problema deverá possuir um identificador único.

Padrão:

```text
ARCH-001
BACK-001
FRONT-001
DB-001
SEC-001
PERF-001
QUAL-001
DEP-001
DEVOPS-001
TEST-001
DOC-001
```

Exemplo:

| ID       | Categoria   | Severidade | Confiança  | Status    |
| -------- | ----------- | ---------- | ---------- | --------- |
| SEC-001  | Segurança   | Alto       | Confirmado | Aberto    |
| PERF-001 | Performance | Médio      | Alta       | Aberto    |
| DB-001   | Banco       | Alto       | Confirmado | Corrigido |

O identificador deverá permanecer inalterado durante todo o ciclo de vida do achado.

---

# 9. Classificação de Severidade

Cada achado deverá receber uma classificação:

* Crítico
* Alto
* Médio
* Baixo
* Informativo

Quando aplicável a vulnerabilidades de segurança, deverá ser utilizada uma metodologia reconhecida, como CVSS, além da classificação contextual do projeto.

---

# 10. Impacto

Cada achado deverá possuir um ou mais tipos de impacto:

* Segurança.
* Performance.
* Escalabilidade.
* Disponibilidade.
* Integridade.
* Confidencialidade.
* Manutenibilidade.
* Legibilidade.
* Testabilidade.
* Compatibilidade.
* Operacional.
* Custo.

---

# 11. Probabilidade

A probabilidade deverá ser classificada:

| Valor | Probabilidade |
| ----: | ------------- |
|     1 | Rara          |
|     2 | Improvável    |
|     3 | Possível      |
|     4 | Provável      |
|     5 | Quase certa   |

---

# 12. Impacto Quantificado

O impacto deverá utilizar:

| Valor | Impacto        |
| ----: | -------------- |
|     1 | Insignificante |
|     2 | Baixo          |
|     3 | Moderado       |
|     4 | Alto           |
|     5 | Crítico        |

---

# 13. Prioridade

Quando aplicável:

```text
Risco = Impacto × Probabilidade
```

Classificação sugerida:

| Pontuação | Prioridade |
| --------: | ---------- |
|     20–25 | Crítica    |
|     15–19 | Alta       |
|      8–14 | Média      |
|       1–7 | Baixa      |

O esforço deverá ser avaliado separadamente.

---

# 14. Esforço

| Valor | Esforço     |
| ----: | ----------- |
|     1 | Muito baixo |
|     2 | Baixo       |
|     3 | Médio       |
|     4 | Alto        |
|     5 | Muito alto  |

Quando necessário, poderá ser calculado:

```text
ROI = Prioridade / Esforço
```

Esse indicador poderá auxiliar na seleção dos Quick Wins.

---

# 15. Confiança da Evidência

Cada achado deverá possuir um nível de confiança:

* Confirmado.
* Alta.
* Média.
* Baixa.
* Não confirmado.

Exemplo:

```text
SEC-001
Severidade: Alta
Impacto: Segurança
Probabilidade: Provável
Confiança: Confirmado
```

Hipóteses não deverão ser apresentadas como vulnerabilidades confirmadas.

---

# 16. Evidência

Cada evidência deverá registrar:

```text
ID do achado
Tipo da evidência
Arquivo
Linha
Função
Trecho
Comando utilizado
Resultado
Versão da tecnologia
Fonte oficial
URL
Data da consulta
```

Exemplo:

```text
ID: SEC-001
Arquivo: src/Auth/Login.php
Linha: 42
Função: authenticate()
Tipo: Código-fonte
Evidência: entrada do usuário utilizada diretamente na consulta
Fonte: documentação oficial do framework
Data: YYYY-MM-DD
```

---

# 17. Causa Raiz

Sempre que possível, cada problema deverá identificar:

```text
Sintoma
↓
Problema
↓
Causa raiz
↓
Impacto
↓
Correção
↓
Prevenção
```

Não deverá ser recomendado apenas tratar o sintoma quando a causa raiz puder ser identificada.

---

# 18. Processo Geral da Auditoria

O processo deverá seguir:

```text
FASE 0  Descoberta
    ↓
FASE 1  Inventário e Baseline
    ↓
FASE 2  Arquitetura
    ↓
FASE 3  Backend
    ↓
FASE 4  Frontend
    ↓
FASE 5  Banco de Dados
    ↓
FASE 6  Segurança
    ↓
FASE 7  Performance
    ↓
FASE 8  Qualidade
    ↓
FASE 9  Dependências
    ↓
FASE 10 Docker/DevOps
    ↓
FASE 11 Testes
    ↓
FASE 12 Documentação
    ↓
FASE 13 Consolidação
    ↓
FASE 14 Plano de Refatoração
    ↓
FASE 15 Implementação Controlada
    ↓
FASE 16 Validação
    ↓
FASE 17 Roadmap
```

---

# 19. Estrutura Obrigatória de Cada Fase

Cada fase deverá possuir:

1. Objetivo.
2. Escopo.
3. Entradas.
4. Procedimentos.
5. Ferramentas utilizadas.
6. Itens de verificação.
7. Evidências obrigatórias.
8. Métricas.
9. Achados.
10. Limitações.
11. Critério de conclusão.
12. Artefato gerado.

---

# 20. Fase 0 — Descoberta

## Objetivo

Compreender completamente o projeto antes de realizar alterações.

## Verificações

* Linguagens.
* Frameworks.
* Bibliotecas.
* Runtime.
* Banco de dados.
* APIs.
* Serviços externos.
* Estrutura de diretórios.
* Arquitetura.
* Docker.
* CI/CD.
* Sistema operacional.
* Ambiente de desenvolvimento.
* Ambiente de produção.
* Variáveis de ambiente.
* Dependências.

## Histórico Git

Quando disponível:

* branches;
* tags;
* releases;
* frequência de commits;
* arquivos mais modificados;
* hotspots;
* commits grandes;
* concentração de conhecimento;
* código abandonado.

## Entrega

`DISCOVERY.md`

---

# 21. Fase 1 — Inventário e Baseline

Deverá ser produzido um inventário completo do sistema e registrada a baseline.

Nenhuma alteração de código deverá ser realizada antes da criação da baseline, salvo correções necessárias para possibilitar a própria auditoria.

---

# 22. Fase 2 — Auditoria Arquitetural

Avaliar:

* organização;
* modularização;
* acoplamento;
* coesão;
* responsabilidades;
* dependências;
* abstrações;
* separação de responsabilidades;
* Clean Architecture;
* MVC;
* DDD;
* arquitetura hexagonal;
* padrões utilizados;
* antipadrões;
* dependências circulares;
* duplicação;
* limites entre módulos.

Deverá ser avaliado se a arquitetura utilizada é adequada ao tamanho e à complexidade real do projeto.

Não deverá ser recomendada uma arquitetura mais complexa apenas por preferência.

---

# 23. Fase 3 — Auditoria de Backend

Avaliar:

* Controllers.
* Services.
* Repositories.
* DTOs.
* Models.
* Entities.
* Validators.
* Middlewares.
* Exceptions.
* Logs.
* Transações.
* Concorrência.
* Async/Await, quando aplicável.
* Cache.
* Filas.
* APIs.
* Paginação.
* Validação de entrada.
* Tratamento de erros.

Verificar especialmente:

* métodos gigantes;
* responsabilidades múltiplas;
* duplicação;
* consultas inadequadas;
* regras de negócio espalhadas;
* tratamento incorreto de exceções.

---

# 24. Fase 4 — Auditoria de Frontend

Quando aplicável, avaliar:

* Componentes.
* Hooks.
* Context API.
* Estado global.
* Estado local.
* Re-renderizações.
* Memoização.
* Lazy loading.
* Code splitting.
* Bundle.
* Cache.
* Acessibilidade.
* Responsividade.
* Performance.
* WCAG.
* Tratamento de erros.
* Segurança no cliente.

---

# 25. Fase 5 — Auditoria de Banco de Dados

Avaliar:

* Modelagem.
* Normalização.
* Chaves primárias.
* Chaves estrangeiras.
* Constraints.
* Índices.
* Índices redundantes.
* Índices ausentes.
* Queries.
* JOINs.
* Subqueries.
* Paginação.
* Transações.
* Locking.
* Concorrência.
* Integridade referencial.
* N+1.
* Full table scans.
* Consultas repetidas.
* Migrações.

As recomendações deverão considerar a versão real do banco utilizada.

---

# 26. Fase 6 — Auditoria de Segurança

A auditoria de segurança deverá utilizar, conforme aplicável:

* OWASP Top 10.
* OWASP ASVS.
* OWASP API Security Top 10.
* CWE.
* CVE.
* NIST SSDF.
* Documentação oficial das tecnologias.

O OWASP API Security Top 10 2023 inclui, entre outros, Broken Object Level Authorization, Broken Authentication, Broken Object Property Level Authorization, Unrestricted Resource Consumption, Broken Function Level Authorization e SSRF.

## Verificações

### Entrada

* SQL Injection.
* Command Injection.
* XSS.
* Path Traversal.
* LDAP Injection.
* Template Injection.
* Deserialização insegura.

### Autenticação

* Senhas.
* MFA, quando aplicável.
* Sessões.
* Tokens.
* JWT.
* Expiração.
* Revogação.
* Brute force.
* Rate limiting.

### Autorização

* IDOR/BOLA.
* Privilege escalation.
* Broken Function Level Authorization.
* Broken Object Property Level Authorization.

### API

* Rate limiting.
* Exposição de dados.
* Inventário de endpoints.
* Métodos HTTP.
* CORS.
* SSRF.
* Consumo de APIs externas.

### Dados

* Secrets.
* Credenciais.
* Chaves.
* Tokens.
* Dados sensíveis.
* Criptografia.
* Hashing.
* Logs contendo informações sensíveis.

### Upload

* Validação MIME.
* Extensão.
* Tamanho.
* Nome de arquivo.
* Path traversal.
* Armazenamento.
* Execução de arquivos.

### Configuração

* Headers.
* Cookies.
* TLS.
* CORS.
* Debug.
* Mensagens de erro.
* Configurações padrão.

---

# 27. Fase 7 — Auditoria de Performance

Avaliar:

* Algoritmos.
* Complexidade.
* Loops.
* Recursão.
* CPU.
* Memória.
* I/O.
* Cache.
* Concorrência.
* Queries.
* N+1.
* Serialização.
* Deserialização.
* Network.
* Latência.

Nenhuma otimização deverá ser recomendada apenas com base em suposição.

Quando possível, deverá existir:

```text
Métrica antes
Métrica depois
Diferença
Condições do teste
```

---

# 28. Fase 8 — Auditoria de Qualidade

Avaliar:

* Clean Code.
* SOLID.
* DRY.
* KISS.
* YAGNI.
* Naming.
* Complexidade.
* Coesão.
* Acoplamento.
* Métodos gigantes.
* Classes gigantes.
* Número de parâmetros.
* Aninhamento.
* Duplicação.
* Código morto.
* Comentários obsoletos.

Uma recomendação de refatoração deverá ser feita somente quando houver benefício técnico real.

---

# 29. Fase 9 — Auditoria de Dependências

Avaliar:

* Dependências desatualizadas.
* Vulnerabilidades.
* CVEs.
* Dependências abandonadas.
* Dependências sem uso.
* Dependências duplicadas.
* Conflitos.
* Licenciamento, quando aplicável.
* Compatibilidade.
* Dependências transitivas.

Registrar:

```text
Pacote
Versão atual
Versão recomendada
Vulnerabilidade
Severidade
Compatibilidade
Impacto
Ação
```

---

# 30. Fase 10 — Auditoria Docker/DevOps

Avaliar:

* Dockerfile.
* Multi-stage builds.
* Imagens base.
* Tags.
* Usuário root.
* Healthcheck.
* Secrets.
* Volumes.
* Networks.
* Portas.
* Variáveis.
* Cache de build.
* Tamanho da imagem.
* Pacotes desnecessários.
* Reprodutibilidade.
* CI/CD.
* Logs.
* Observabilidade.

As recomendações deverão considerar a documentação oficial do Docker e a versão efetivamente utilizada.

---

# 31. Fase 11 — Auditoria de Testes

Avaliar:

* Testes unitários.
* Testes de integração.
* Testes E2E.
* Testes de API.
* Testes de segurança.
* Testes de regressão.
* Casos extremos.
* Testes negativos.
* Mocks.
* Fixtures.
* Cobertura.

Cobertura de código não deverá ser tratada isoladamente como indicador de qualidade.

Deverá ser avaliada também a qualidade dos cenários testados.

---

# 32. Fase 12 — Auditoria de Documentação

Avaliar:

* README.
* Instalação.
* Configuração.
* Desenvolvimento.
* Deploy.
* Rollback.
* Backup.
* API.
* Banco.
* Arquitetura.
* Troubleshooting.
* Variáveis de ambiente.
* Segurança.

A documentação deverá permitir que outro desenvolvedor consiga configurar e executar o projeto sem depender de conhecimento informal.

---

# 33. Fase 13 — Consolidação

Todos os achados deverão ser consolidados em uma matriz.

| ID      | Categoria | Severidade | Impacto | Probabilidade | Esforço | Prioridade | Confiança  | Status |
| ------- | --------- | ---------- | ------- | ------------- | ------- | ---------- | ---------- | ------ |
| SEC-001 | Segurança | Alto       | 5       | 4             | 2       | 20         | Confirmado | Aberto |

A matriz será a fonte principal para o planejamento das correções.

---

# 34. Matriz de Cobertura

Cada item auditado deverá possuir um estado:

* Conforme.
* Não conforme.
* Não aplicável.
* Não confirmado.
* Não auditado.
* Bloqueado.

Exemplo:

| Controle      | Status         | Evidência |
| ------------- | -------------- | --------- |
| SQL Injection | Conforme       | SEC-001   |
| XSS           | Não conforme   | SEC-002   |
| SSRF          | Não confirmado | SEC-003   |
| CSRF          | Não aplicável  | SEC-004   |

---

# 35. Fase 14 — Plano de Refatoração

A refatoração deverá ser separada da auditoria.

Para cada alteração:

1. Identificar problema.
2. Identificar causa raiz.
3. Avaliar impacto.
4. Definir solução.
5. Avaliar compatibilidade.
6. Criar plano.
7. Implementar.
8. Testar.
9. Medir.
10. Validar.
11. Atualizar documentação.

Nenhuma alteração deverá ser aplicada em lote sem validação intermediária.

---

# 36. Formato Obrigatório de Cada Recomendação

Cada recomendação deverá conter:

## 1. ID

Identificador único.

## 2. Problema

Descrição técnica objetiva.

## 3. Localização

Informar:

* arquivo;
* linha;
* função;
* classe;
* módulo.

## 4. Evidência

Apresentar a evidência verificável.

## 5. Fonte

Indicar documentação oficial, OWASP, NIST, CWE, RFC ou outra fonte primária.

## 6. Situação Atual

Explicar o comportamento atual.

## 7. Causa Raiz

Explicar por que o problema ocorre.

## 8. Impacto

Explicar consequências técnicas e operacionais.

## 9. Implementação Recomendada

Descrever a solução.

## 10. Código Atual

Quando aplicável, apresentar o trecho existente.

## 11. Código Sugerido

Apresentar a implementação recomendada.

Para mudanças exclusivamente relacionadas a configuração, dependências, infraestrutura ou documentação, apresentar o diff/configuração correspondente.

## 12. Benefícios

Avaliar:

* Segurança.
* Performance.
* Legibilidade.
* Escalabilidade.
* Manutenção.
* Testabilidade.

## 13. Riscos

Descrever possíveis efeitos colaterais.

## 14. Compatibilidade

Avaliar:

* API.
* Banco.
* Dependências.
* Runtime.
* Sistema operacional.
* Docker.
* Produção.

## 15. Testes

Definir como validar a correção.

## 16. Métrica

Definir como medir o resultado.

## 17. Status

* Aberto.
* Em análise.
* Em implementação.
* Corrigido.
* Validado.
* Rejeitado.
* Não aplicável.

---

# 37. Definition of Done

Uma correção somente será considerada concluída quando:

* [ ] Problema reproduzido ou confirmado.
* [ ] Evidência registrada.
* [ ] Causa raiz identificada.
* [ ] Correção implementada.
* [ ] Testes executados.
* [ ] Testes existentes continuam passando.
* [ ] Segurança reavaliada.
* [ ] Performance reavaliada quando aplicável.
* [ ] Compatibilidade validada.
* [ ] Métricas comparadas.
* [ ] Documentação atualizada.
* [ ] Diff revisado.
* [ ] Finding atualizado.
* [ ] Changelog atualizado quando aplicável.

---

# 38. Processo Controlado de Implementação

As alterações deverão seguir obrigatoriamente:

```text
1. Identificar problema
2. Registrar evidência
3. Identificar causa raiz
4. Classificar risco
5. Priorizar
6. Elaborar solução
7. Registrar baseline
8. Alterar uma unidade por vez
9. Executar testes
10. Comparar métricas
11. Validar compatibilidade
12. Atualizar documentação
13. Atualizar finding
14. Somente então iniciar próxima alteração
```

---

# 39. Regras de Preservação

As alterações deverão:

* Preservar comportamento funcional.
* Preservar APIs públicas quando possível.
* Preservar contratos existentes.
* Evitar mudanças desnecessárias.
* Evitar refatoração cosmética.
* Evitar introduzir dependências sem necessidade.
* Evitar aumentar a complexidade arquitetural sem justificativa.

Alterações incompatíveis deverão ser explicitamente identificadas.

---

# 40. Critérios de Aceitação

Uma recomendação somente deverá ser aprovada quando responder positivamente, ou possuir justificativa documentada, para:

* Resolve um problema real?
* Existe evidência?
* A causa raiz foi identificada?
* Reduz risco?
* Melhora segurança?
* Melhora performance?
* Reduz complexidade?
* Reduz dívida técnica?
* Melhora manutenibilidade?
* Está alinhada à documentação oficial?
* Mantém compatibilidade?
* Possui estratégia de teste?
* O benefício supera o custo?

---

# 41. Quick Wins

Serão classificados como Quick Wins os itens que:

* possuem baixo esforço;
* possuem benefício comprovado;
* apresentam baixo risco de regressão;
* podem ser implementados rapidamente.

Por padrão:

```text
Esforço <= 2
Prioridade >= Média
```

O limite poderá ser ajustado conforme o projeto.

---

# 42. Métricas de Qualidade

Quando aplicável, registrar:

* Complexidade ciclomática.
* Complexidade cognitiva.
* Linhas de código.
* Tamanho de classes.
* Tamanho de funções.
* Número de parâmetros.
* Profundidade de herança.
* Acoplamento.
* Coesão.
* Duplicação.
* Código morto.
* Warnings.
* Débitos técnicos.

---

# 43. Métricas de Performance

Registrar, quando aplicável:

* Tempo médio.
* P95.
* P99.
* Throughput.
* CPU.
* Memória.
* I/O.
* Latência.
* Número de queries.
* Queries lentas.
* Cache hit ratio.
* Tempo de inicialização.

Toda comparação deverá informar as condições do teste.

---

# 44. Métricas de Segurança

Registrar:

* Vulnerabilidades críticas.
* Vulnerabilidades altas.
* Vulnerabilidades médias.
* Vulnerabilidades baixas.
* CVEs.
* CWE.
* Secrets.
* Controles ASVS.
* Controles OWASP.
* Dependências vulneráveis.

---

# 45. Métricas de Manutenibilidade

Avaliar:

* Legibilidade.
* Organização.
* Documentação.
* Acoplamento.
* Coesão.
* Complexidade.
* Testabilidade.
* Facilidade de alteração.
* Concentração de conhecimento.

---

# 46. Análise Automatizada

Sempre que houver ferramentas adequadas, deverão ser executadas:

* Linter.
* Formatter/check.
* Static Analysis.
* SAST.
* Dependency Audit.
* Secret Scan.
* Container Scan.
* Testes.
* Coverage.
* Benchmark.
* DAST, quando aplicável.

Cada execução deverá registrar:

```text
Ferramenta
Versão
Comando
Data
Ambiente
Resultado
Artefato
```

---

# 47. Reprodução

Todo resultado importante deverá ser reproduzível.

Exemplo:

```text
Ferramenta:
PHPStan 2.x

Comando:
phpstan analyse

Ambiente:
PHP 8.2.x

Resultado:
...

Data:
YYYY-MM-DD
```

Quando um teste manual for necessário, registrar:

1. Pré-condições.
2. Entrada.
3. Procedimento.
4. Resultado esperado.
5. Resultado obtido.

---

# 48. Controle de Falso Positivo

Nenhum alerta automatizado deverá ser considerado vulnerabilidade confirmada sem validação.

Processo:

```text
Alerta
 ↓
Triagem
 ↓
Validação
 ↓
Evidência
 ↓
Classificação
```

Resultados possíveis:

* Confirmado.
* Falso positivo.
* Não confirmado.
* Necessita investigação.

---

# 49. Limitações

O relatório final deverá possuir uma seção de limitações.

Deverão ser registrados:

* componentes não disponíveis;
* funcionalidades não executadas;
* ambientes inacessíveis;
* ferramentas indisponíveis;
* testes impossíveis;
* dados insuficientes;
* dependências externas;
* restrições de tempo;
* restrições de acesso.

A auditoria nunca deverá afirmar que um sistema é "100% seguro".

A conclusão deverá ser limitada ao escopo, ambiente e evidências efetivamente analisados.

---

# 50. Artefatos Obrigatórios

Ao término da auditoria deverão existir:

```text
DISCOVERY.md
AUDIT_REPORT.md
SECURITY_REPORT.md
PERFORMANCE_REPORT.md
TECH_DEBT.md
REFACTORING_PLAN.md
ROADMAP.md
CHANGELOG_SUGERIDO.md
DEPENDENCY_AUDIT.md
TEST_PLAN.md
ARCHITECTURE_REVIEW.md
AUDIT_MATRIX.md
EVIDENCE_INDEX.md
```

---

# 51. Estrutura dos Relatórios

Cada relatório deverá conter:

```text
1. Introdução
2. Objetivo
3. Escopo
4. Metodologia
5. Ambiente
6. Evidências
7. Achados
8. Métricas
9. Limitações
10. Conclusões
11. Recomendações
12. Referências
```

---

# 52. AUDIT_REPORT.md

Deverá conter:

* Resumo executivo.
* Nota geral.
* Matriz de riscos.
* Principais problemas.
* Quick Wins.
* Top 5 prioridades.
* Estado da arquitetura.
* Estado da segurança.
* Estado da performance.
* Estado dos testes.
* Estado da documentação.
* Dívida técnica.
* Plano de evolução.

---

# 53. SECURITY_REPORT.md

Deverá conter:

* Metodologia.
* OWASP Top 10.
* OWASP ASVS.
* OWASP API Security.
* CWE.
* CVE.
* Autenticação.
* Autorização.
* Sessões.
* Input validation.
* APIs.
* Upload.
* Secrets.
* Criptografia.
* Configuração.
* Logging.
* Matriz de vulnerabilidades.

---

# 54. PERFORMANCE_REPORT.md

Deverá conter:

* Baseline.
* Metodologia.
* Benchmarks.
* Gargalos.
* CPU.
* Memória.
* Banco.
* I/O.
* Cache.
* Latência.
* Comparações antes/depois.
* Recomendações.

---

# 55. TECH_DEBT.md

Deverá classificar dívida técnica por:

* impacto;
* prioridade;
* esforço;
* causa;
* custo estimado;
* risco de não corrigir.

---

# 56. REFACTORING_PLAN.md

Deverá conter:

* Finding.
* Objetivo.
* Arquivos.
* Estratégia.
* Dependências.
* Riscos.
* Testes.
* Métricas.
* Ordem de execução.
* Critério de conclusão.

---

# 57. ROADMAP.md

Dividir em:

## Curto prazo

Correções críticas e Quick Wins.

## Médio prazo

Refatorações estruturais e redução significativa de dívida técnica.

## Longo prazo

Evolução arquitetural, observabilidade, escalabilidade e modernização.

---

# 58. DEPENDENCY_AUDIT.md

Deverá registrar:

| Pacote | Atual | Recomendado | CVE | Severidade | Compatibilidade | Ação |
| ------ | ----- | ----------- | --- | ---------- | --------------- | ---- |

---

# 59. TEST_PLAN.md

Deverá definir:

* testes unitários;
* integração;
* E2E;
* API;
* segurança;
* regressão;
* performance;
* casos extremos;
* cenários negativos.

Cada teste deverá possuir:

```text
ID
Objetivo
Pré-condição
Entrada
Procedimento
Resultado esperado
Resultado obtido
Status
```

---

# 60. ARCHITECTURE_REVIEW.md

Deverá conter:

* arquitetura atual;
* componentes;
* dependências;
* fluxos;
* pontos de acoplamento;
* pontos de coesão;
* riscos;
* limitações;
* arquitetura recomendada;
* justificativa;
* impacto da migração.

Nenhuma arquitetura deverá ser recomendada apenas por tendência tecnológica.

---

# 61. AUDIT_MATRIX.md

Deverá funcionar como índice central de todos os controles.

| ID | Área | Controle | Resultado | Evidência | Finding |
| -- | ---- | -------- | --------- | --------- | ------- |

Esse documento deverá permitir verificar rapidamente se toda a auditoria foi coberta.

---

# 62. EVIDENCE_INDEX.md

Deverá funcionar como índice de evidências.

| ID | Finding | Arquivo | Linha | Evidência | Fonte | Data |
| -- | ------- | ------- | ----: | --------- | ----- | ---- |

---

# 63. Nota Geral do Projeto

A nota geral deverá ser calculada a partir de categorias objetivas.

| Categoria        | Nota |
| ---------------- | ---: |
| Arquitetura      |  TBD |
| Segurança        |  TBD |
| Performance      |  TBD |
| Qualidade        |  TBD |
| Testabilidade    |  TBD |
| Documentação     |  TBD |
| Manutenibilidade |  TBD |
| DevOps           |  TBD |
| Dependências     |  TBD |

A metodologia de cálculo deverá ser documentada no relatório.

A nota não deverá substituir a análise dos achados.

---

# 64. Critérios de Finalização

A auditoria será considerada concluída somente quando:

1. Todas as fases forem executadas.
2. O escopo estiver documentado.
3. O ambiente estiver documentado.
4. A baseline estiver registrada.
5. A matriz de auditoria estiver preenchida.
6. Todos os achados possuírem ID.
7. Os achados possuírem evidências.
8. Os achados possuírem classificação.
9. Os falsos positivos forem tratados.
10. As limitações forem documentadas.
11. Os relatórios obrigatórios forem gerados.
12. As recomendações forem priorizadas.
13. O plano de refatoração estiver definido.
14. Os critérios de aceitação estiverem atendidos.
15. Os testes necessários forem executados.
16. As alterações forem validadas.
17. A documentação estiver atualizada.
18. O roadmap estiver definido.

---

# 65. Regra Fundamental da Auditoria

O auditor deverá seguir rigorosamente:

> **Não inventar problemas. Não ocultar problemas. Não presumir evidências. Não alterar o sistema sem justificativa. Não considerar uma correção concluída sem validação.**

Toda conclusão deverá responder:

```text
O que foi encontrado?
Onde está?
Como foi comprovado?
Qual é a causa?
Qual é o impacto?
Qual é o risco?
Qual é a prioridade?
Como corrigir?
Como testar?
Como medir?
Como comprovar que foi corrigido?
```

---

# 66. Referências Oficiais

As fontes oficiais deverão ser priorizadas durante a auditoria.

* [OWASP Top 10:2025](https://owasp.org/Top10/?utm_source=chatgpt.com)
* [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/?utm_source=chatgpt.com)
* [OWASP API Security Top 10](https://owasp.org/API-Security/?utm_source=chatgpt.com)
* [MITRE CWE](https://cwe.mitre.org/?utm_source=chatgpt.com)
* [NIST SSDF](https://csrc.nist.gov/Projects/ssdf/publications?utm_source=chatgpt.com)
* [RFC Editor](https://www.rfc-editor.org/?utm_source=chatgpt.com)
* [Docker Documentation](https://docs.docker.com/?utm_source=chatgpt.com)
* [markdownlint — regras de Markdown](https://github.com/DavidAnson/markdownlint/blob/main/doc/Rules.md?utm_source=chatgpt.com)

---

# 67. Resultado Esperado

Ao final do processo, o projeto deverá possuir uma visão objetiva de:

```text
ESTADO ATUAL
     ↓
PROBLEMAS REAIS
     ↓
EVIDÊNCIAS
     ↓
RISCOS
     ↓
PRIORIDADES
     ↓
SOLUÇÕES
     ↓
IMPLEMENTAÇÃO
     ↓
TESTES
     ↓
MÉTRICAS
     ↓
VALIDAÇÃO
     ↓
ROADMAP
```

O resultado final deverá ser um conjunto de documentos tecnicamente defensáveis, reproduzíveis e rastreáveis, permitindo que qualquer desenvolvedor ou auditor competente consiga compreender:

* o que foi analisado;
* como foi analisado;
* quais problemas foram encontrados;
* quais problemas não foram encontrados;
* quais problemas não puderam ser confirmados;
* quais evidências sustentam cada conclusão;
* quais alterações devem ser realizadas;
* quais alterações já foram realizadas;
* como cada alteração foi validada;
* quais riscos permanecem;
* e qual deverá ser a evolução futura do projeto.

---

# 68. Instrução Final ao Auditor

Siga rigorosamente esta especificação.

A auditoria deverá ser:

* Evidence-based.
* Reproduzível.
* Mensurável.
* Rastreável.
* Conservadora.
* Tecnicamente justificável.
* Orientada a risco.
* Orientada a impacto.
* Orientada à redução de dívida técnica.

**Nunca declarar um problema sem evidência suficiente.**

**Nunca considerar uma correção concluída sem validação.**

**Nunca modificar comportamento funcional sem justificativa explícita.**

**Sempre priorizar documentação oficial e fontes primárias.**

**Sempre registrar limitações.**

**Sempre preservar a rastreabilidade entre finding, evidência, alteração, teste e validação.**
