<?php
require_once __DIR__ . '/../inc/check_access.php';
requererAcesso('user');

$modulo = $_GET['modulo'] ?? 'cameras';

$isCamera = $modulo === 'cameras';

$header = $isCamera
    ? ['class' => 'bg-primary', 'icon' => 'fa-camera',     'title' => 'Controle de C&acirc;meras']
    : ['class' => 'bg-danger',  'icon' => 'fa-bell',       'title' => 'Controle de Alarmes'];

$stats = $isCamera ? [
    ['label' => 'C&acirc;meras Totais',            'class' => 'bg-primary'],
    ['label' => 'Ativas',                          'class' => 'bg-success'],
    ['label' => 'Em Manuten&ccedil;&atilde;o',    'class' => 'bg-warning'],
    ['label' => 'Desativadas',                     'class' => 'bg-secondary'],
] : [
    ['label' => 'Alarmes Ativos',  'class' => 'bg-warning'],
    ['label' => 'Resolvidos Hoje', 'class' => 'bg-success'],
    ['label' => 'Cr&iacute;ticos', 'class' => 'bg-danger'],
    ['label' => 'Em An&aacute;lise',  'class' => 'bg-info'],
];

$actions = $isCamera ? [
    ['icon' => 'fa-plus-circle',    'color' => 'text-primary',   'title' => 'Cadastro',        'desc' => 'Nova c&acirc;mera no sistema',                     'page' => 'cadastro_cameras',    'btn' => 'btn-primary',    'access' => 'supervisor'],
    ['icon' => 'fa-list',           'color' => 'text-success',   'title' => 'Listagem',        'desc' => 'Visualizar e gerenciar c&acirc;meras',               'page' => 'listar_cameras',      'btn' => 'btn-success',    'access' => 'user'],
    ['icon' => 'fa-pen-to-square',  'color' => 'text-warning',   'title' => 'Edi&ccedil;&atilde;o',  'desc' => 'Alterar dados das c&acirc;meras cadastradas',            'page' => 'editar_cameras',      'btn' => 'btn-warning text-dark', 'access' => 'supervisor'],
    ['icon' => 'fa-tools',          'color' => 'text-info',      'title' => 'Manuten&ccedil;&atilde;o', 'desc' => 'Registrar manuten&ccedil;&atilde;o e acompanhar hist&oacute;rico', 'page' => 'manutencao_cameras',  'btn' => 'btn-info text-white', 'access' => 'user'],
    ['icon' => 'fa-chart-bar',      'color' => 'text-secondary', 'title' => 'Relat&oacute;rios',   'desc' => 'Relat&oacute;rios e exporta&ccedil;&atilde;o',            'page' => 'relatorios_cameras',  'btn' => 'btn-secondary',  'access' => 'supervisor'],
    ['icon' => 'fa-clipboard-check','color' => 'text-dark',      'title' => 'Auditoria',       'desc' => 'Acompanhar altera&ccedil;&otilde;es no cadastro',          'page' => 'auditoria_cameras',   'btn' => 'btn-dark',       'access' => 'admin'],
] : [
    ['icon' => 'fa-plus-circle',    'color' => 'text-primary',   'title' => 'Cadastro',   'desc' => 'Novo alarme no sistema',         'page' => 'cadastro_alarmes',   'btn' => 'btn-primary',    'access' => 'supervisor'],
    ['icon' => 'fa-list',           'color' => 'text-success',   'title' => 'Listagem',   'desc' => 'Visualizar e gerenciar alarmes',  'page' => 'listar_alarmes',     'btn' => 'btn-success',    'access' => 'user'],
    ['icon' => 'fa-pen-to-square',  'color' => 'text-warning',   'title' => 'Edicao',     'desc' => 'Alterar dados dos alarmes cadastrados',  'page' => 'editar_alarmes',     'btn' => 'btn-warning text-dark', 'access' => 'supervisor'],
    ['icon' => 'fa-tools',          'color' => 'text-success',   'title' => 'Manutencao', 'desc' => 'Registrar manutencao e acompanhar historico', 'page' => 'manutencao_alarmes', 'btn' => 'btn-success',   'access' => 'user'],
    ['icon' => 'fa-chart-bar',      'color' => 'text-secondary', 'title' => 'Relat&oacute;rios',  'desc' => 'Relat&oacute;rios e exporta&ccedil;&atilde;o',   'page' => 'relatorios_alarmes', 'btn' => 'btn-secondary',  'access' => 'supervisor'],
    ['icon' => 'fa-clipboard-check','color' => 'text-dark',      'title' => 'Auditoria',  'desc' => 'Acompanhar altera&ccedil;&otilde;es no cadastro',    'page' => 'auditoria_alarmes',  'btn' => 'btn-dark',       'access' => 'admin'],
];
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header <?= $header['class'] ?> text-white">
                    <h3 class="mb-0">
                        <i class="fas <?= $header['icon'] ?> me-2"></i><?= $header['title'] ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <?php foreach ($stats as $s): ?>
                        <div class="col-md-3">
                            <div class="card <?= $s['class'] ?> text-white">
                                <div class="card-body text-center">
                                    <h1 class="mb-0">-</h1>
                                    <p class="mb-0"><?= $s['label'] ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="row">
                        <?php foreach ($actions as $a): ?>
                        <?php if (userHasAccess($a['access'])): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas <?= $a['icon'] ?> fa-3x <?= $a['color'] ?> mb-3"></i>
                                    <h5><?= $a['title'] ?></h5>
                                    <p><?= $a['desc'] ?></p>
                                    <a href="?page=<?= $a['page'] ?>" class="btn <?= $a['btn'] ?>"><?= $a['title'] ?></a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
