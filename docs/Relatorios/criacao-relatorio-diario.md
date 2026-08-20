# Implementar Relatório Diário de Turno

Criar no sistema de Câmeras e Alarmes uma nova funcionalidade chamada **Relatório Diário de Turno**.

## Objetivo

O sistema trabalha em escala **12x36**. O objetivo é permitir que o operador que assumir o próximo turno saiba exatamente:

* o que aconteceu no turno anterior;
* quais ocorrências foram identificadas;
* o que foi resolvido;
* o que continua pendente;
* quais situações precisam de atenção no próximo turno;
* observações importantes deixadas pelo operador anterior.

Esta funcionalidade deve funcionar como um **histórico operacional permanente dos turnos**.

## 1. Dashboard

Adicionar um novo card no Dashboard:

**Relatório Diário**

Descrição:

> Registro das ocorrências e informações importantes de cada turno.

O card deve abrir a página do relatório.

Utilizar o mesmo padrão visual e de permissões já existente no sistema.

## 2. Página do Relatório

A página deve mostrar inicialmente o relatório referente ao **turno/data atual**.

Permitir consultar relatórios anteriores por data.

Exibir claramente:

* data;
* turno;
* operador responsável;
* horário de início;
* horário de término;
* situação geral;
* registros realizados durante o turno.

## 3. Registro do turno

Criar uma forma simples para o operador registrar acontecimentos.

Campos:

* data;
* turno;
* horário;
* tipo;
* título;
* descrição;
* situação;
* prioridade;
* equipamento relacionado, quando aplicável;
* observação.

Tipos sugeridos:

* Ocorrência;
* Alarme;
* Câmera;
* Sistema;
* Rede;
* Observação;
* Outros.

Situação:

* Resolvido;
* Pendente;
* Em acompanhamento.

Prioridade:

* Normal;
* Atenção;
* Urgente.

## 4. Continuidade entre turnos

Esta é a principal finalidade da funcionalidade.

O operador do turno seguinte deve conseguir identificar rapidamente:

### Resolvido

O que foi tratado durante o turno anterior.

### Pendente

O que ainda precisa ser resolvido.

### Em acompanhamento

Situações que não estão necessariamente pendentes, mas precisam continuar sendo observadas.

Na abertura do relatório do novo turno, destacar principalmente os registros **Pendentes** e **Em acompanhamento** do turno anterior.

Não criar um novo registro automaticamente. Apenas apresentar as pendências para continuidade.

## 5. Ordens de Serviço

Já existe no sistema uma funcionalidade própria para criação de **O.S. (Ordem de Serviço)**.

NÃO criar outro cadastro de manutenção.

Quando uma ocorrência exigir manutenção, permitir relacionar o registro do relatório a uma O.S. existente.

Exemplo:

> Câmera 12 apresentou falha de imagem. O.S. #152 criada para manutenção.

O relatório deve registrar o acontecimento e a referência para a O.S., enquanto os detalhes da manutenção permanecem na funcionalidade de O.S.

## 6. Histórico

O histórico dos turnos deve ser permanente.

Não apagar automaticamente relatórios antigos.

Permitir consultar:

* data;
* turno;
* operador;
* ocorrências;
* pendências;
* registros resolvidos.

Permitir navegar entre os dias anteriores.

O histórico deve preservar quem realizou cada registro e quando ele foi realizado.

## 7. Resumo do turno

No topo do relatório apresentar um resumo simples:

```text
Relatório do Turno

Data: 20/08/2026
Operador: Nome do operador
Turno: 07:00 às 19:00

Ocorrências: 5
Resolvidas: 3
Pendentes: 1
Em acompanhamento: 1
```

Os números devem ser calculados a partir dos registros existentes.

## 8. Passagem de turno

Adicionar uma área específica:

**Passagem para o próximo turno**

Campo de texto para o operador deixar informações gerais que não se encaixam em um registro específico.

Exemplo:

> Sistema operando normalmente. Câmera 12 permanece sem imagem e possui O.S. #152 aberta. Monitorar câmera 18 devido a instabilidade observada durante o turno.

Essa informação deve ficar vinculada ao relatório daquele turno.

## 9. Segurança

Utilizar integralmente os mecanismos de autenticação, autorização, CSRF, validação e auditoria já existentes.

O usuário identificado na sessão deve ser utilizado como operador do registro.

Não confiar no nome ou ID do operador enviado pelo navegador.

Usuários sem permissão não devem conseguir alterar ou excluir registros de outros operadores.

## 10. Banco de dados

Antes de criar qualquer tabela, analisar a estrutura existente.

Criar somente as tabelas necessárias para o relatório de turno.

Não duplicar:

* usuários;
* câmeras;
* alarmes;
* equipamentos;
* ordens de serviço.

Utilizar relacionamentos com os registros já existentes.

Criar migrations seguindo o padrão atual do projeto.

## 11. Interface

A interface deve ser simples e rápida para uso durante o turno.

Priorizar:

* leitura rápida;
* poucos campos;
* destaque para pendências;
* histórico fácil de consultar;
* boa visualização em desktop;
* possibilidade de uso em telas menores.

Não criar uma interface complexa de gerenciamento de manutenção.

## 12. Critérios de conclusão

Considerar a implementação concluída somente quando:

* [ ] O card estiver no Dashboard.
* [ ] O operador puder abrir o Relatório Diário.
* [ ] For possível registrar acontecimentos.
* [ ] For possível marcar registros como Resolvido, Pendente ou Em acompanhamento.
* [ ] For possível relacionar uma ocorrência a uma O.S. existente.
* [ ] O operador puder deixar uma mensagem de passagem de turno.
* [ ] O próximo turno conseguir visualizar as pendências do turno anterior.
* [ ] O histórico permanecer armazenado.
* [ ] For possível consultar dias/turnos anteriores.
* [ ] O operador responsável e horário do registro forem preservados.
* [ ] As permissões e proteções de segurança existentes forem respeitadas.
* [ ] Nenhuma funcionalidade existente for duplicada ou quebrada.

## Regra principal

Esta funcionalidade **não deve substituir nem duplicar a O.S.**

A O.S. continua sendo responsável pela manutenção.

O Relatório Diário deve ser o **histórico operacional da equipe**, permitindo a continuidade do trabalho entre os turnos 12x36.

Antes de implementar, analise a arquitetura existente e reutilize os padrões já utilizados pelo sistema.
