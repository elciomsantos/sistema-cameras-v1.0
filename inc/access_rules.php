<?php

/**
 * Mapa de acesso: rota → nível mínimo necessário.
 *
 * Níveis: user < supervisor < admin
 * - user: painéis, listagens, OS
 * - supervisor: cadastro/edição, relatórios, locais, admin lookup
 * - admin: gerenciamento de usuários
 *
 * Ordem agrupada por nível para facilitar manutenção.
 */

return [

    // =========================================================
    // user — qualquer usuário logado
    // =========================================================
    'home' => 'user',

    'controle'           => 'user',
    'listar_cameras'     => 'user',
    'listar_alarmes'     => 'user',
    'manutencao_cameras' => 'user',
    'manutencao_alarmes' => 'user',

    'alterar_senha'        => 'user',

    // =========================================================
    // supervisor — operações de cadastro e gestão
    // =========================================================
    'cadastro_cameras'   => 'supervisor',
    'cadastro_alarmes'   => 'supervisor',
    'editar_cameras'     => 'supervisor',
    'editar_alarmes'     => 'supervisor',

    'relatorios_cameras' => 'supervisor',
    'relatorios_alarmes' => 'supervisor',

    'cadastro_locais' => 'supervisor',
    'editar_locais'   => 'supervisor',
    'listar_locais'   => 'supervisor',
    'listar_escolas'    => 'supervisor',
    'listar_cmeis'      => 'supervisor',
    'listar_operadoras' => 'supervisor',
    'listar_proprios'   => 'supervisor',

    'notificacoes'  => 'supervisor',
    'administracao' => 'supervisor',
    'admin_tabelas' => 'supervisor',
    'auditoria_cameras' => 'admin',

    // =========================================================
    // admin — gerenciamento de contas de usuário
    // =========================================================
    'cadastroUsuario'    => 'admin',
    'listarUsuario'      => 'admin',
    'editarUsuario'      => 'admin',
    'deletarUsuario'     => 'admin',
    'bloquearUsuario'    => 'admin',
    'ativarUsuario'      => 'admin',

    'action_cadastro_usuario' => 'admin',
    'action_editar_usuario'   => 'admin',
    'action_deletar_usuario'  => 'admin',
    'action_bloquear_usuario' => 'admin',
    'action_ativar_usuario'   => 'admin',

    'recuperar_senha' => 'admin',
];
