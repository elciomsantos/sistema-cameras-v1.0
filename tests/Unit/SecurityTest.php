<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para funções de segurança
 * 
 * Valida:
 * - Hash de senha com bcrypt
 * - Verificação de senha
 * - Política de senha
 * - CSRF token
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testHashPasswordUsesStrongAlgorithm(): void
    {
        $password = 'MySecurePass123!';
        $hash = hashPassword($password);

        $this->assertStringStartsWith('$argon2id', $hash);
        $this->assertGreaterThanOrEqual(60, strlen($hash));
    }

    public function testVerifyPasswordCorrect(): void
    {
        $password = 'MySecurePass123!';
        $hash = hashPassword($password);

        $this->assertTrue(verifyPassword($password, $hash));
    }

    public function testVerifyPasswordIncorrect(): void
    {
        $password = 'MySecurePass123!';
        $hash = hashPassword($password);

        $this->assertFalse(verifyPassword('WrongPassword123!', $hash));
    }

    public function testPasswordPolicyMinLength(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('12345', $errors);

        $this->assertFalse($result);
        $this->assertNotEmpty($errors);
    }

    public function testPasswordPolicyValid(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('MySecure1!', $errors);

        $this->assertTrue($result);
        $this->assertEmpty($errors);
    }

    public function testPasswordPolicyNumericOnlyFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('12345678', $errors);

        $this->assertFalse($result);
        $this->assertNotEmpty($errors);
    }

    public function testPasswordPolicyShortFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('Ab1', $errors);

        $this->assertFalse($result);
        $this->assertNotEmpty($errors);
    }

    public function testPasswordPolicyNoSpecialFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('MySecure1', $errors);

        $this->assertFalse($result);
        $this->assertStringContainsString('caractere especial', implode(' ', $errors));
    }

    public function testPasswordPolicyNoUppercaseFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('mysecure1!', $errors);

        $this->assertFalse($result);
        $this->assertStringContainsString('maiuscula', implode(' ', $errors));
    }

    public function testPasswordPolicyNoLowercaseFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('MYSECURE1!', $errors);

        $this->assertFalse($result);
        $this->assertStringContainsString('minuscula', implode(' ', $errors));
    }

    public function testGenerateCsrfToken(): void
    {
        $_SESSION['csrf_tokens'] = [];
        
        $token = getCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testValidateCsrfTokenCorrect(): void
    {
        $_SESSION['csrf_tokens'] = [];
        
        $token = getCsrfToken();
        $valid = validateCsrfToken($token);

        $this->assertTrue($valid);
    }

    public function testValidateCsrfTokenIncorrect(): void
    {
        $_SESSION['csrf_tokens'] = ['valid_token_123456789012345678901234567890123456789012345'];
        
        $valid = validateCsrfToken('invalid_token_123456789012345678901234567890123456789012');

        $this->assertFalse($valid);
    }

    public function testAccessLevelMapping(): void
    {
        // DB: admin=1, supervisor=2, user=3
        $this->assertEquals('admin', mapAccessIdToName(1));
        $this->assertEquals('supervisor', mapAccessIdToName(2));
        $this->assertEquals('user', mapAccessIdToName(3));
        $this->assertNull(mapAccessIdToName(99));
    }

    public function testGenerateTemporaryPassword(): void
    {
        $password = generateTemporaryPassword(12);

        $this->assertEquals(12, strlen($password));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9!@#$%&]+$/', $password);
        $this->assertMatchesRegularExpression('/[a-zA-Z]/', $password, 'Deve conter letras');
        $this->assertMatchesRegularExpression('/[0-9]/', $password, 'Deve conter numeros');
        $this->assertMatchesRegularExpression('/[!@#$%&]/', $password, 'Deve conter especial');
    }

    public function testGenerateTemporaryPasswordMultiple(): void
    {
        $hasNumber = $hasLetter = $hasSpecial = false;
        for ($i = 0; $i < 20; $i++) {
            $pwd = generateTemporaryPassword(12);
            if (preg_match('/[0-9]/', $pwd)) $hasNumber = true;
            if (preg_match('/[a-zA-Z]/', $pwd)) $hasLetter = true;
            if (preg_match('/[!@#$%&]/', $pwd)) $hasSpecial = true;
        }
        $this->assertTrue($hasNumber, 'Deve gerar ao menos uma senha com numero em 20 tentativas');
        $this->assertTrue($hasLetter, 'Deve gerar ao menos uma senha com letra em 20 tentativas');
        $this->assertTrue($hasSpecial, 'Deve gerar ao menos uma senha com especial em 20 tentativas');
    }

    public function testDifferentPasswordsDifferentHashes(): void
    {
        $hash1 = hashPassword('Password123!');
        $hash2 = hashPassword('Password456!');

        $this->assertNotEquals($hash1, $hash2);
    }
}
