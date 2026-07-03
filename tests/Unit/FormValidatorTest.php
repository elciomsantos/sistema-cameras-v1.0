<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormValidatorTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $v = new \FormValidator([]);
        $this->assertInstanceOf(\FormValidator::class, $v);
    }

    public function testRequiredPasses(): void
    {
        $v = new \FormValidator(['nome' => 'Teste']);
        $v->required('nome', 'Nome');
        $this->assertTrue($v->passes());
    }

    public function testRequiredFails(): void
    {
        $v = new \FormValidator(['nome' => '']);
        $v->required('nome', 'Nome');
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('nome', $v->firstError());
    }

    public function testRequiredMissingField(): void
    {
        $v = new \FormValidator([]);
        $v->required('nome', 'Nome');
        $this->assertTrue($v->fails());
    }

    public function testEmailValid(): void
    {
        $v = new \FormValidator(['email' => 'teste@exemplo.com']);
        $v->email('email');
        $this->assertTrue($v->passes());
    }

    public function testEmailInvalid(): void
    {
        $v = new \FormValidator(['email' => 'invalido']);
        $v->email('email');
        $this->assertTrue($v->fails());
    }

    public function testEmailEmptySkips(): void
    {
        $v = new \FormValidator(['email' => '']);
        $v->email('email');
        $this->assertTrue($v->passes());
    }

    public function testNumericValid(): void
    {
        $v = new \FormValidator(['valor' => '123']);
        $v->numeric('valor', 'Valor');
        $this->assertTrue($v->passes());
    }

    public function testNumericInvalid(): void
    {
        $v = new \FormValidator(['valor' => 'abc']);
        $v->numeric('valor', 'Valor');
        $this->assertTrue($v->fails());
    }

    public function testMaxLength(): void
    {
        $v = new \FormValidator(['nome' => 'abc']);
        $v->maxLength('nome', 5, 'Nome');
        $this->assertTrue($v->passes());

        $v2 = new \FormValidator(['nome' => 'abcdef']);
        $v2->maxLength('nome', 5, 'Nome');
        $this->assertTrue($v2->fails());
    }

    public function testInArray(): void
    {
        $v = new \FormValidator(['status' => 'ativo']);
        $v->inArray('status', ['ativo', 'inativo'], 'Status');
        $this->assertTrue($v->passes());

        $v2 = new \FormValidator(['status' => 'inexistente']);
        $v2->inArray('status', ['ativo', 'inativo'], 'Status');
        $this->assertTrue($v2->fails());
    }

    public function testMatches(): void
    {
        $v = new \FormValidator(['senha' => '123', 'confirmar' => '123']);
        $v->matches('senha', 'confirmar', 'Senha', 'Confirmar');
        $this->assertTrue($v->passes());

        $v2 = new \FormValidator(['senha' => '123', 'confirmar' => '456']);
        $v2->matches('senha', 'confirmar', 'Senha', 'Confirmar');
        $this->assertTrue($v2->fails());
    }

    public function testChaining(): void
    {
        $v = new \FormValidator(['nome' => 'João', 'email' => 'joao@exemplo.com']);
        $v->required('nome', 'Nome')
           ->required('email', 'Email')
           ->email('email');
        $this->assertTrue($v->passes());
    }

    public function testGetData(): void
    {
        $v = new \FormValidator(['nome' => 'Teste']);
        $this->assertEquals('Teste', $v->get('nome'));
        $this->assertNull($v->get('inexistente'));
        $this->assertEquals('padrao', $v->get('inexistente', 'padrao'));
        $this->assertEquals(['nome' => 'Teste'], $v->data());
    }

    public function testErrorsByField(): void
    {
        $v = new \FormValidator(['nome' => '']);
        $v->required('nome', 'Nome');
        $errors = $v->errors('nome');
        $this->assertNotEmpty($errors);
        $this->assertEmpty($v->errors('outro'));
    }

    public function testFirstErrorOnEmpty(): void
    {
        $v = new \FormValidator([]);
        $this->assertEquals('', $v->firstError());
    }
}
