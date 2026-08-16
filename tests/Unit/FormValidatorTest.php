<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormValidatorTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $v = new \App\Validation\FormValidator([]);
        $this->assertInstanceOf(\App\Validation\FormValidator::class, $v);
    }

    public function testRequiredPasses(): void
    {
        $v = new \App\Validation\FormValidator(['nome' => 'Teste']);
        $v->required('nome', 'Nome');
        $this->assertTrue($v->passes());
    }

    public function testRequiredFails(): void
    {
        $v = new \App\Validation\FormValidator(['nome' => '']);
        $v->required('nome', 'Nome');
        $this->assertTrue($v->fails());
        $this->assertStringContainsString('Nome', $v->firstError());
    }

    public function testRequiredMissingField(): void
    {
        $v = new \App\Validation\FormValidator([]);
        $v->required('nome', 'Nome');
        $this->assertTrue($v->fails());
    }

    public function testEmailValid(): void
    {
        $v = new \App\Validation\FormValidator(['email' => 'teste@exemplo.com']);
        $v->email('email');
        $this->assertTrue($v->passes());
    }

    public function testEmailInvalid(): void
    {
        $v = new \App\Validation\FormValidator(['email' => 'invalido']);
        $v->email('email');
        $this->assertTrue($v->fails());
    }

    public function testEmailEmptySkips(): void
    {
        $v = new \App\Validation\FormValidator(['email' => '']);
        $v->email('email');
        $this->assertTrue($v->passes());
    }

    public function testNumericValid(): void
    {
        $v = new \App\Validation\FormValidator(['valor' => '123']);
        $v->numeric('valor', 'Valor');
        $this->assertTrue($v->passes());
    }

    public function testNumericInvalid(): void
    {
        $v = new \App\Validation\FormValidator(['valor' => 'abc']);
        $v->numeric('valor', 'Valor');
        $this->assertTrue($v->fails());
    }

    public function testMaxLength(): void
    {
        $v = new \App\Validation\FormValidator(['nome' => 'abc']);
        $v->maxLength('nome', 5, 'Nome');
        $this->assertTrue($v->passes());

        $v2 = new \App\Validation\FormValidator(['nome' => 'abcdef']);
        $v2->maxLength('nome', 5, 'Nome');
        $this->assertTrue($v2->fails());
    }

    public function testInArray(): void
    {
        $v = new \App\Validation\FormValidator(['status' => 'ativo']);
        $v->inArray('status', ['ativo', 'inativo'], 'Status');
        $this->assertTrue($v->passes());

        $v2 = new \App\Validation\FormValidator(['status' => 'inexistente']);
        $v2->inArray('status', ['ativo', 'inativo'], 'Status');
        $this->assertTrue($v2->fails());
    }

    public function testMatches(): void
    {
        $v = new \App\Validation\FormValidator(['senha' => '123', 'confirmar' => '123']);
        $v->matches('senha', 'confirmar', 'Senha', 'Confirmar');
        $this->assertTrue($v->passes());

        $v2 = new \App\Validation\FormValidator(['senha' => '123', 'confirmar' => '456']);
        $v2->matches('senha', 'confirmar', 'Senha', 'Confirmar');
        $this->assertTrue($v2->fails());
    }

    public function testChaining(): void
    {
        $v = new \App\Validation\FormValidator(['nome' => 'João', 'email' => 'joao@exemplo.com']);
        $v->required('nome', 'Nome')
           ->required('email', 'Email')
           ->email('email');
        $this->assertTrue($v->passes());
    }

    public function testGetData(): void
    {
        $v = new \App\Validation\FormValidator(['nome' => 'Teste']);
        $this->assertEquals('Teste', $v->get('nome'));
        $this->assertNull($v->get('inexistente'));
        $this->assertEquals('padrao', $v->get('inexistente', 'padrao'));
        $this->assertEquals(['nome' => 'Teste'], $v->data());
    }

    public function testErrorsByField(): void
    {
        $v = new \App\Validation\FormValidator(['nome' => '']);
        $v->required('nome', 'Nome');
        $errors = $v->errors('nome');
        $this->assertNotEmpty($errors);
        $this->assertEmpty($v->errors('outro'));
    }

    public function testFirstErrorOnEmpty(): void
    {
        $v = new \App\Validation\FormValidator([]);
        $this->assertEquals('', $v->firstError());
    }
}
