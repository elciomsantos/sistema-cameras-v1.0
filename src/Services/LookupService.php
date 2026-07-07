<?php

declare(strict_types=1);

class LookupService
{
    private $db;

    private array $allowedTables = [
        'status', 'procedimentos', 'regioes', 'tipos_equipamento',
        'tipo_cameras', 'transmissoes', 'origem_link', 'marcas',
        'locais', 'tipos_locais', 'secretarias', 'classificacao_enderecos',
    ];

    public function __construct($db = null)
    {
        $this->db = $db ?? db();
    }

    public function getTableData(string $tableName): array
    {
        if (!in_array($tableName, $this->allowedTables, true)) {
            return [];
        }
        $orderCol = 'nome';
        if ($tableName === 'transmissoes') {
            $orderCol = 'tipo';
        } elseif ($tableName === 'classificacao_enderecos') {
            $orderCol = 'descricao';
        }
        $result = $this->db->query("SELECT * FROM {$tableName} ORDER BY {$orderCol}");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getMultipleTableData(array $tableNames): array
    {
        $data = [];
        foreach ($tableNames as $key => $table) {
            $data[$key] = $this->getTableData($table);
        }
        return $data;
    }

    public function getDadosCadastroCamera(): array
    {
        return $this->getMultipleTableData([
            'status' => 'status',
            'procedimentos' => 'procedimentos',
            'regioes' => 'regioes',
            'tipos_equipamento' => 'tipos_equipamento',
            'tipo_cameras' => 'tipo_cameras',
            'transmissoes' => 'transmissoes',
            'origem_links' => 'origem_link',
            'marcas' => 'marcas',
            'locais' => 'locais',
            'tipos_locais' => 'tipos_locais',
            'secretarias' => 'secretarias',
        ]);
    }

    public function getDadosEditarCamera(): array
    {
        return $this->getMultipleTableData([
            'status' => 'status',
            'procedimentos' => 'procedimentos',
            'regioes' => 'regioes',
            'tipos_equipamento' => 'tipos_equipamento',
            'tipo_cameras' => 'tipo_cameras',
            'transmissoes' => 'transmissoes',
            'origem_links' => 'origem_link',
            'marcas' => 'marcas',
            'locais' => 'locais',
            'secretarias' => 'secretarias',
            'classificacao_enderecos' => 'classificacao_enderecos',
        ]);
    }

    public function getStatusList(): array
    {
        $result = $this->db->query("SELECT id, nome FROM status ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getRegioesList(): array
    {
        $result = $this->db->query("SELECT id, nome FROM regioes ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getModelosAlarmes(): array
    {
        $result = $this->db->query("SELECT id, nome FROM catalogo_modelos_alarmes ORDER BY nome ASC");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getContasAlarmes(): array
    {
        $result = $this->db->query("SELECT conta, local FROM central_alarmes WHERE deleted_at IS NULL ORDER BY conta");
        return $result['status'] === 'success' ? $result['data'] : [];
    }
}
