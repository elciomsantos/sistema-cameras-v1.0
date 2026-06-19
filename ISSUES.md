Análise do Projeto - Correções e Melhorias (Histórico Pré-Vite)

NOTA: Este arquivo foi substituído pela auditoria completa pós-migração Vite.
Veja: docs/VITE_MIGRATION_AUDIT.md — contém todos os itens abaixo (numerados #3-#44) + 16 novos itens da migração Vite (#1, #2, #8-#11, #18-#25, #34-#39, #43-#44).

Os itens originais abaixo foram consolidados no novo arquivo com numeração atualizada:

CORREÇÕES CRÍTICAS (agora itens #3 a #7 em VITE_MIGRATION_AUDIT.md)
03. Senha real no .env.example
04. EquipamentoService omite colunas da migration 003
05. Dados duplicados em auditoria (triggers + aplicação)
06. Swagger com CORS aberto e sem autenticação
07. XSS em ui-utils.js innerHTML

CORREÇÕES ALTAS (agora itens #12 a #17)
12. api_cameras.php não filtra soft-delete
13. api_relatorios_cameras.php sem filtro soft-delete
14. cadastro_cameras.js crash ao não encontrar form
15. cadastro_cameras.js - submitToAPI não checa response.ok
16. Race condition ao carregar modelos por marca
17. Access rules faltando para páginas de locais

CORREÇÕES MÉDIAS (agora itens #26 a #33)
26. showToast duplicado
27. uppercase.js acumula listeners duplicados
28. listar_cameras.js - DOMContentLoaded pode nunca disparar
29. putenv() sem validação
30. API relatórios sem cast de tipos
31. Migrations não-idempotentes
32. central_alarmes com status/região em texto livre
33. setTimeout(500) fixo em preencherFormulario

--- MELHORIAS SUGERIDAS (pré-migração) ---

Segurança
22. Senha no .env.example → placeholder
23. swagger.php → restringir CORS
24. Headers de segurança na API bootstrap
25. Password policy exigir maiúsculas/especiais
26. IP spoofing em single_session.php

Código
27. Consolidar showToast em ui-utils.js
28. AbortController nas chamadas fetch
29. response.ok antes de .json()
30. Guard para form null
31. uppercase.js com flag anti-duplicata
32. Remover emojis de console

Banco de Dados
33. FK em equipamentos_anexos
34. UNIQUE em locais(nome, secretaria_id)
35. Migrations idempotentes
36. Remover triggers de auditoria duplicadas
37. Normalizar central_alarmes

Arquitetura
38. regiao_id duplicado - documentar
39. helper handleApiError() reutilizável
40. access_rules para páginas de locais