<?php

declare(strict_types=1);

namespace App\Validation;

class FormValidator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function checkSubmission(): ?string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
            return 'Metodo invalido.';
        }
        $csrfToken = $_POST['csrf_token'] ?? null;
        if (!validateCsrfToken($csrfToken)) {
            return 'Falha de validacao CSRF.';
        }
        return null;
    }

    public function required(string $field, string $label): self
    {
        $value = trim($this->data[$field] ?? '');
        if ($value === '') {
            $this->addError($field, "O campo {$label} e obrigatorio.");
        }
        return $this;
    }

    public function email(string $field): self
    {
        $value = trim($this->data[$field] ?? '');
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'E-mail invalido.');
        }
        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        $value = trim($this->data[$field] ?? '');
        if ($value !== '' && !is_numeric($value)) {
            $this->addError($field, "O campo {$label} deve ser numerico.");
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        $value = trim($this->data[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->addError($field, "O campo {$label} deve ter no maximo {$max} caracteres.");
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, string $label): self
    {
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->addError($field, "O campo {$label} contem um valor invalido.");
        }
        return $this;
    }

    public function matches(string $field, string $otherField, string $label, string $otherLabel): self
    {
        $value = $this->data[$field] ?? '';
        $other = $this->data[$otherField] ?? '';
        if ((string)$value !== (string)$other) {
            $this->addError($field, "O campo {$label} nao confere com {$otherLabel}.");
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function firstError(): string
    {
        foreach ($this->errors as $field => $msgs) {
            return $msgs[0];
        }
        return '';
    }

    public function errors(?string $field = null): array
    {
        if ($field !== null) {
            return $this->errors[$field] ?? [];
        }
        return $this->errors;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function data(): array
    {
        return $this->data;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
