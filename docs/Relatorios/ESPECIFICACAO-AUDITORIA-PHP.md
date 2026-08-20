# Especificação Final — Auditoria Completa de Projeto PHP

## 1. Visão Geral

Este documento define o protocolo oficial para auditoria completa, sistemática, reproduzível e baseada em evidências de um projeto desenvolvido em **PHP**.

A auditoria deverá avaliar, conforme aplicável:

- código PHP;
- framework;
- arquitetura;
- Composer e dependências;
- banco de dados;
- APIs;
- autenticação;
- autorização;
- sessões;
- CSRF;
- uploads;
- segurança;
- performance;
- testes;
- infraestrutura;
- Docker;
- Apache/Nginx;
- sistema operacional;
- configuração;
- documentação;
- manutenibilidade;
- cadeia de software.

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

A auditoria não deverá presumir a existência de problemas.

Um problema somente poderá ser registrado como **confirmado** quando houver evidência suficiente no código, configuração, execução, teste, dependência, infraestrutura ou documentação.

Quando não houver evidência suficiente, o achado deverá ser classificado como:

- Não confirmado;
- Necessita evidência adicional;
- Não aplicável;
- Não auditado;
- Bloqueado.

---

# 2. Objetivos

A auditoria tem como objetivos:

- identificar problemas reais e verificáveis;
- avaliar riscos de segurança;
- identificar gargalos de performance;
- avaliar arquitetura e organização do sistema;
- reduzir dívida técnica;
- melhorar manutenibilidade;
- melhorar testabilidade;
- avaliar dependências e cadeia de software;
- avaliar infraestrutura e Docker/DevOps;
- verificar compatibilidade entre componentes;
- estabelecer métricas objetivas;
- produzir recomendações reproduzíveis;
- preservar o comportamento funcional existente;
- evitar alterações desnecessárias;
- estabelecer um plano de evolução sustentável.

---

# 3. Princípios Fundamentais

## 3.1 Auditoria baseada em evidências

Toda conclusão deverá possuir evidência verificável.

São consideradas evidências válidas:

- código-fonte;
- arquivo de configuração;
- linha específica de código;
- função;
- classe;
- log;
- stack trace;
- teste;
- resultado de benchmark;
- métrica;
- consulta SQL;
- resultado de ferramenta de análise;
- vulnerabilidade conhecida;
- CVE;
- CWE;
- documentação oficial;
- RFC;
- configuração Docker;
- manifesto de dependências;
- `composer.json`;
- `composer.lock`;
- histórico Git.

Não deverá ser utilizada como evidência apenas uma opinião, preferência pessoal ou "boa prática" sem justificativa técnica.

---

## 3.2 Fontes oficiais

Sempre que possível, as recomendações deverão utilizar fontes primárias:

- documentação oficial do PHP;
- documentação oficial do framework utilizado;
- documentação oficial do Composer;
- documentação oficial das bibliotecas;
- documentação oficial do banco de dados;
- documentação oficial do Docker;
- documentação oficial do Apache ou Nginx;
- OWASP;
- NIST;
- MITRE/CWE;
- RFCs;
- documentação oficial do sistema operacional.

Para segurança web, considerar o **OWASP Top 10 vigente** e o **OWASP ASVS**.

Para APIs, considerar o **OWASP API Security Top 10**, quando aplicável.

Para desenvolvimento seguro, considerar o **NIST Secure Software Development Framework (SSDF)**.

---

# 4. Escopo

A auditoria poderá abranger:

```text
Código PHP
Arquitetura
Framework
Composer
Dependências
Extensões PHP
Backend
Frontend
Banco de dados
APIs
Autenticação
Autorização
Sessões
CSRF
Cookies
Uploads
Integrações
Docker
Apache/Nginx
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

- serviço externo indisponível;
- código proprietário não fornecido;
- ambiente de produção inacessível;
- banco de dados sem acesso;
- dependência sem código-fonte disponível;
- funcionalidade impossível de reproduzir;
- ausência de credenciais de teste;
- limitação de ferramenta;
- ambiente não disponível.

Nenhum componente deverá ser considerado seguro apenas porque não pôde ser auditado.

---

# 6. Matriz do Ambiente PHP

Antes da auditoria, deverá ser criada uma matriz contendo as tecnologias e versões utilizadas.

| Componente | Tecnologia | Versão | Status | Fonte |
|---|---|---:|---|---|
| Linguagem | PHP | TBD | TBD | Oficial |
| Framework | TBD | TBD | TBD | Oficial |
| Composer | Composer | TBD | TBD | Oficial |
| Banco | TBD | TBD | TBD | Oficial |
| Web Server | Apache/Nginx | TBD | TBD | Oficial |
| PHP Runtime | CLI/FPM/Apache Module | TBD | TBD | Oficial |
| Docker | Docker Engine | TBD | TBD | Oficial |
| Sistema operacional | TBD | TBD | TBD | Oficial |

Também deverão ser identificados:

- versões suportadas;
- versões em fim de vida;
- incompatibilidades conhecidas;
- dependências críticas;
- versões mínimas necessárias;
- versões utilizadas em produção;
- extensões PHP obrigatórias;
- diferenças entre desenvolvimento, homologação e produção.

---

# 7. Inventário PHP

Deverá ser identificado, quando aplicável:

```text
PHP
Composer
Framework
composer.json
composer.lock
Extensões PHP
php.ini
PHP-FPM
Apache
Nginx
Banco
Dockerfile
docker-compose.yml
.env
Configurações
Testes
Scripts
CI/CD
```

As versões deverão ser obtidas preferencialmente por execução direta no ambiente.

Exemplos:

```bash
php -v
php -m
php --ini
composer --version
composer show
```

Quando Docker for utilizado, a versão efetivamente executada dentro do container deverá ser considerada, e não apenas a versão instalada no host.

---

# 8. Baseline

Antes de qualquer alteração no código, deverá ser criada uma baseline.

A baseline deverá registrar, quando aplicável:

## Qualidade

- complexidade ciclomática;
- complexidade cognitiva;
- quantidade de arquivos;
- quantidade de linhas;
- quantidade de funções;
- quantidade de classes;
- código duplicado;
- warnings;
- resultado de análise estática;
- violações de padrão.

## PHP

- versão;
- extensões;
- configuração relevante;
- modo de execução;
- OPcache;
- PHP-FPM, quando utilizado.

## Composer

- versão;
- dependências diretas;
- dependências transitivas;
- estado do `composer.lock`;
- vulnerabilidades;
- scripts;
- plugins;
- repositórios personalizados.

## Testes

- quantidade de testes;
- cobertura;
- testes aprovados;
- testes falhos;
- testes ignorados.

## Performance

- tempo de resposta;
- CPU;
- memória;
- consultas SQL;
- quantidade de consultas;
- latência;
- throughput.

## Segurança

- vulnerabilidades conhecidas;
- dependências vulneráveis;
- secrets encontrados;
- configurações inseguras;
- exposição de serviços.

## Infraestrutura

- tamanho das imagens Docker;
- containers executando como root;
- healthchecks;
- volumes;
- redes;
- portas expostas;
- permissões;
- configurações de produção.

A baseline deverá permitir comparar o estado anterior e posterior às alterações.

---

# 9. Identificação dos Achados

Todo problema deverá possuir um identificador único.

Padrão:

```text
ARCH-001
PHP-001
BACK-001
FRONT-001
DB-001
SEC-001
AUTH-001
SESSION-001
API-001
UPLOAD-001
PERF-001
QUAL-001
DEP-001
COMPOSER-001
DEVOPS-001
TEST-001
DOC-001
```

O identificador deverá permanecer inalterado durante todo o ciclo de vida do achado.

---

# 10. Classificação de Severidade

Cada achado deverá receber uma classificação:

- Crítico;
- Alto;
- Médio;
- Baixo;
- Informativo.

Quando aplicável a vulnerabilidades de segurança, deverá ser utilizada uma metodologia reconhecida, como CVSS, além da classificação contextual do projeto.

---

# 11. Impacto

Cada achado deverá possuir um ou mais tipos de impacto:

- Segurança;
- Performance;
- Escalabilidade;
- Disponibilidade;
- Integridade;
- Confidencialidade;
- Manutenibilidade;
- Legibilidade;
- Testabilidade;
- Compatibilidade;
- Operacional;
- Custo.

---

# 12. Probabilidade

A probabilidade deverá ser classificada:

| Valor | Probabilidade |
|---:|---|
| 1 | Rara |
| 2 | Improvável |
| 3 | Possível |
| 4 | Provável |
| 5 | Quase certa |

---

# 13. Impacto Quantificado

O impacto deverá utilizar:

| Valor | Impacto |
|---:|---|
| 1 | Insignificante |
| 2 | Baixo |
| 3 | Moderado |
| 4 | Alto |
| 5 | Crítico |

---

# 14. Prioridade

Quando aplicável:

```text
Risco = Impacto × Probabilidade
```

Classificação sugerida:

| Pontuação | Prioridade |
|---:|---|
| 20–25 | Crítica |
| 15–19 | Alta |
| 8–14 | Média |
| 1–7 | Baixa |

O esforço deverá ser avaliado separadamente.

---

# 15. Esforço

| Valor | Esforço |
|---:|---|
| 1 | Muito baixo |
| 2 | Baixo |
| 3 | Médio |
| 4 | Alto |
| 5 | Muito alto |

Quando necessário:

```text
ROI = Prioridade / Esforço
```

Esse indicador poderá auxiliar na seleção de Quick Wins.

---

# 16. Confiança da Evidência

Cada achado deverá possuir um nível de confiança:

- Confirmado;
- Alta;
- Média;
- Baixa;
- Não confirmado.

Hipóteses não deverão ser apresentadas como vulnerabilidades confirmadas.

---

# 17. Evidência

Cada evidência deverá registrar:

```text
ID do achado
Tipo da evidência
Arquivo
Linha
Função
Classe
Módulo
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
Evidência: entrada do usuário utilizada diretamente em consulta SQL
Fonte: documentação oficial do PHP/PDO
Data: YYYY-MM-DD
```

---

# 18. Causa Raiz

Sempre que possível:

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

# 19. Processo Geral da Auditoria

O processo deverá seguir:

```text
FASE 0  Descoberta
    ↓
FASE 1  Inventário e Baseline
    ↓
FASE 2  Arquitetura
    ↓
FASE 3  PHP e Backend
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
FASE 9  Composer e Dependências
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

**A fase somente será executada quando for aplicável ao projeto.**

Fases não aplicáveis deverão ser registradas e justificadas.

---

# 20. Estrutura Obrigatória de Cada Fase

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

# 21. Fase 0 — Descoberta

## Objetivo

Compreender completamente o projeto antes de realizar alterações.

## Verificações

- linguagens;
- PHP;
- framework;
- bibliotecas;
- Composer;
- banco de dados;
- APIs;
- serviços externos;
- estrutura de diretórios;
- arquitetura;
- Docker;
- CI/CD;
- sistema operacional;
- ambiente de desenvolvimento;
- ambiente de produção;
- variáveis de ambiente;
- dependências.

## Histórico Git

Quando disponível:

- branches;
- tags;
- releases;
- frequência de commits;
- arquivos mais modificados;
- hotspots;
- commits grandes;
- concentração de conhecimento;
- código abandonado.

## Entrega

`DISCOVERY.md`

---

# 22. Fase 1 — Inventário e Baseline

Deverá ser produzido um inventário completo do sistema e registrada a baseline.

Nenhuma alteração de código deverá ser realizada antes da criação da baseline, salvo correções necessárias para possibilitar a própria auditoria.

---

# 23. Fase 2 — Auditoria Arquitetural

Avaliar:

- organização;
- modularização;
- acoplamento;
- coesão;
- responsabilidades;
- dependências;
- abstrações;
- separação de responsabilidades;
- MVC;
- DDD;
- Clean Architecture;
- arquitetura hexagonal;
- padrões utilizados;
- antipadrões;
- dependências circulares;
- duplicação;
- limites entre módulos.

A arquitetura deverá ser avaliada de acordo com o tamanho e a complexidade real do projeto.

Não deverá ser recomendada uma arquitetura mais complexa apenas por preferência ou tendência tecnológica.

---

# 24. Fase 3 — Auditoria PHP e Backend

Avaliar:

- Controllers;
- Services;
- Repositories;
- Models;
- Entities;
- DTOs, quando utilizados;
- Validators;
- Middlewares;
- Exceptions;
- Logs;
- Transações;
- Concorrência;
- Cache;
- Filas, quando utilizadas;
- APIs;
- Paginação;
- Validação de entrada;
- Tratamento de erros.

Verificar especialmente:

- métodos gigantes;
- responsabilidades múltiplas;
- duplicação;
- consultas inadequadas;
- regras de negócio espalhadas;
- tratamento incorreto de exceções;
- uso incorreto de recursos PHP;
- gerenciamento de arquivos;
- gerenciamento de memória;
- tratamento de streams;
- problemas de serialização;
- funções perigosas quando realmente aplicáveis.

## PHP Runtime

Verificar:

- versão do PHP;
- suporte da versão;
- extensões;
- `php.ini`;
- `memory_limit`;
- `max_execution_time`;
- `upload_max_filesize`;
- `post_max_size`;
- `max_input_vars`;
- `disable_functions`, quando utilizado;
- OPcache;
- PHP-FPM, quando aplicável;
- permissões do processo;
- usuário utilizado pelo PHP.

Configurações somente deverão ser classificadas como vulneráveis quando houver contexto e evidência de risco.

---

# 25. Fase 4 — Auditoria de Frontend

Quando aplicável, avaliar:

- HTML;
- CSS;
- JavaScript;
- componentes;
- estado;
- re-renderizações;
- bundle;
- cache;
- acessibilidade;
- responsividade;
- performance;
- WCAG;
- tratamento de erros;
- segurança no cliente;
- exposição de dados.

Se o projeto possuir frontend exclusivamente server-side, essa fase deverá ser adaptada ao modelo utilizado.

---

# 26. Fase 5 — Auditoria de Banco de Dados

Avaliar:

- modelagem;
- normalização;
- chaves primárias;
- chaves estrangeiras;
- constraints;
- índices;
- índices redundantes;
- índices ausentes;
- queries;
- JOINs;
- subqueries;
- paginação;
- transações;
- locking;
- concorrência;
- integridade referencial;
- N+1;
- full table scans;
- consultas repetidas;
- migrações.

Para PHP, verificar especificamente:

- PDO;
- prepared statements;
- parâmetros vinculados;
- tratamento de erros;
- transações;
- conexão persistente quando utilizada;
- gerenciamento de charset;
- timeouts;
- pool/conexões quando aplicável.

As recomendações deverão considerar a versão real do banco utilizada.

---

# 27. Fase 6 — Auditoria de Segurança

A auditoria deverá utilizar, conforme aplicável:

- OWASP Top 10;
- OWASP ASVS;
- OWASP API Security Top 10;
- CWE;
- CVE;
- NIST SSDF;
- documentação oficial das tecnologias.

## 27.1 Entrada

Verificar:

- SQL Injection;
- Command Injection;
- XSS;
- Path Traversal;
- LDAP Injection;
- Template Injection;
- deserialização insegura;
- validação inadequada;
- bypass de validação;
- manipulação de parâmetros.

## 27.2 Autenticação

Verificar:

- armazenamento de senhas;
- `password_hash()`;
- `password_verify()`;
- política de senha;
- MFA, quando aplicável;
- sessões;
- tokens;
- expiração;
- revogação;
- brute force;
- rate limiting;
- enumeração de usuários;
- recuperação de senha.

## 27.3 Autorização

Verificar:

- IDOR/BOLA;
- privilege escalation;
- Broken Function Level Authorization;
- Broken Object Property Level Authorization;
- validação de permissões no servidor.

Nunca confiar exclusivamente em controles do frontend.

## 27.4 Sessões

Verificar:

- regeneração do ID de sessão;
- expiração;
- invalidação no logout;
- proteção contra session fixation;
- cookies;
- `Secure`;
- `HttpOnly`;
- `SameSite`;
- armazenamento;
- concorrência;
- timeout.

## 27.5 CSRF

Quando aplicável, verificar:

- existência de token;
- validação server-side;
- escopo;
- expiração;
- rotação;
- reutilização;
- tratamento de requisições AJAX;
- formulários;
- métodos HTTP relevantes.

## 27.6 APIs

Verificar:

- autenticação;
- autorização;
- rate limiting;
- exposição de dados;
- inventário de endpoints;
- métodos HTTP;
- CORS;
- SSRF;
- consumo de APIs externas;
- validação de payload;
- paginação;
- limites de tamanho;
- controle de recursos.

## 27.7 Dados e Secrets

Verificar:

- `.env`;
- credenciais;
- tokens;
- chaves;
- secrets no Git;
- dados sensíveis;
- criptografia;
- hashing;
- logs contendo informações sensíveis.

Secrets nunca deverão ser registrados integralmente nos relatórios.

## 27.8 Uploads

Quando aplicável:

- MIME type;
- extensão;
- tamanho;
- nome do arquivo;
- path traversal;
- armazenamento;
- execução de arquivos;
- permissões;
- diretório público;
- nomes previsíveis;
- acesso não autorizado;
- links de download;
- expiração;
- controle de acesso.

## 27.9 Configuração

Verificar:

- debug;
- mensagens de erro;
- headers;
- cookies;
- TLS;
- CORS;
- configurações padrão;
- permissões;
- exposição de arquivos;
- arquivos de configuração;
- `.env`;
- backups acessíveis;
- diretórios de desenvolvimento.

---

# 28. Fase 7 — Auditoria de Performance

Avaliar:

- algoritmos;
- complexidade;
- loops;
- recursão;
- CPU;
- memória;
- I/O;
- cache;
- concorrência;
- queries;
- N+1;
- serialização;
- desserialização;
- rede;
- latência;
- processamento PHP.

Para aplicações PHP, quando aplicável, avaliar:

- OPcache;
- PHP-FPM;
- número de workers;
- tempo de execução;
- uso de memória por requisição;
- consultas por requisição;
- chamadas externas;
- processamento de arquivos grandes.

Nenhuma otimização deverá ser recomendada apenas com base em suposição.

Quando possível:

```text
Métrica antes
Métrica depois
Diferença
Condições do teste
```

---

# 29. Fase 8 — Auditoria de Qualidade

Avaliar:

- Clean Code;
- SOLID;
- DRY;
- KISS;
- YAGNI;
- naming;
- complexidade;
- coesão;
- acoplamento;
- métodos gigantes;
- classes gigantes;
- número de parâmetros;
- aninhamento;
- duplicação;
- código morto;
- comentários obsoletos;
- padrões inconsistentes.

Uma recomendação de refatoração deverá ser feita somente quando houver benefício técnico real.

---

# 30. Fase 9 — Auditoria Composer e Dependências

Esta fase é obrigatória quando Composer for utilizado.

## 30.1 composer.json

Avaliar:

- dependências;
- versões;
- constraints;
- dependências de desenvolvimento;
- scripts;
- plugins;
- autoload;
- autoload-dev;
- repositories;
- stability;
- configuração;
- pacotes desnecessários.

## 30.2 composer.lock

Verificar:

- existência;
- versionamento no Git;
- consistência com `composer.json`;
- versões efetivamente instaladas;
- dependências transitivas;
- alterações inesperadas.

## 30.3 Auditoria

Quando disponível:

```bash
composer validate
composer audit
composer outdated
composer show
```

Os comandos e resultados deverão ser registrados.

## 30.4 Scripts e Plugins

Avaliar cuidadosamente:

```text
scripts
plugins
repositories
autoload
autoload-dev
```

Scripts do Composer podem executar código durante operações do Composer. Portanto, sua presença não deverá ser considerada vulnerabilidade automaticamente, mas deverá ser auditada quanto à origem, necessidade e comportamento.

## 30.5 Dependências

Registrar:

| Pacote | Atual | Recomendado | CVE | Severidade | Compatibilidade | Ação |
|---|---|---|---|---|---|---|

Avaliar:

- dependências desatualizadas;
- vulnerabilidades;
- CVEs;
- dependências abandonadas;
- dependências sem uso;
- dependências duplicadas;
- conflitos;
- dependências transitivas;
- licenciamento, quando aplicável;
- compatibilidade com a versão do PHP.

---

# 31. Fase 10 — Auditoria Docker/DevOps

Quando Docker for utilizado, avaliar:

- Dockerfile;
- multi-stage builds;
- imagens base;
- tags;
- usuário root;
- healthcheck;
- secrets;
- volumes;
- networks;
- portas;
- variáveis;
- cache de build;
- tamanho da imagem;
- pacotes desnecessários;
- reprodutibilidade;
- CI/CD;
- logs;
- observabilidade;
- permissões;
- filesystem;
- capabilities.

Verificar especificamente:

```text
PHP no container
Composer
extensões PHP
Apache/Nginx
usuário do processo
volumes
uploads
arquivos temporários
logs
rede
exposição de portas
```

As recomendações deverão considerar a documentação oficial do Docker e a versão efetivamente utilizada.

---

# 32. Fase 11 — Auditoria de Testes

Avaliar:

- testes unitários;
- testes de integração;
- testes E2E;
- testes de API;
- testes de segurança;
- testes de regressão;
- casos extremos;
- testes negativos;
- mocks;
- fixtures;
- cobertura.

Para PHP, quando aplicável:

- PHPUnit;
- testes de integração com banco;
- testes HTTP;
- testes de autenticação;
- testes de autorização;
- testes de upload;
- testes de sessão;
- testes CSRF;
- testes de regressão.

Cobertura de código não deverá ser tratada isoladamente como indicador de qualidade.

Deverá ser avaliada também a qualidade dos cenários testados.

---

# 33. Fase 12 — Auditoria de Documentação

Avaliar:

- README;
- instalação;
- configuração;
- desenvolvimento;
- deploy;
- rollback;
- backup;
- API;
- banco;
- arquitetura;
- troubleshooting;
- variáveis de ambiente;
- segurança;
- Docker;
- Composer;
- PHP.

A documentação deverá permitir que outro desenvolvedor consiga configurar e executar o projeto sem depender de conhecimento informal.

---

# 34. Fase 13 — Consolidação

Todos os achados deverão ser consolidados em uma matriz.

| ID | Categoria | Severidade | Impacto | Probabilidade | Esforço | Prioridade | Confiança | Status |
|---|---|---|---:|---:|---:|---:|---|---|
| SEC-001 | Segurança | Alto | 5 | 4 | 2 | 20 | Confirmado | Aberto |

A matriz será a fonte principal para o planejamento das correções.

---

# 35. Matriz de Cobertura

Cada item auditado deverá possuir um estado:

- Conforme;
- Não conforme;
- Não aplicável;
- Não confirmado;
- Não auditado;
- Bloqueado.

Exemplo:

| Controle | Status | Evidência |
|---|---|---|
| SQL Injection | Conforme | SEC-001 |
| XSS | Não conforme | SEC-002 |
| SSRF | Não confirmado | SEC-003 |
| CSRF | Não aplicável | SEC-004 |

---

# 36. Fase 14 — Plano de Refatoração

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

# 37. Formato Obrigatório de Cada Recomendação

Cada recomendação deverá conter:

## 1. ID

Identificador único.

## 2. Problema

Descrição técnica objetiva.

## 3. Localização

Informar:

- arquivo;
- linha;
- função;
- classe;
- módulo.

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

- segurança;
- performance;
- legibilidade;
- escalabilidade;
- manutenção;
- testabilidade.

## 13. Riscos

Descrever possíveis efeitos colaterais.

## 14. Compatibilidade

Avaliar:

- API;
- banco;
- dependências;
- PHP;
- extensões;
- sistema operacional;
- Docker;
- produção.

## 15. Testes

Definir como validar a correção.

## 16. Métrica

Definir como medir o resultado.

## 17. Status

- Aberto;
- Em análise;
- Em implementação;
- Corrigido;
- Validado;
- Rejeitado;
- Não aplicável.

---

# 38. Definition of Done

Uma correção somente será considerada concluída quando:

- [ ] Problema reproduzido ou confirmado.
- [ ] Evidência registrada.
- [ ] Causa raiz identificada.
- [ ] Correção implementada.
- [ ] Testes executados.
- [ ] Testes existentes continuam passando.
- [ ] Segurança reavaliada.
- [ ] Performance reavaliada quando aplicável.
- [ ] Compatibilidade validada.
- [ ] Métricas comparadas.
- [ ] Documentação atualizada.
- [ ] Diff revisado.
- [ ] Finding atualizado.
- [ ] Changelog atualizado quando aplicável.

---

# 39. Processo Controlado de Implementação

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

# 40. Regras de Preservação

As alterações deverão:

- preservar comportamento funcional;
- preservar APIs públicas quando possível;
- preservar contratos existentes;
- evitar mudanças desnecessárias;
- evitar refatoração cosmética;
- evitar introduzir dependências sem necessidade;
- evitar aumentar a complexidade arquitetural sem justificativa.

Alterações incompatíveis deverão ser explicitamente identificadas.

---

# 41. Critérios de Aceitação

Uma recomendação somente deverá ser aprovada quando responder positivamente, ou possuir justificativa documentada, para:

- Resolve um problema real?
- Existe evidência?
- A causa raiz foi identificada?
- Reduz risco?
- Melhora segurança?
- Melhora performance?
- Reduz complexidade?
- Reduz dívida técnica?
- Melhora manutenibilidade?
- Está alinhada à documentação oficial?
- Mantém compatibilidade?
- Possui estratégia de teste?
- O benefício supera o custo?

---

# 42. Quick Wins

Serão classificados como Quick Wins os itens que:

- possuem baixo esforço;
- possuem benefício comprovado;
- apresentam baixo risco de regressão;
- podem ser implementados rapidamente.

Por padrão:

```text
Esforço <= 2
Prioridade >= Média
```

O limite poderá ser ajustado conforme o projeto.

---

# 43. Métricas de Qualidade

Quando aplicável, registrar:

- complexidade ciclomática;
- complexidade cognitiva;
- linhas de código;
- tamanho de classes;
- tamanho de funções;
- número de parâmetros;
- profundidade de herança;
- acoplamento;
- coesão;
- duplicação;
- código morto;
- warnings;
- débitos técnicos;
- violações de padrão.

---

# 44. Métricas de Performance

Registrar, quando aplicável:

- tempo médio;
- P95;
- P99;
- throughput;
- CPU;
- memória;
- I/O;
- latência;
- número de queries;
- queries lentas;
- cache hit ratio;
- tempo de inicialização;
- tempo de resposta HTTP;
- consumo de memória por processo PHP.

Toda comparação deverá informar as condições do teste.

---

# 45. Métricas de Segurança

Registrar:

- vulnerabilidades críticas;
- vulnerabilidades altas;
- vulnerabilidades médias;
- vulnerabilidades baixas;
- CVEs;
- CWE;
- secrets encontrados;
- controles ASVS;
- controles OWASP;
- dependências vulneráveis.

---

# 46. Métricas de Manutenibilidade

Avaliar:

- legibilidade;
- organização;
- documentação;
- acoplamento;
- coesão;
- complexidade;
- testabilidade;
- facilidade de alteração;
- concentração de conhecimento.

---

# 47. Análise Automatizada

Sempre que houver ferramentas adequadas, deverão ser executadas:

- linter;
- formatter/check;
- análise estática;
- SAST;
- dependency audit;
- secret scan;
- container scan;
- testes;
- coverage;
- benchmark;
- DAST, quando aplicável.

Para projetos PHP, considerar conforme necessidade:

```text
PHPStan
PHPUnit
PHP_CodeSniffer
Psalm
Composer Audit
PHP Security Checker ou ferramenta equivalente atual
Ferramentas de secret scanning
Trivy ou scanner equivalente para containers
```

Nenhuma ferramenta deverá ser considerada obrigatória se não for compatível com a arquitetura ou com a versão efetivamente utilizada.

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

# 48. Reprodução

Todo resultado importante deverá ser reproduzível.

Exemplo:

```text
Ferramenta:
PHPStan

Comando:
vendor/bin/phpstan analyse

Ambiente:
PHP 8.x

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

# 49. Controle de Falso Positivo

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

- Confirmado;
- Falso positivo;
- Não confirmado;
- Necessita investigação.

---

# 50. Limitações

O relatório final deverá possuir uma seção de limitações.

Deverão ser registrados:

- componentes não disponíveis;
- funcionalidades não executadas;
- ambientes inacessíveis;
- ferramentas indisponíveis;
- testes impossíveis;
- dados insuficientes;
- dependências externas;
- restrições de tempo;
- restrições de acesso.

A auditoria nunca deverá afirmar que um sistema é "100% seguro".

A conclusão deverá ser limitada ao escopo, ambiente e evidências efetivamente analisados.

---

# 51. Artefatos Obrigatórios

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

# 52. Estrutura dos Relatórios

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

# 53. AUDIT_REPORT.md

Deverá conter:

- resumo executivo;
- nota geral;
- matriz de riscos;
- principais problemas;
- Quick Wins;
- Top 5 prioridades;
- estado da arquitetura;
- estado da segurança;
- estado da performance;
- estado dos testes;
- estado da documentação;
- dívida técnica;
- plano de evolução.

---

# 54. SECURITY_REPORT.md

Deverá conter:

- metodologia;
- OWASP Top 10;
- OWASP ASVS;
- OWASP API Security;
- CWE;
- CVE;
- autenticação;
- autorização;
- sessões;
- CSRF;
- input validation;
- APIs;
- upload;
- secrets;
- criptografia;
- configuração;
- logging;
- matriz de vulnerabilidades.

---

# 55. PERFORMANCE_REPORT.md

Deverá conter:

- baseline;
- metodologia;
- benchmarks;
- gargalos;
- CPU;
- memória;
- banco;
- I/O;
- cache;
- PHP-FPM, quando aplicável;
- OPcache, quando aplicável;
- latência;
- comparações antes/depois;
- recomendações.

---

# 56. TECH_DEBT.md

Deverá classificar dívida técnica por:

- impacto;
- prioridade;
- esforço;
- causa;
- custo estimado;
- risco de não corrigir.

---

# 57. REFACTORING_PLAN.md

Deverá conter:

- Finding;
- objetivo;
- arquivos;
- estratégia;
- dependências;
- riscos;
- testes;
- métricas;
- ordem de execução;
- critério de conclusão.

---

# 58. ROADMAP.md

Dividir em:

## Curto prazo

Correções críticas e Quick Wins.

## Médio prazo

Refatorações estruturais e redução significativa de dívida técnica.

## Longo prazo

Evolução arquitetural, observabilidade, escalabilidade e modernização.

---

# 59. DEPENDENCY_AUDIT.md

Deverá registrar:

| Pacote | Atual | Recomendado | CVE | Severidade | Compatibilidade | Ação |
|---|---|---|---|---|---|---|

Também deverá considerar:

- dependências diretas;
- dependências transitivas;
- pacotes abandonados;
- plugins;
- scripts;
- origem dos pacotes;
- `composer.lock`;
- compatibilidade com PHP;
- vulnerabilidades conhecidas.

---

# 60. TEST_PLAN.md

Deverá definir:

- testes unitários;
- integração;
- E2E;
- API;
- segurança;
- regressão;
- performance;
- casos extremos;
- cenários negativos;
- autenticação;
- autorização;
- sessão;
- CSRF;
- upload.

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

# 61. ARCHITECTURE_REVIEW.md

Deverá conter:

- arquitetura atual;
- componentes;
- dependências;
- fluxos;
- pontos de acoplamento;
- pontos de coesão;
- riscos;
- limitações;
- arquitetura recomendada;
- justificativa;
- impacto da migração.

Nenhuma arquitetura deverá ser recomendada apenas por tendência tecnológica.

---

# 62. AUDIT_MATRIX.md

Deverá funcionar como índice central de todos os controles.

| ID | Área | Controle | Resultado | Evidência | Finding |
|---|---|---|---|---|---|

Esse documento deverá permitir verificar rapidamente se toda a auditoria aplicável foi coberta.

---

# 63. EVIDENCE_INDEX.md

Deverá funcionar como índice de evidências.

| ID | Finding | Arquivo | Linha | Evidência | Fonte | Data |
|---|---|---|---:|---|---|---|

---

# 64. Nota Geral do Projeto

A nota geral deverá ser calculada a partir de categorias objetivas.

| Categoria | Nota |
|---|---:|
| Arquitetura | TBD |
| Segurança | TBD |
| Performance | TBD |
| Qualidade | TBD |
| Testabilidade | TBD |
| Documentação | TBD |
| Manutenibilidade | TBD |
| DevOps | TBD |
| Dependências | TBD |
| PHP/Runtime | TBD |

A metodologia de cálculo deverá ser documentada no relatório.

A nota não deverá substituir a análise dos achados.

---

# 65. Critérios de Finalização

A auditoria será considerada concluída somente quando:

1. Todas as fases aplicáveis forem executadas.
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
19. As fases não aplicáveis estiverem justificadas.

---

# 66. Regra Fundamental da Auditoria

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

# 67. Referências Oficiais

As fontes oficiais deverão ser priorizadas durante a auditoria:

- [PHP Documentation](https://www.php.net/docs.php)
- [PHP Security](https://www.php.net/manual/en/security.php)
- [Composer Documentation](https://getcomposer.org/doc/)
- [Composer Audit](https://getcomposer.org/doc/03-cli.md#audit)
- [OWASP Top 10](https://owasp.org/Top10/)
- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)
- [OWASP API Security](https://owasp.org/API-Security/)
- [MITRE CWE](https://cwe.mitre.org/)
- [NIST SSDF](https://csrc.nist.gov/Projects/ssdf)
- [RFC Editor](https://www.rfc-editor.org/)
- [Docker Documentation](https://docs.docker.com/)
- [Apache HTTP Server Documentation](https://httpd.apache.org/docs/)
- [Nginx Documentation](https://nginx.org/en/docs/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer)
- [markdownlint](https://github.com/DavidAnson/markdownlint/blob/main/doc/Rules.md)

---

# 68. Resultado Esperado

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

- o que foi analisado;
- como foi analisado;
- quais problemas foram encontrados;
- quais problemas não foram encontrados;
- quais problemas não puderam ser confirmados;
- quais evidências sustentam cada conclusão;
- quais alterações devem ser realizadas;
- quais alterações já foram realizadas;
- como cada alteração foi validada;
- quais riscos permanecem;
- qual deverá ser a evolução futura do projeto.

---

# 69. Instrução Final ao Auditor

Siga rigorosamente esta especificação.

A auditoria deverá ser:

- Evidence-based;
- reproduzível;
- mensurável;
- rastreável;
- conservadora;
- tecnicamente justificável;
- orientada a risco;
- orientada a impacto;
- orientada à redução de dívida técnica.

**Nunca declarar um problema sem evidência suficiente.**

**Nunca considerar uma correção concluída sem validação.**

**Nunca modificar comportamento funcional sem justificativa explícita.**

**Sempre priorizar documentação oficial e fontes primárias.**

**Sempre registrar limitações.**

**Sempre preservar a rastreabilidade entre finding, evidência, alteração, teste e validação.**

**Quando uma fase ou tecnologia não for aplicável ao projeto PHP, registrar explicitamente a condição "Não aplicável" e justificar a decisão, em vez de criar verificações artificiais.**
