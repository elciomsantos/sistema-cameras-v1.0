<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class RequestValidatorTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $v = new \App\Api\RequestValidator([]);
        $this->assertInstanceOf(\App\Api\RequestValidator::class, $v);
    }

    public function testRequiredPasses(): void
    {
        $v = new \App\Api\RequestValidator(['nome' => 'Teste']);
        $result = $v->validate(['nome' => 'required']);
        $this->assertTrue($result);
        $this->assertFalse($v->fails());
    }

    public function testRequiredFails(): void
    {
        $v = new \App\Api\RequestValidator(['nome' => '']);
        $result = $v->validate(['nome' => 'required']);
        $this->assertFalse($result);
        $this->assertTrue($v->fails());
    }

    public function testStringValidation(): void
    {
        $v = new \App\Api\RequestValidator(['campo' => 'texto']);
        $this->assertTrue($v->validate(['campo' => 'string']));

        $v2 = new \App\Api\RequestValidator(['campo' => 123]);
        $this->assertFalse($v2->validate(['campo' => 'string']));
    }

    public function testNumericValidation(): void
    {
        $v = new \App\Api\RequestValidator(['valor' => '123']);
        $this->assertTrue($v->validate(['valor' => 'numeric']));

        $v2 = new \App\Api\RequestValidator(['valor' => 'abc']);
        $this->assertFalse($v2->validate(['valor' => 'numeric']));
    }

    public function testEmailValidation(): void
    {
        $v = new \App\Api\RequestValidator(['email' => 'teste@exemplo.com']);
        $this->assertTrue($v->validate(['email' => 'email']));

        $v2 = new \App\Api\RequestValidator(['email' => 'invalido']);
        $this->assertFalse($v2->validate(['email' => 'email']));
    }

    public function testMinMaxString(): void
    {
        $v = new \App\Api\RequestValidator(['nome' => 'abc']);
        $this->assertTrue($v->validate(['nome' => 'min:2|max:5']));

        $v2 = new \App\Api\RequestValidator(['nome' => 'a']);
        $this->assertFalse($v2->validate(['nome' => 'min:2']));

        $v3 = new \App\Api\RequestValidator(['nome' => 'abcdef']);
        $this->assertFalse($v3->validate(['nome' => 'max:5']));
    }

    public function testMinMaxNumeric(): void
    {
        $v = new \App\Api\RequestValidator(['idade' => '18']);
        $this->assertTrue($v->validate(['idade' => 'numeric|min:18|max:150']));

        $v2 = new \App\Api\RequestValidator(['idade' => '17']);
        $this->assertFalse($v2->validate(['idade' => 'numeric|min:18']));

        $v3 = new \App\Api\RequestValidator(['idade' => '151']);
        $this->assertFalse($v3->validate(['idade' => 'numeric|max:150']));
    }

    public function testInValidation(): void
    {
        $v = new \App\Api\RequestValidator(['status' => 'ativo']);
        $this->assertTrue($v->validate(['status' => 'in:ativo,inativo,pendente']));

        $v2 = new \App\Api\RequestValidator(['status' => 'inexistente']);
        $this->assertFalse($v2->validate(['status' => 'in:ativo,inativo']));
    }

    public function testRegexValidation(): void
    {
        $v = new \App\Api\RequestValidator(['codigo' => 'ABC-123']);
        $this->assertTrue($v->validate(['codigo' => 'regex:/^[A-Z]{3}-\d{3}$/']));

        $v2 = new \App\Api\RequestValidator(['codigo' => 'invalido']);
        $this->assertFalse($v2->validate(['codigo' => 'regex:/^[A-Z]{3}-\d{3}$/']));
    }

    public function testUrlValidation(): void
    {
        $v = new \App\Api\RequestValidator(['url' => 'https://exemplo.com']);
        $this->assertTrue($v->validate(['url' => 'url']));

        $v2 = new \App\Api\RequestValidator(['url' => 'invalido']);
        $this->assertFalse($v2->validate(['url' => 'url']));
    }

    public function testIpValidation(): void
    {
        $v = new \App\Api\RequestValidator(['ip' => '192.168.1.1']);
        $this->assertTrue($v->validate(['ip' => 'ip']));

        $v2 = new \App\Api\RequestValidator(['ip' => 'invalido']);
        $this->assertFalse($v2->validate(['ip' => 'ip']));
    }

    public function testUuidValidation(): void
    {
        $v = new \App\Api\RequestValidator(['uuid' => '550e8400-e29b-41d4-a716-446655440000']);
        $this->assertTrue($v->validate(['uuid' => 'uuid']));

        $v2 = new \App\Api\RequestValidator(['uuid' => 'invalido']);
        $this->assertFalse($v2->validate(['uuid' => 'uuid']));
    }

    public function testDateValidation(): void
    {
        $v = new \App\Api\RequestValidator(['data' => '2026-01-15']);
        $this->assertTrue($v->validate(['data' => 'date']));

        $v2 = new \App\Api\RequestValidator(['data' => '15/01/2026']);
        $this->assertFalse($v2->validate(['data' => 'date']));
    }

    public function testDateWithCustomFormat(): void
    {
        $v = new \App\Api\RequestValidator(['data' => '15/01/2026']);
        $this->assertTrue($v->validate(['data' => 'date:d/m/Y']));
    }

    public function testJsonValidation(): void
    {
        $v = new \App\Api\RequestValidator(['dados' => '{"key":"value"}']);
        $this->assertTrue($v->validate(['dados' => 'json']));

        $v2 = new \App\Api\RequestValidator(['dados' => 'not json']);
        $this->assertFalse($v2->validate(['dados' => 'json']));
    }

    public function testBooleanValidation(): void
    {
        $v = new \App\Api\RequestValidator(['flag' => true]);
        $this->assertTrue($v->validate(['flag' => 'boolean']));

        $v2 = new \App\Api\RequestValidator(['flag' => 'true']);
        $this->assertTrue($v2->validate(['flag' => 'boolean']));

        $v3 = new \App\Api\RequestValidator(['flag' => 'notabool']);
        $this->assertFalse($v3->validate(['flag' => 'boolean']));
    }

    public function testIntegerValidation(): void
    {
        $v = new \App\Api\RequestValidator(['num' => '42']);
        $this->assertTrue($v->validate(['num' => 'integer']));

        $v2 = new \App\Api\RequestValidator(['num' => '42.5']);
        $this->assertFalse($v2->validate(['num' => 'integer']));
    }

    public function testFloatValidation(): void
    {
        $v = new \App\Api\RequestValidator(['num' => '42.5']);
        $this->assertTrue($v->validate(['num' => 'float']));

        $v2 = new \App\Api\RequestValidator(['num' => 'abc']);
        $this->assertFalse($v2->validate(['num' => 'float']));
    }

    public function testArrayValidation(): void
    {
        $v = new \App\Api\RequestValidator(['items' => [1, 2, 3]]);
        $this->assertTrue($v->validate(['items' => 'array']));

        $v2 = new \App\Api\RequestValidator(['items' => 'not array']);
        $this->assertFalse($v2->validate(['items' => 'array']));
    }

    public function testTypeAliases(): void
    {
        $v = new \App\Api\RequestValidator(['campo' => 'texto']);
        $this->assertTrue($v->validate(['campo' => 'str']));

        $v2 = new \App\Api\RequestValidator(['campo' => '123']);
        $this->assertTrue($v2->validate(['campo' => 'int']));
    }

    public function testOptionalFieldSkipsWhenEmpty(): void
    {
        $v = new \App\Api\RequestValidator(['email' => '']);
        $result = $v->validate(['email' => 'email|max:100']);
        $this->assertTrue($result);
    }

    public function testCustomValidator(): void
    {
        $v = new \App\Api\RequestValidator(['par' => '4']);
        $v->addValidator('par', function ($field, $value) {
            return (int)$value % 2 === 0;
        });

        $this->assertTrue($v->validate(['par' => 'par']));
        $this->assertFalse((new \App\Api\RequestValidator(['par' => '3']))
            ->addValidator('par', function ($f, $v) { return (int)$v % 2 === 0; })
            ->validate(['par' => 'par']));
    }

    public function testCustomMessages(): void
    {
        $v = new \App\Api\RequestValidator(['nome' => '']);
        $v->validate(['nome' => 'required'], [
            'nome' => ['required' => 'Nome é obrigatório']
        ]);
        $errors = $v->errors();
        $this->assertStringContainsString('Nome é obrigatório', $errors['nome'][0]);
    }

    public function testLengthValidation(): void
    {
        $v = new \App\Api\RequestValidator(['codigo' => '12345']);
        $this->assertTrue($v->validate(['codigo' => 'length:5']));

        $v2 = new \App\Api\RequestValidator(['codigo' => '1234']);
        $this->assertFalse($v2->validate(['codigo' => 'length:5']));
    }

    public function testNotInValidation(): void
    {
        $v = new \App\Api\RequestValidator(['tipo' => 'admin']);
        $this->assertTrue($v->validate(['tipo' => 'not_in:root,superuser']));

        $v2 = new \App\Api\RequestValidator(['tipo' => 'root']);
        $this->assertFalse($v2->validate(['tipo' => 'not_in:root,superuser']));
    }

    public function testCpfValidation(): void
    {
        $v = new \App\Api\RequestValidator(['cpf' => '529.982.247-25']);
        $this->assertTrue($v->validate(['cpf' => 'cpf']));

        $v2 = new \App\Api\RequestValidator(['cpf' => '000.000.000-00']);
        $this->assertFalse($v2->validate(['cpf' => 'cpf']));
    }

    public function testCnpjValidation(): void
    {
        $v = new \App\Api\RequestValidator(['cnpj' => '11.444.777/0001-61']);
        $this->assertTrue($v->validate(['cnpj' => 'cnpj']));

        $v2 = new \App\Api\RequestValidator(['cnpj' => '00.000.000/0000-00']);
        $this->assertFalse($v2->validate(['cnpj' => 'cnpj']));
    }

    public function testPasses(): void
    {
        $v = new \App\Api\RequestValidator(['nome' => 'Teste']);
        $v->validate(['nome' => 'required']);
        $this->assertTrue($v->passes());
        $this->assertFalse($v->fails());
    }

    public function testErrorsReturnsAll(): void
    {
        $v = new \App\Api\RequestValidator([]);
        $v->validate(['a' => 'required', 'b' => 'required']);
        $errors = $v->errors();
        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('a', $errors);
        $this->assertArrayHasKey('b', $errors);
    }

    public function testDateBeforeValidation(): void
    {
        $v = new \App\Api\RequestValidator(['data' => '2024-01-01']);
        $this->assertTrue($v->validate(['data' => 'date_before']));

        $v2 = new \App\Api\RequestValidator(['data' => '2099-01-01']);
        $this->assertFalse($v2->validate(['data' => 'date_before']));
    }

    public function testDateAfterValidation(): void
    {
        $v = new \App\Api\RequestValidator(['data' => '2099-01-01']);
        $this->assertTrue($v->validate(['data' => 'date_after']));

        $v2 = new \App\Api\RequestValidator(['data' => '2024-01-01']);
        $this->assertFalse($v2->validate(['data' => 'date_after']));
    }

    public function testObjectValidation(): void
    {
        $v = new \App\Api\RequestValidator(['obj' => (object)[]]);
        $this->assertTrue($v->validate(['obj' => 'object']));

        $v2 = new \App\Api\RequestValidator(['obj' => '{"key":"val"}']);
        $this->assertTrue($v2->validate(['obj' => 'object']));

        $v3 = new \App\Api\RequestValidator(['obj' => 'not json']);
        $this->assertFalse($v3->validate(['obj' => 'object']));
    }
}
