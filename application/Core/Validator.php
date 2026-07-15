<?php

declare(strict_types=1);

namespace Core;

use Exceptions\ValidationException;

class Validator
{
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
                $message = $this->validateRule((string) $field, $value, (string) $rule);

                if ($message !== null) {
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
    private function validateRule(string $field, $value, string $rule): ?string
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        if ($name === 'nullable' && ($value === null || $value === '')) {
            return null;
        }

        if ($name !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        switch ($name) {
            case 'required':
                return $value === null || $value === '' ? $field . ' is required.' : null;
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? $field . ' must be a valid email.' : null;
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) === false ? $field . ' must be an integer.' : null;
            case 'numeric':
                return !is_numeric($value) ? $field . ' must be numeric.' : null;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) === false ? $field . ' must be a valid URL.' : null;
            case 'min':
                return $this->length($value) < (int) $parameter ? $field . ' must be at least ' . $parameter . ' characters.' : null;
            case 'max':
                return $this->length($value) > (int) $parameter ? $field . ' may not be greater than ' . $parameter . ' characters.' : null;
            case 'in':
                $allowed = $parameter === null ? [] : explode(',', $parameter);

                return !in_array((string) $value, $allowed, true) ? $field . ' is invalid.' : null;
            case 'nullable':
                return null;
            default:
                throw new \InvalidArgumentException('Unknown validation rule: ' . $name);
        }
    }

    /**
     * Return a string length.
     *
     * @param mixed $value
     */
    private function length($value): int
    {
        return mb_strlen((string) $value);
    }
}
