<?php

declare(strict_types=1);

namespace WpsMicro\Core;

use WpsMicro\Core\Exceptions\ValidationException;

class Validator
{
    private const RULES = [
        'required',
        'nullable',
        'confirmed',
        'string',
        'array',
        'boolean',
        'email',
        'url',
        'integer',
        'numeric',
        'min',
        'max',
        'in',
    ];

    /**
     * Validate data with simple pipe-separated rules.
     *
     * @throws ValidationException
     */
    public function validate(array $data, array $rules): array
    {
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $definition) {
            $value = $data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $fieldRules = is_array($definition) ? $definition : explode('|', (string) $definition);

            foreach ($fieldRules as $rule) {
                $message = $this->validateRule((string) $field, $value, (string) $rule, $data);

                if ($message !== null && !in_array($message, $errors[$field] ?? [], true)) {
                    $errors[$field][] = $message;
                }
            }

            if (!array_key_exists($field, $errors) && array_key_exists($field, $data)) {
                $validated[$field] = $value;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $validated;
    }

    /**
     * Validate one rule and return an error message when it fails.
     *
     * @param mixed $value
     */
    private function validateRule(string $field, mixed $value, string $rule, array $data): ?string
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        if (!in_array($name, self::RULES, true)) {
            throw new \InvalidArgumentException('Unknown validation rule: ' . $name);
        }

        if ($name === 'nullable' && ($value === null || $value === '')) {
            return null;
        }

        if ($name !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        switch ($name) {
            case 'required':
                return in_array($value, [null, '', []], true) ? $field . ' is required.' : null;
            case 'string':
                return !is_string($value) ? $field . ' must be a string.' : null;
            case 'array':
                return !is_array($value) ? $field . ' must be an array.' : null;
            case 'boolean':
                return !$this->isBoolean($value) ? $field . ' must be true or false.' : null;
            case 'email':
                return !is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false
                    ? $field . ' must be a valid email.'
                    : null;
            case 'integer':
                return (
                    (!is_int($value) && !is_string($value))
                    || filter_var($value, FILTER_VALIDATE_INT) === false
                )
                    ? $field . ' must be an integer.'
                    : null;
            case 'numeric':
                return is_bool($value) || !is_numeric($value) ? $field . ' must be numeric.' : null;
            case 'url':
                return !is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false
                    ? $field . ' must be a valid URL.'
                    : null;
            case 'min':
                if (!is_string($value)) {
                    return $field . ' must be a string.';
                }

                return $this->length($value) < (int) $parameter
                    ? $field . ' must be at least ' . $parameter . ' characters.'
                    : null;
            case 'max':
                if (!is_string($value)) {
                    return $field . ' must be a string.';
                }

                return $this->length($value) > (int) $parameter
                    ? $field . ' may not be greater than ' . $parameter . ' characters.'
                    : null;
            case 'in':
                $allowed = $parameter === null ? [] : explode(',', $parameter);

                if (!is_scalar($value)) {
                    return $field . ' is invalid.';
                }

                return !in_array((string) $value, $allowed, true) ? $field . ' is invalid.' : null;
            case 'confirmed':
                $confirmation = $data[$field . '_confirmation'] ?? null;
                $confirmation = is_string($confirmation) ? trim($confirmation) : $confirmation;

                return $value !== $confirmation ? $field . ' confirmation does not match.' : null;
            case 'nullable':
                return null;
        }

        throw new \LogicException('Validation rule was not handled: ' . $name);
    }

    /**
     * Return a string length.
     *
     * @param mixed $value
     */
    private function length(string $value): int
    {
        return mb_strlen($value);
    }

    /**
     * Check whether a value is a supported boolean representation.
     *
     * @param mixed $value
     */
    private function isBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }
}
