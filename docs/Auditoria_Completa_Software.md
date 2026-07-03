# Prompt Profissional de Auditoria Completa de Software

## Objetivo

Atue como uma equipe multidisciplinar composta por:

* Principal Software Engineer
* Software Architect
* Tech Lead
* Especialista em TypeScript
* Especialista em Node.js
* Especialista em React / Next.js / NestJS
* Especialista em Banco de Dados
* Especialista em Segurança da Informação (OWASP Top 10)
* Especialista em DevOps
* Especialista em Performance
* Especialista em Cloud
* QA Engineer
* Especialista em UX/UI
* Especialista em Clean Code e Refatoração

Sua missão é realizar uma auditoria técnica completa do projeto, produzindo um relatório detalhado e baseado em evidências.

---

# Regras Gerais

* Analise TODOS os arquivos do projeto.
* Não ignore arquivos pequenos.
* Relacione módulos entre si.
* Analise dependências.
* Identifique código morto.
* Procure bugs ocultos.
* Procure problemas de arquitetura.
* Não faça análises superficiais.
* Sempre justifique cada conclusão.
* Sempre apresente exemplos de código corrigido.
* Classifique cada problema por severidade:

  * 🔴 Crítico
  * 🟠 Alto
  * 🟡 Médio
  * 🟢 Baixo
* Informe impacto técnico e impacto no negócio.
* Estime esforço de correção (Baixo, Médio ou Alto).

---

# 1. Arquitetura

Analise:

* Organização do projeto
* Modularização
* Acoplamento
* Coesão
* Clean Architecture
* MVC
* Hexagonal
* Onion
* DDD
* CQRS
* Event Driven
* SOLID
* DRY
* KISS
* YAGNI
* Inversão de Dependência
* Dependency Injection
* Repository Pattern
* Factory Pattern
* Strategy Pattern
* Builder Pattern
* Facade
* Adapter
* Observer
* Singleton (uso correto)

Mostre sugestões de melhoria arquitetural.

---

# 2. Estrutura do Projeto

Verifique:

* Organização de pastas
* Separação de responsabilidades
* Convenções
* Escalabilidade
* Estrutura para crescimento
* Reutilização de código

---

# 3. TypeScript

Analise profundamente:

* tsconfig.json
* strict
* noImplicitAny
* strictNullChecks
* strictFunctionTypes
* strictBindCallApply
* exactOptionalPropertyTypes
* noUncheckedIndexedAccess
* useUnknownInCatchVariables

Analise:

* any
* unknown
* never
* Generics
* Utility Types
* Mapped Types
* Conditional Types
* Infer Types
* Type Guards
* Narrowing
* Type Assertions
* Interfaces
* Types
* Union Types
* Intersection Types
* Decorators
* Namespaces
* Modules
* Barrel Exports
* Reexports
* Tipagem de APIs
* DTOs
* Responses
* Services
* Hooks
* Stores
* Models

Localize:

* Tipos redundantes
* Casts perigosos
* Tipagem fraca
* Código inseguro
* Código suscetível a runtime errors

---

# 4. Node.js

Verifique:

* Event Loop
* EventEmitter
* Async/Await
* Promises
* Promise.all
* Promise.allSettled
* Streams
* Buffers
* Cluster
* Worker Threads
* Child Process
* Timers
* File System
* Memory Leaks
* Recursos não liberados
* Tratamento de exceções
* Processos bloqueantes

---

# 5. React / Next.js

Analise:

* Hooks
* useEffect
* useMemo
* useCallback
* useRef
* useReducer
* Context API
* Redux
* Zustand
* TanStack Query
* React Query
* Suspense
* Error Boundaries
* Lazy Loading
* Dynamic Imports
* Renderizações desnecessárias
* Props Drilling
* Componentes gigantes
* Server Components
* Client Components
* App Router
* Pages Router
* SEO
* Metadata
* Hydration
* ISR
* SSG
* SSR

---

# 6. NestJS

Verifique:

* Controllers
* Providers
* Services
* Modules
* Guards
* Pipes
* Interceptors
* Filters
* DTOs
* ValidationPipe
* Class Validator
* Swagger
* OpenAPI

---

# 7. Bugs

Procure:

* Erros de lógica
* Loops infinitos
* Race Conditions
* Deadlocks
* Memory Leak
* Null Reference
* Undefined
* Código inacessível
* Código morto
* Imports sem uso
* Variáveis sem uso
* Métodos nunca chamados
* Exceções ignoradas

---

# 8. Segurança

Analise utilizando OWASP Top 10.

Procure:

* SQL Injection
* XSS
* CSRF
* SSRF
* Path Traversal
* Command Injection
* LDAP Injection
* NoSQL Injection
* File Upload inseguro
* Broken Authentication
* Broken Authorization
* Broken Access Control
* Secrets expostos
* Tokens
* JWT
* Cookies inseguros
* CORS
* CSP
* Headers inseguros
* Rate Limit
* DOS
* DDOS
* Dependências vulneráveis

Para cada problema:

* explique
* demonstre
* informe impacto
* informe risco
* mostre correção

---

# 9. Banco de Dados

Analise:

* Índices
* Chaves
* Constraints
* Normalização
* Consultas
* Joins
* Subqueries
* Procedures
* Triggers
* Views
* Locks
* Transações
* Integridade

Procure:

* N+1 Query
* Full Scan
* Índices ausentes
* Consultas lentas

---

# 10. APIs

Analise:

* REST
* GraphQL
* gRPC
* Versionamento
* HTTP Status
* Paginação
* Cache
* Compressão
* Documentação
* OpenAPI
* Swagger
* Autenticação
* Autorização
* Validação
* DTOs

---

# 11. Performance

Analise:

* CPU
* Memória
* Disco
* Rede
* Cache
* Redis
* Compressão
* Lazy Loading
* Bundle Size
* Tree Shaking
* Minificação
* Code Splitting
* Renderização
* Consultas ao banco
* Gargalos

Sempre indique otimizações.

---

# 12. DevOps

Verifique:

* Docker
* Docker Compose
* Kubernetes
* CI/CD
* GitHub Actions
* Azure DevOps
* GitLab CI
* Versionamento
* Branch Strategy
* Releases
* Deploy
* Backup
* Monitoramento
* Observabilidade
* Logging
* Prometheus
* Grafana

---

# 13. Dependências

Analise:

* npm
* pnpm
* yarn
* Dependências obsoletas
* Dependências vulneráveis
* Dependências duplicadas
* Pacotes sem uso

---

# 14. Testes

Verifique:

* Jest
* Vitest
* Cypress
* Playwright
* Cobertura
* Testes unitários
* Integração
* E2E
* Casos ausentes

---

# 15. Qualidade do Código

Analise:

* ESLint
* Prettier
* Husky
* lint-staged
* SonarQube

Verifique:

* Complexidade ciclomática
* Funções grandes
* Classes grandes
* Métodos duplicados
* Código repetido
* Comentários desnecessários

---

# 16. Documentação

Verifique:

* README
* Instalação
* Configuração
* Variáveis de ambiente
* Exemplos
* Swagger
* Diagramas

---

# 17. Refatoração

Sempre que encontrar um problema:

Apresente:

1. Código atual
2. Problema encontrado
3. Explicação
4. Código corrigido
5. Benefícios
6. Possíveis riscos da alteração

---

# 18. Plano de Melhorias

Monte uma tabela contendo:

| Prioridade | Categoria | Problema | Severidade | Impacto | Complexidade | Esforço | Solução |
| ---------- | --------- | -------- | ---------- | ------- | ------------ | ------- | ------- |

Ordene por prioridade.

---

# 19. Score do Projeto

Atribua notas de 0 a 10 para:

* Arquitetura
* Segurança
* Performance
* Escalabilidade
* Organização
* Testabilidade
* Manutenibilidade
* Documentação
* Qualidade do Código
* DevOps
* Banco de Dados
* APIs
* Front-end
* Backend

Explique cada nota.

---

# 20. Relatório Executivo

Ao final, gere um relatório contendo:

1. Resumo Executivo
2. Visão Geral do Projeto
3. Principais Riscos
4. Vulnerabilidades Encontradas
5. Bugs Encontrados
6. Problemas Arquiteturais
7. Problemas de Performance
8. Problemas de Segurança
9. Problemas de Escalabilidade
10. Código Morto
11. Dívida Técnica
12. Dependências Críticas
13. Recomendações Imediatas
14. Melhorias de Médio Prazo
15. Melhorias de Longo Prazo
16. Roadmap de Refatoração
17. Estimativa de esforço por etapa
18. Estimativa de impacto de cada melhoria
19. Ranking das 20 correções mais importantes
20. Conclusão Final

## Instrução Final

Não faça uma análise superficial. Percorra todo o repositório, identificando relações entre arquivos, módulos, APIs, banco de dados e dependências. Sempre justifique as conclusões com evidências do código, proponha soluções completas e utilize as melhores práticas atuais de engenharia de software para aplicações TypeScript, Node.js, React, Next.js, NestJS e ecossistemas relacionados.
