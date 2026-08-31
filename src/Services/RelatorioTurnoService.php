<?php

declare(strict_types=1);

namespace App\Services;

use App\Exception\ServiceException;

/**
 * Relatório Diário de Turno (escala 12x36).
 *
 * Mantém o histórico operacional permanente dos turnos, permitindo que o
 * operador do turno seguinte veja o que aconteceu, o que foi resolvido e o
 * que permanece pendente/em acompanhamento.
 *
 * Não substitui nem duplica a O.S.: a O.S. é referenciada de forma opcional
 * e polimórfica (equipamentos_manutencoes ou alarmes_manutencoes).
 */
class RelatorioTurnoService
{
    private $db;

    public const TURNOS = ['DIURNO', 'NOTURNO'];

    public const HORARIOS_PADRAO = [
        'DIURNO'  => ['07:00', '19:00'],
        'NOTURNO' => ['19:00', '07:00'],
    ];

    public const TIPOS = [
        'Ocorrência',
        'Alarme',
        'Câmera',
        'Sistema',
        'Rede',
        'Observação',
        'Outros',
    ];

    public const SITUACOES = [
        'Resolvido',
        'Pendente',
        'Em acompanhamento',
    ];

    public const PRIORIDADES = [
        'Normal',
        'Atenção',
        'Urgente',
    ];

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Determina o turno atual (12x36) com base na hora do servidor.
     * DIURNO: 07:00 às 19:00. NOTURNO: 19:00 às 07:00.
     */
    public function turnoAtual(?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone(date_default_timezone_get()));
        $hora = (int)$now->format('G');

        if ($hora >= 7 && $hora < 19) {
            return ['data' => $now->format('Y-m-d'), 'turno' => 'DIURNO'];
        }

        // Turno noturno: começa no dia anterior se for madrugada (00h-07h)
        $data = ($hora < 7)
            ? $now->modify('-1 day')->format('Y-m-d')
            : $now->format('Y-m-d');

        return ['data' => $data, 'turno' => 'NOTURNO'];
    }

    /**
     * Busca o relatório de uma data/turno, ou null se ainda não existir.
     */
    public function getRelatorio(string $data, string $turno): ?object
    {
        $turno = $this->normalizarTurno($turno);

        $result = $this->db->query(
            "SELECT rt.*, u.nome AS operador_nome, u.usuario AS operador_usuario
             FROM relatorios_turno rt
             JOIN usuarios u ON u.id = rt.operador_id
             WHERE rt.data = ? AND rt.turno = ?
             LIMIT 1",
            [$data, $turno]
        );

        return $result['data'][0] ?? null;
    }

    /**
     * Abre (ou retorna o existente) relatório para a data/turno.
     * Operador identificado pela sessão.
     */
    public function abrirRelatorio(string $data, string $turno, int $operadorId, ?int $secretariaId = null): object
    {
        $turno = $this->normalizarTurno($turno);
        $this->validarData($data);

        $existente = $this->getRelatorio($data, $turno);
        if ($existente !== null) {
            return $existente;
        }

        [$horaInicio, $horaFim] = self::HORARIOS_PADRAO[$turno];

        $this->db->query(
            "INSERT INTO relatorios_turno (data, turno, operador_id, hora_inicio, hora_fim, situacao_geral, passagem_turno)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$data, $turno, $operadorId, $horaInicio, $horaFim, '', '']
        );

        $id = (int)$this->db->lastInsertId();

        $this->registrarAuditoria($id, 'INSERT', ['data' => $data, 'turno' => $turno], null, $operadorId, $secretariaId);

        $relatorio = $this->getRelatorio($data, $turno);
        if ($relatorio === null) {
            throw ServiceException::internal('Falha ao abrir o relatório de turno.');
        }

        return $relatorio;
    }

    /**
     * Consulta um relatório com registros, resumo e passagem de turno.
     */
    public function consultarRelatorio(string $data, string $turno, ?int $secretariaId = null): array
    {
        $turno = $this->normalizarTurno($turno);

        $relatorio = $this->getRelatorio($data, $turno);
        if ($relatorio === null) {
            return [
                'relatorio' => null,
                'registros' => [],
                'resumo'    => $this->resumoVazio(),
                'pendencias_continuidade' => [],
                'historico' => [],
            ];
        }

        $registros = $this->getRegistros((int)$relatorio->id, $secretariaId);
        $resumo = $this->getResumo((int)$relatorio->id);

        return [
            'relatorio' => $relatorio,
            'registros' => $registros,
            'resumo'    => $resumo,
            'pendencias_continuidade' => $this->getPendenciasContinuidade((int)$relatorio->id, $secretariaId),
            'historico' => $this->getHistorico($secretariaId),
        ];
    }

    /**
     * Lista os registros de um relatório.
     */
    public function getRegistros(int $relatorioId, ?int $secretariaId = null): array
    {
        $where = ['r.relatorio_id = ?'];
        $params = [$relatorioId];

        if ($secretariaId !== null) {
            $where[] = '(
                r.equipamento_id IS NULL
                OR r.equipamento_id IN (
                    SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ?
                )
            )';
            $params[] = $secretariaId;
        }

        $result = $this->db->query(
            "SELECT r.*, u.nome AS operador_nome,
                    e.patrimonio AS equip_patrimonio,
                    COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS equip_descricao
             FROM relatorio_turno_registros r
             JOIN usuarios u ON u.id = r.created_by
             LEFT JOIN equipamentos e ON e.id = r.equipamento_id
             LEFT JOIN locais l ON l.id = e.local_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.horario ASC, r.id ASC",
            $params
        );

        return $result['data'] ?? [];
    }

    /**
     * Resumo do turno calculado a partir dos registros.
     */
    public function getResumo(int $relatorioId): array
    {
        $result = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(situacao = 'Resolvido') AS resolvidos,
                SUM(situacao = 'Pendente') AS pendentes,
                SUM(situacao = 'Em acompanhamento') AS acompanhamento,
                SUM(prioridade = 'Urgente') AS urgentes
             FROM relatorio_turno_registros
             WHERE relatorio_id = ?",
            [$relatorioId]
        );

        $row = $result['data'][0] ?? null;
        if ($row === null) {
            return $this->resumoVazio();
        }

        return [
            'total' => (int)($row->total ?? 0),
            'resolvidos' => (int)($row->resolvidos ?? 0),
            'pendentes' => (int)($row->pendentes ?? 0),
            'acompanhamento' => (int)($row->acompanhamento ?? 0),
            'urgentes' => (int)($row->urgentes ?? 0),
        ];
    }

    /**
     * Registros Pendentes e Em acompanhamento — destaques para o turno seguinte.
     */
    public function getPendenciasContinuidade(int $relatorioId, ?int $secretariaId = null): array
    {
        $where = ['r.relatorio_id = ?', "r.situacao IN ('Pendente', 'Em acompanhamento')"];
        $params = [$relatorioId];

        if ($secretariaId !== null) {
            $where[] = '(
                r.equipamento_id IS NULL
                OR r.equipamento_id IN (
                    SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ?
                )
            )';
            $params[] = $secretariaId;
        }

        $result = $this->db->query(
            "SELECT r.*, u.nome AS operador_nome,
                    e.patrimonio AS equip_patrimonio,
                    COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS equip_descricao
             FROM relatorio_turno_registros r
             JOIN usuarios u ON u.id = r.created_by
             LEFT JOIN equipamentos e ON e.id = r.equipamento_id
             LEFT JOIN locais l ON l.id = e.local_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY r.prioridade DESC, r.horario ASC",
            $params
        );

        return $result['data'] ?? [];
    }

    /**
     * Histórico dos relatórios (permanente). Navegação por data/turno.
     */
    public function getHistorico(?int $secretariaId = null, int $limite = 60): array
    {
        $where = [];
        $params = [];

        if ($secretariaId !== null) {
            $where[] = 'EXISTS (
                SELECT 1 FROM relatorio_turno_registros r
                WHERE r.relatorio_id = rt.id
                  AND (
                    r.equipamento_id IS NULL
                    OR r.equipamento_id IN (
                        SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ?
                    )
                  )
            )';
            $params[] = $secretariaId;
        }

        $whereClause = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        $result = $this->db->query(
            "SELECT rt.id, rt.data, rt.turno, rt.passagem_turno,
                    u.nome AS operador_nome,
                    (SELECT COUNT(*) FROM relatorio_turno_registros r WHERE r.relatorio_id = rt.id) AS total,
                    (SELECT COUNT(*) FROM relatorio_turno_registros r WHERE r.relatorio_id = rt.id AND r.situacao = 'Pendente') AS pendentes,
                    (SELECT COUNT(*) FROM relatorio_turno_registros r WHERE r.relatorio_id = rt.id AND r.situacao = 'Resolvido') AS resolvidos,
                    (SELECT COUNT(*) FROM relatorio_turno_registros r WHERE r.relatorio_id = rt.id AND r.situacao = 'Em acompanhamento') AS acompanhamento
             FROM relatorios_turno rt
             JOIN usuarios u ON u.id = rt.operador_id
             {$whereClause}
             ORDER BY rt.data DESC, FIELD(rt.turno, 'DIURNO', 'NOTURNO') ASC
             LIMIT ?",
            array_merge($params, [$limite])
        );

        return $result['data'] ?? [];
    }

    /**
     * Registra um acontecimento no relatório.
     */
    public function criarRegistro(array $payload, int $usuarioId, ?int $secretariaId = null): int
    {
        $relatorioId = max(1, (int)($payload['relatorio_id'] ?? 0));
        $tipo = trim((string)($payload['tipo'] ?? ''));
        $titulo = trim((string)($payload['titulo'] ?? ''));
        $descricao = trim((string)($payload['descricao'] ?? ''));
        $situacao = trim((string)($payload['situacao'] ?? 'Pendente'));
        $prioridade = trim((string)($payload['prioridade'] ?? 'Normal'));
        $horario = trim((string)($payload['horario'] ?? ''));
        $equipamentoId = !empty($payload['equipamento_id']) ? (int)$payload['equipamento_id'] : null;
        $osTipo = trim((string)($payload['os_tipo'] ?? ''));
        $osId = !empty($payload['os_id']) ? (int)$payload['os_id'] : null;
        $numeroOs = trim((string)($payload['numero_os'] ?? ''));
        $observacao = trim((string)($payload['observacao'] ?? ''));

        $this->validarRelatorioExiste($relatorioId);

        if ($tipo === '' || !in_array($tipo, self::TIPOS, true)) {
            throw ServiceException::validation('Tipo de registro inválido.');
        }
        if ($titulo === '') {
            throw ServiceException::validation('Informe o título do acontecimento.');
        }
        if (mb_strlen($titulo) > 255) {
            throw ServiceException::validation('Título não pode exceder 255 caracteres.');
        }
        if (mb_strlen($descricao) > 5000) {
            throw ServiceException::validation('Descrição não pode exceder 5000 caracteres.');
        }
        if (!in_array($situacao, self::SITUACOES, true)) {
            throw ServiceException::validation('Situação inválida.');
        }
        if (!in_array($prioridade, self::PRIORIDADES, true)) {
            throw ServiceException::validation('Prioridade inválida.');
        }

        if ($horario === '') {
            $horario = date('H:i:s');
        } elseif (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario)) {
            $horario = strlen($horario) === 5 ? $horario . ':00' : $horario;
        } else {
            throw ServiceException::validation('Horário inválido.');
        }

        if ($equipamentoId !== null && !$this->equipamentoExiste($equipamentoId)) {
            throw ServiceException::validation('Equipamento relacionado inválido.');
        }

        if ($osId !== null) {
            $this->validarOs($osTipo, $osId, $numeroOs);
        }

        $this->db->query(
            "INSERT INTO relatorio_turno_registros
                (relatorio_id, horario, tipo, titulo, descricao, situacao, prioridade,
                 equipamento_id, os_tipo, os_id, numero_os, observacao, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $relatorioId, $horario, $tipo, $titulo, $descricao !== '' ? $descricao : null,
                $situacao, $prioridade,
                $equipamentoId, $osId !== null ? $osTipo : null, $osId,
                $osId !== null ? $numeroOs : null, $observacao !== '' ? $observacao : null,
                $usuarioId,
            ]
        );

        $id = (int)$this->db->lastInsertId();

        $this->registrarAuditoria(
            $relatorioId,
            'INSERT',
            null,
            ['registro' => $id, 'tipo' => $tipo, 'titulo' => $titulo, 'situacao' => $situacao],
            $usuarioId,
            $secretariaId,
            'relatorio_turno_registro'
        );

        return $id;
    }

    /**
     * Atualiza a situação de um registro (Resolvido/Pendente/Em acompanhamento).
     */
    public function atualizarSituacao(int $registroId, string $situacao, int $usuarioId, ?int $secretariaId = null): void
    {
        if (!in_array($situacao, self::SITUACOES, true)) {
            throw ServiceException::validation('Situação inválida.');
        }

        $registro = $this->getRegistro($registroId);
        if ($registro === null) {
            throw ServiceException::notFound('Registro do relatório', $registroId);
        }

        $this->autorizarOperacao($registro, $usuarioId);

        $this->db->query(
            "UPDATE relatorio_turno_registros SET situacao = ? WHERE id = ?",
            [$situacao, $registroId]
        );

        $this->registrarAuditoria(
            (int)$registro->relatorio_id,
            'UPDATE',
            ['registro' => $registroId, 'situacao_anterior' => $registro->situacao],
            ['registro' => $registroId, 'situacao_nova' => $situacao],
            $usuarioId,
            $secretariaId,
            'relatorio_turno_registro'
        );
    }

    /**
     * Salva a passagem de turno (informações gerais para o próximo operador).
     */
    public function atualizarPassagemTurno(int $relatorioId, string $texto, int $usuarioId, ?int $secretariaId = null): void
    {
        $relatorio = $this->getRelatorioPorId($relatorioId);
        if ($relatorio === null) {
            throw ServiceException::notFound('Relatório de turno', $relatorioId);
        }

        $this->autorizarOperacaoRelatorio($relatorio, $usuarioId);

        if (mb_strlen($texto) > 10000) {
            throw ServiceException::validation('Passagem de turno não pode exceder 10000 caracteres.');
        }

        $this->db->query(
            "UPDATE relatorios_turno SET passagem_turno = ? WHERE id = ?",
            [$texto !== '' ? $texto : null, $relatorioId]
        );

        $this->registrarAuditoria(
            $relatorioId,
            'UPDATE',
            ['campo' => 'passagem_turno'],
            ['campo' => 'passagem_turno'],
            $usuarioId,
            $secretariaId
        );
    }

    /**
     * Atualiza a situação geral do turno.
     */
    public function atualizarSituacaoGeral(int $relatorioId, string $texto, int $usuarioId, ?int $secretariaId = null): void
    {
        $relatorio = $this->getRelatorioPorId($relatorioId);
        if ($relatorio === null) {
            throw ServiceException::notFound('Relatório de turno', $relatorioId);
        }

        $this->autorizarOperacaoRelatorio($relatorio, $usuarioId);

        if (mb_strlen($texto) > 10000) {
            throw ServiceException::validation('Situação geral não pode exceder 10000 caracteres.');
        }

        $this->db->query(
            "UPDATE relatorios_turno SET situacao_geral = ? WHERE id = ?",
            [$texto !== '' ? $texto : null, $relatorioId]
        );

        $this->registrarAuditoria(
            $relatorioId,
            'UPDATE',
            ['campo' => 'situacao_geral'],
            ['campo' => 'situacao_geral'],
            $usuarioId,
            $secretariaId
        );
    }

    /**
     * Exclui um registro (apenas o autor ou usuário com permissão).
     */
    public function excluirRegistro(int $registroId, int $usuarioId, ?int $secretariaId = null): void
    {
        $registro = $this->getRegistro($registroId);
        if ($registro === null) {
            throw ServiceException::notFound('Registro do relatório', $registroId);
        }

        $this->autorizarOperacao($registro, $usuarioId, true);

        $relatorioId = (int)$registro->relatorio_id;

        $this->db->query("DELETE FROM relatorio_turno_registros WHERE id = ?", [$registroId]);

        $this->registrarAuditoria(
            $relatorioId,
            'DELETE',
            ['registro' => $registroId, 'titulo' => $registro->titulo],
            null,
            $usuarioId,
            $secretariaId,
            'relatorio_turno_registro'
        );
    }

    /**
     * Lista O.S. existentes (não canceladas) para relacionar a um registro.
     */
    public function listarOsDisponiveis(?int $secretariaId = null, string $filtro = ''): array
    {
        $where = ['em.os_status_id IN (1, 2)'];
        $params = [];

        if ($secretariaId !== null) {
            $where[] = 'e.secretaria_id = ?';
            $params[] = $secretariaId;
        }
        if ($filtro !== '') {
            $where[] = '(em.numero_os LIKE ? OR em.descricao LIKE ? OR e.patrimonio LIKE ? OR l.nome LIKE ?)';
            $like = '%' . $filtro . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = implode(' AND ', $where);

        $result = $this->db->query(
            "SELECT 'camera' AS os_tipo, em.id AS os_id,
                    em.numero_os, em.os_status_id, st.nome AS os_status_nome,
                    e.id AS equipamento_id, e.patrimonio,
                    e.ip AS ip_num,
                    COALESCE(l.nome, CONCAT('EQUIPAMENTO ', e.id)) AS local_nome,
                    em.descricao AS os_descricao,
                    em.data_hora AS os_data_hora
             FROM equipamentos_manutencoes em
             JOIN equipamentos e ON e.id = em.equipamento_id
             JOIN status_os st ON st.id = em.os_status_id
             LEFT JOIN locais l ON l.id = e.local_id
             WHERE {$whereClause}
             UNION ALL
             SELECT 'alarme' AS os_tipo, am.id AS os_id,
                    am.numero_os, am.os_status_id, st.nome AS os_status_nome,
                    ca.id AS equipamento_id, NULL AS patrimonio,
                    ca.ip AS ip_num,
                    COALESCE(ca.local, CONCAT('ALARME ', ca.id)) AS local_nome,
                    am.descricao AS os_descricao,
                    am.data_hora AS os_data_hora
             FROM alarmes_manutencoes am
             JOIN central_alarmes ca ON ca.id = am.alarme_id
             JOIN status_os st ON st.id = am.os_status_id
             WHERE am.os_status_id IN (1, 2)
             ORDER BY os_tipo ASC, numero_os ASC
             LIMIT 200",
            $params
        );

        return $result['data'] ?? [];
    }

    public function getRelatorioPorId(int $relatorioId): ?object
    {
        $result = $this->db->query(
            "SELECT rt.*, u.nome AS operador_nome, u.usuario AS operador_usuario
             FROM relatorios_turno rt
             JOIN usuarios u ON u.id = rt.operador_id
             WHERE rt.id = ?
             LIMIT 1",
            [$relatorioId]
        );

        return $result['data'][0] ?? null;
    }

    public function getRegistro(int $registroId): ?object
    {
        $result = $this->db->query(
            "SELECT r.*, u.nome AS operador_nome
             FROM relatorio_turno_registros r
             JOIN usuarios u ON u.id = r.created_by
             WHERE r.id = ?
             LIMIT 1",
            [$registroId]
        );

        return $result['data'][0] ?? null;
    }

    private function normalizarTurno(string $turno): string
    {
        $turno = strtoupper(trim($turno));
        if (!in_array($turno, self::TURNOS, true)) {
            throw ServiceException::validation('Turno inválido. Use DIURNO ou NOTURNO.');
        }
        return $turno;
    }

    private function validarData(string $data): void
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || strtotime($data) === false) {
            throw ServiceException::validation('Data inválida.');
        }
    }

    private function validarRelatorioExiste(int $relatorioId): void
    {
        if ($this->getRelatorioPorId($relatorioId) === null) {
            throw ServiceException::notFound('Relatório de turno', $relatorioId);
        }
    }

    private function equipamentoExiste(int $equipamentoId): bool
    {
        $result = $this->db->query(
            "SELECT id FROM equipamentos WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$equipamentoId]
        );
        return !empty($result['data']);
    }

    /**
     * Valida a referência a uma O.S. existente (polimórfica).
     */
    private function validarOs(string $osTipo, int $osId, string $numeroOs): void
    {
        if (!in_array($osTipo, ['camera', 'alarme'], true)) {
            throw ServiceException::validation('Tipo de O.S. inválido.');
        }

        if ($osTipo === 'camera') {
            $result = $this->db->query(
                "SELECT numero_os FROM equipamentos_manutencoes WHERE id = ? AND os_status_id IN (1, 2) LIMIT 1",
                [$osId]
            );
        } else {
            $result = $this->db->query(
                "SELECT numero_os FROM alarmes_manutencoes WHERE id = ? AND os_status_id IN (1, 2) LIMIT 1",
                [$osId]
            );
        }

        if (empty($result['data'])) {
            throw ServiceException::notFound('Ordem de serviço', $osId);
        }
    }

    /**
     * Regras de operação:
     * - o autor pode sempre operar sobre o próprio registro/relatório;
     * - supervisor e admin podem operar sobre qualquer registro/relatório;
     * - usuário comum só pode operar sobre os próprios registros.
     */
    private function autorizarOperacao(object $registro, int $usuarioId, bool $exclusao = false): void
    {
        $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);

        $propriedade = $registro->created_by ?? $registro->operador_id ?? null;

        if ((int)$propriedade === $usuarioId) {
            return;
        }

        if (in_array($nivel, ['admin', 'supervisor'], true)) {
            return;
        }

        throw ServiceException::forbidden(
            $exclusao
                ? 'Você não pode excluir registros de outros operadores.'
                : 'Você não pode alterar registros de outros operadores.'
        );
    }

    /**
     * Autorização para atualizar situação geral e passagem de turno.
     *
     * O relatório do turno atual é um documento compartilhado: qualquer operador
     * do turno pode preencher esses campos (registros já são compartilhados).
     * Para relatórios de turnos anteriores mantém-se a restrição de dono/admin.
     */
    private function autorizarOperacaoRelatorio(object $relatorio, int $usuarioId): void
    {
        $nivel = resolveUserAccessLevel($_SESSION['usuario'] ?? null);

        if ((int)($relatorio->operador_id ?? 0) === $usuarioId) {
            return;
        }

        if (in_array($nivel, ['admin', 'supervisor'], true)) {
            return;
        }

        $atual = $this->turnoAtual();
        if ($relatorio->data === $atual['data'] && $relatorio->turno === $atual['turno']) {
            return;
        }

        throw ServiceException::forbidden(
            'Você não pode alterar registros de outros operadores.'
        );
    }

    private function registrarAuditoria(
        int $relatorioId,
        string $operacao,
        ?array $dadosAntes,
        ?array $dadosDepois,
        int $usuarioId,
        ?int $secretariaId,
        string $entidade = 'relatorio_turno'
    ): void {
        try {
            $mapaOperacao = ['INSERT' => 1, 'UPDATE' => 2, 'DELETE' => 3];
            $operacaoId = $mapaOperacao[$operacao] ?? null;
            if ($operacaoId === null) {
                return;
            }

            $this->db->query(
                "INSERT INTO auditoria_eventos
                    (entidade, entidade_id, operacao_id, dados_antes, dados_depois, origem, changed_by, created_at)
                 VALUES (?, ?, ?, ?, ?, 'web', ?, NOW())",
                [
                    $entidade,
                    $relatorioId,
                    $operacaoId,
                    $dadosAntes ? json_encode($dadosAntes, JSON_UNESCAPED_UNICODE) : null,
                    $dadosDepois ? json_encode($dadosDepois, JSON_UNESCAPED_UNICODE) : null,
                    $usuarioId,
                ]
            );
        } catch (\Throwable $e) {
            logError('Falha ao registrar auditoria do relatório de turno', ['error' => $e->getMessage()], 'audit');
        }
    }

    private function resumoVazio(): array
    {
        return [
            'total' => 0,
            'resolvidos' => 0,
            'pendentes' => 0,
            'acompanhamento' => 0,
            'urgentes' => 0,
        ];
    }
}