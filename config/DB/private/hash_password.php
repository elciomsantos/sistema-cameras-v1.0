<?php
/**
 * Gera hash bcrypt de uma senha.
 * Uso: php hash_password.php <senha>
 * Ex:  php hash_password.php "minha@senha123"
 */

$password = $argv[1] ?? null;
if (!$password) {
    echo "Uso: php hash_password.php \"<senha>\"\n";
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Senha: $password\n";
echo "Hash:  $hash\n";
echo "SQL:   INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, secretaria_id, senha_temporaria, ativo)\n";
echo "       VALUES ('Nome','usuario','$hash',1,NULL,1,1);\n";
