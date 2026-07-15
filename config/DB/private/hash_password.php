<?php
/**
 * Gera hash de senha usando o padrão do sistema (Argon2ID ou bcrypt).
 * Uso: php hash_password.php <senha>
 * Ex:  php hash_password.php "minha@senha123"
 */

require_once __DIR__ . '/../../../inc/security.php';

$password = $argv[1] ?? null;
if (!$password) {
    echo "Uso: php hash_password.php \"<senha>\"\n";
    exit(1);
}

$config = hashPasswordOptions();
$algo = $config['algo'] === PASSWORD_ARGON2ID ? 'Argon2ID' : 'bcrypt';
$hash = hashPassword($password);
echo "Senha:  $password\n";
echo "Algo:   $algo\n";
echo "Hash:   $hash\n";
echo "SQL:    INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, secretaria_id, senha_temporaria, ativo)\n";
echo "        VALUES ('Nome','usuario','$hash',1,NULL,1,1);\n";
