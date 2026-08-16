<?php

declare(strict_types=1);

namespace App\Services;

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

        $cacheKey = "lookup_{$tableName}";
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $orderCol = 'nome';
        if ($tableName === 'transmissoes') {
            $orderCol = 'tipo';
        } elseif ($tableName === 'classificacao_enderecos') {
            $orderCol = 'descricao';
        }
        $result = $this->db->query("SELECT * FROM {$tableName} ORDER BY {$orderCol}");
        $data = $result['status'] === 'success' ? $result['data'] : [];

        if (function_exists('apcu_store') && !empty($data)) {
            apcu_store($cacheKey, $data, 300);
        }

        return $data;
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
            'classificacao_enderecos' => 'classificacao_enderecos',
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
        $cacheKey = 'lookup_status_list';
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $result = $this->db->query("SELECT id, nome FROM status ORDER BY nome");
        $data = $result['status'] === 'success' ? $result['data'] : [];
        if (function_exists('apcu_store') && !empty($data)) {
            apcu_store($cacheKey, $data, 300);
        }
        return $data;
    }

    public function getRegioesList(): array
    {
        $result = $this->db->query("SELECT id, nome FROM regioes ORDER BY nome");
        return $result['status'] === 'success' ? $result['data'] : [];
    }

    public function getModelosAlarmes(): array
    {
        $cacheKey = 'lookup_modelos_alarmes';
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }
        $result = $this->db->query("SELECT id, nome FROM catalogo_modelos_alarmes ORDER BY nome ASC");
        $data = $result['status'] === 'success' ? $result['data'] : [];
        if (function_exists('apcu_store') && !empty($data)) {
            apcu_store($cacheKey, $data, 300);
        }
        return $data;
    }

    /**
     * Modelos de camera por marca (e opcionalmente por tipo_equipamento).
     * Cache APCu 300s por combinacao marca+tipo — lookup frequente em forms de cadastro.
     *
     * @return array<int, object{id:int, nome:string}>
     */
    public function getModelosCameras(int $marcaId, int $tipoId = 0): array
    {
        $cacheKey = "lookup_modelos_cameras:{$marcaId}:{$tipoId}";
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $sql = "SELECT id, nome FROM catalogo_modelos WHERE marca_id = :marca_id";
        $params = [':marca_id' => $marcaId];
        if ($tipoId > 0) {
            $sql .= " AND tipo_equipamento_id = :tipo_id";
            $params[':tipo_id'] = $tipoId;
        }
        $sql .= " ORDER BY nome";

        $result = $this->db->query($sql, $params);
        $data = $result['status'] === 'success' ? $result['data'] : [];

        if (function_exists('apcu_store') && !empty($data)) {
            apcu_store($cacheKey, $data, 300);
        }

        return $data;
    }

    public function getContasAlarmes(): array
    {
        $result = $this->db->query("SELECT conta, local FROM central_alarmes WHERE deleted_at IS NULL ORDER BY conta");
        return $result['status'] === 'success' ? $result['data'] : [];
    }
}
