<?php

class AuthService
{
    private database $db;
    private string $ip;
    private string $username;
    private string $password;
    private int $now;
    private array $attempt;
    private array $ipAttempt;
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockSeconds;
    private int $maxIpAttempts = 15;
    private object $userRow;
    private const IP_LOCK_KEY = '__ip_lock__';

    public function __construct(?string $username, ?string $password, $db = null)
    {
        $this->db = $db ?? db();
        $this->username = $username ?? '';
        $this->password = $password ?? '';
        $this->ip = resolveClientIp();
        $this->now = time();
        $this->maxAttempts = LOGIN_MAX_ATTEMPTS;
        $this->windowSeconds = LOGIN_WINDOW_SECONDS;
        $this->lockSeconds = LOGIN_LOCK_SECONDS;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getNow(): int
    {
        return $this->now;
    }

    public function authenticate(): array
    {
        $this->validateInput();
        $this->checkIpRateLimit();
        $this->checkUserRateLimit();
        $this->loadUser();
        $this->setUserRow($this->userRow);
        $this->verifyPassword();
        $this->checkActive();
        $this->clearAttempt();
        $this->loadAccessLevel();
        $this->createSession();

        return [
            'success' => true,
            'usuario' => $this->userRow,
        ];
    }

    private function validateInput(): void
    {
        if ($this->username !== null && mb_strlen($this->username) > 255) {
            $this->fail('Falha de validacao.');
        }
        if ($this->password !== null && mb_strlen($this->password) > 128) {
            $this->fail('Falha de validacao.');
        }
        if (!$this->username || !$this->password) {
            $this->fail('Usuario ou senha invalidos');
        }
    }

    private function checkIpRateLimit(): void
    {
        $this->ipAttempt = ['count' => 0, 'first_at' => $this->now, 'locked_until' => 0];

        try {
            $ipAttemptFromDb = $this->readLoginAttempt(self::IP_LOCK_KEY, $this->ip);
            if ($ipAttemptFromDb !== null) {
                $this->ipAttempt = $ipAttemptFromDb;
                if (!empty($this->ipAttempt['locked_until']) && $this->ipAttempt['locked_until'] > $this->now) {
                    $this->fail('Muitas tentativas. Tente novamente em alguns minutos.');
                }
            }
        } catch (Throwable $e) {
            $this->fail('Erro interno. Tente novamente.');
        }

        if (($this->now - (int)$this->ipAttempt['first_at']) > $this->windowSeconds) {
            $this->ipAttempt = ['count' => 0, 'first_at' => $this->now, 'locked_until' => 0];
        }
    }

    private function checkUserRateLimit(): void
    {
        $this->attempt = ['count' => 0, 'first_at' => $this->now, 'locked_until' => 0];

        $this->db->beginTransaction();
        try {
            $attemptFromDb = $this->readLoginAttempt($this->username, $this->ip, true);
            if ($attemptFromDb !== null) {
                $this->attempt = $attemptFromDb;
                if (!empty($this->attempt['locked_until']) && $this->attempt['locked_until'] > $this->now) {
                    $this->db->commit();
                    $this->fail('Muitas tentativas. Tente novamente em alguns minutos.');
                }
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            $this->fail('Erro interno. Tente novamente.');
        }

        if (($this->now - (int)$this->attempt['first_at']) > $this->windowSeconds) {
            $this->attempt = ['count' => 0, 'first_at' => $this->now, 'locked_until' => 0];
        }
    }

    private function readLoginAttempt(string $username, string $ip, bool $forUpdate = false): ?array
    {
        $lockSql = $forUpdate ? ' FOR UPDATE' : '';
        $attemptQuery = $this->db->query(
            "SELECT attempt_count, first_attempt_at, locked_until
             FROM login_attempts
             WHERE username = :username
               AND ip_address = :ip_address{$lockSql}",
            [':username' => $username, ':ip_address' => $ip]
        );
        if (($attemptQuery['status'] ?? '') !== 'success' || empty($attemptQuery['data'])) {
            return null;
        }
        $row = $attemptQuery['data'][0];
        return [
            'count' => (int)($row->attempt_count ?? 0),
            'first_at' => !empty($row->first_attempt_at) ? (strtotime((string)$row->first_attempt_at) ?: $this->now) : $this->now,
            'locked_until' => !empty($row->locked_until) ? (strtotime((string)$row->locked_until) ?: 0) : 0,
        ];
    }

    private function persistLoginAttempt(string $username, string $ip, array $attempt): void
    {
        $this->db->query(
            "INSERT INTO login_attempts (username, ip_address, attempt_count, first_attempt_at, locked_until)
             VALUES (:username, :ip_address, :attempt_count, :first_attempt_at, :locked_until)
             ON DUPLICATE KEY UPDATE
                attempt_count = attempt_count + 1,
                first_attempt_at = COALESCE(first_attempt_at, VALUES(first_attempt_at)),
                locked_until = VALUES(locked_until)",
            [
                ':username' => $username,
                ':ip_address' => $ip,
                ':attempt_count' => (int)$attempt['count'],
                ':first_attempt_at' => date('Y-m-d H:i:s', (int)$attempt['first_at']),
                ':locked_until' => ((int)$attempt['locked_until'] > 0) ? date('Y-m-d H:i:s', (int)$attempt['locked_until']) : null,
            ]
        );
    }

    private function incrementIpAttempt(): void
    {
        $this->ipAttempt['count']++;
        if ($this->ipAttempt['count'] >= $this->maxIpAttempts) {
            $this->ipAttempt['locked_until'] = $this->now + $this->lockSeconds;
        }
        $this->persistLoginAttempt(self::IP_LOCK_KEY, $this->ip, $this->ipAttempt);
    }

    private function loadUser(): void
    {
        $sql = "SELECT id, usuario, nome, senha, ativo, nivel_acesso_id, secretaria_id, senha_temporaria
                FROM usuarios WHERE usuario = :usuario";
        $result = $this->db->query($sql, [':usuario' => $this->username]);

        if ($result['status'] === 'error') {
            $this->fail('Erro interno. Tente novamente.');
        }
        if (!isset($result['data']) || count($result['data']) === 0) {
            $this->incrementIpAttempt();
            $this->incrementAttempt();
            $this->db->commit();
            $this->verifyPasswordDummy();
            $this->fail('Usuario ou senha invalidos');
        }

        $this->userRow = $result['data'][0];
    }

    public function setUserRow(object $row): void
    {
        $this->userRow = $row;
    }

    private function incrementAttempt(): void
    {
        $this->attempt['count']++;
        if ($this->attempt['count'] >= $this->maxAttempts) {
            $this->attempt['locked_until'] = $this->now + $this->lockSeconds;
        }
        $this->persistLoginAttempt($this->username, $this->ip, $this->attempt);
    }

    private function verifyPasswordDummy(): void
    {
        $dummyHash = '';
        verifyPassword($this->password, '$2y$10$00000000000000000000000000000000000000000000000000000', $dummyHash);
    }

    private function verifyPassword(): void
    {
        $senhaHash = $this->userRow->senha;
        $newHash = null;

        if (!verifyPassword($this->password, $senhaHash, $newHash)) {
            $this->incrementIpAttempt();
            $this->incrementAttempt();
            $this->db->commit();
            $this->fail('Usuario ou senha invalidos');
        }

        if ($newHash) {
            $this->db->query("UPDATE usuarios SET senha = :senha WHERE id = :id", [
                ':senha' => $newHash,
                ':id' => $this->userRow->id,
            ]);
        }
    }

    private function checkActive(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
        if ((int)($this->userRow->ativo ?? 0) !== 1) {
            $this->fail('Credenciais invalidas ou conta desativada.');
        }
    }

    private function clearAttempt(): void
    {
        $this->db->query(
            "DELETE FROM login_attempts
             WHERE username = :username
               AND ip_address = :ip_address",
            [':username' => $this->username, ':ip_address' => $this->ip]
        );
    }

    private function loadAccessLevel(): void
    {
        $nivelAcessoQuery = $this->db->query(
            "SELECT nome FROM niveis_acesso WHERE id = :id",
            [':id' => $this->userRow->nivel_acesso_id]
        );
        if ($nivelAcessoQuery['status'] === 'success' && !empty($nivelAcessoQuery['data'])) {
            $this->userRow->nivel_acesso = $nivelAcessoQuery['data'][0]->nome;
        } else {
            $this->userRow->nivel_acesso = 'user';
        }
    }

    private function createSession(): void
    {
        session_regenerate_id(true);
        $_SESSION['ultimo_acesso'] = $this->now;
        $_SESSION['session_started_at'] = $this->now;

        unset($this->userRow->senha);
        $_SESSION['usuario'] = $this->userRow;

        try {
            $sessionToken = createSessionToken();
            $_SESSION['session_token'] = $sessionToken;
            registerUniqueSession($this->db, (int)$this->userRow->id, $sessionToken);
        } catch (Throwable $e) {
            error_log('Erro ao registrar sessao unica no login: ' . $e->getMessage());
            unset($_SESSION['usuario'], $_SESSION['session_token']);
            $this->fail('Nao foi possivel iniciar a sessao. Tente novamente.');
        }
    }

    private function fail(string $message): void
    {
        throw new AuthException($message);
    }
}
