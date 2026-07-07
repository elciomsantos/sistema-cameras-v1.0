<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../inc/check_access.php';

requererAcesso('admin');

$erro = $_SESSION['erro_cadastro'] ?? null;
unset($_SESSION['erro_cadastro']);
$sucesso = $_SESSION['status_cadastro'] ?? null;
unset($_SESSION['status_cadastro']);
$nomeExiste = $_SESSION['nome_existe'] ?? null;
unset($_SESSION['nome_existe']);
$usuarioExiste = $_SESSION['usuario_existe'] ?? null;
unset($_SESSION['usuario_existe']);
$emailExiste = $_SESSION['email_existe'] ?? null;
unset($_SESSION['email_existe']);
?>

<div class="container mt-4 mb-5">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-1"><i class="fas fa-user-plus me-2"></i>Novo Usu&aacute;rio</h3>
                <p class="text-muted mb-0">Cadastre uma nova conta de acesso ao sistema.</p>
            </div>
            <a href="?page=listarUsuario" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar para lista
            </a>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <?php if ($erro): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($nomeExiste): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>O nome escolhido j&aacute; existe.
                        </div>
                    <?php endif; ?>
                    <?php if ($usuarioExiste): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>O usu&aacute;rio escolhido j&aacute; existe.
                        </div>
                    <?php endif; ?>
                    <?php if ($emailExiste): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>O e-mail escolhido j&aacute; est&aacute; em uso.
                        </div>
                    <?php endif; ?>
                    <?php if ($sucesso): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>Usu&aacute;rio cadastrado com sucesso!
                        </div>
                    <?php endif; ?>

                    <form action="?page=cadastroUsuario" method="post" autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="text_nome" class="form-label">Nome <span class="text-danger">*</span></label>
                            <input type="text" id="text_nome" name="text_nome" class="form-control" placeholder="Nome completo" required maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="text_usuario" class="form-label">Usu&aacute;rio <span class="text-danger">*</span></label>
                            <input type="text" id="text_usuario" name="text_usuario" class="form-control" placeholder="Login de acesso" required maxlength="100" autocapitalize="none">
                        </div>

                        <div class="mb-3">
                            <label for="text_email" class="form-label">E-mail</label>
                            <input type="email" id="text_email" name="text_email" class="form-control" placeholder="email@exemplo.com" maxlength="150" autocomplete="off">
                            <div class="form-text">Opcional. Usado para recuperacao de senha e envio de credenciais.</div>
                        </div>

                        <div class="mb-3">
                            <label for="text_senha" class="form-label">Senha <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" id="text_senha" name="text_senha" class="form-control" placeholder="Senha tempor&aacute;ria" required minlength="8">
                                <button type="button" id="btnGerarSenha" class="btn btn-outline-secondary" title="Gerar senha temporaria forte">
                                    <i class="fas fa-magic me-1"></i>Gerar
                                </button>
                                <button type="button" id="btnToggleSenha" class="btn btn-outline-secondary" title="Mostrar/ocultar senha">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">M&iacute;nimo 8 caracteres, com mai&uacute;sculas, min&uacute;sculas, n&uacute;meros e um caractere especial.</div>
                        </div>

                        <div class="mb-4">
                            <label for="text_nivel_acesso" class="form-label">N&iacute;vel de Acesso <span class="text-danger">*</span></label>
                            <select id="text_nivel_acesso" name="text_nivel_acesso" class="form-select" required>
                                <option value="user">Usu&aacute;rio</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Cadastrar
                            </button>
                            <a href="?page=listarUsuario" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($CSP_NONCE ?? '', ENT_QUOTES, 'UTF-8') ?>">
(function () {
    function gerarSenhaTemporaria(len) {
        var mai = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        var min = 'abcdefghijkmnpqrstuvwxyz';
        var num = '23456789';
        var esp = '!@#$%*?';
        var all = mai + min + num + esp;
        var senha = '';
        senha += mai.charAt(Math.floor(Math.random() * mai.length));
        senha += min.charAt(Math.floor(Math.random() * min.length));
        senha += num.charAt(Math.floor(Math.random() * num.length));
        senha += esp.charAt(Math.floor(Math.random() * esp.length));
        for (var i = senha.length; i < (len || 12); i++) {
            senha += all.charAt(Math.floor(Math.random() * all.length));
        }
        return senha.split('').sort(function () { return Math.random() - 0.5; }).join('');
    }

    var btnGerar = document.getElementById('btnGerarSenha');
    var inputSenha = document.getElementById('text_senha');
    if (btnGerar && inputSenha) {
        btnGerar.addEventListener('click', function () {
            var novaSenha = gerarSenhaTemporaria(12);
            inputSenha.value = novaSenha;
            inputSenha.type = 'text';
            inputSenha.focus();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(novaSenha).catch(function () {});
            }
        });
    }

    var btnToggle = document.getElementById('btnToggleSenha');
    if (btnToggle && inputSenha) {
        btnToggle.addEventListener('click', function () {
            var icon = btnToggle.querySelector('i');
            if (inputSenha.type === 'password') {
                inputSenha.type = 'text';
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            } else {
                inputSenha.type = 'password';
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            }
        });
    }
})();
</script>
