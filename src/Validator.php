<?php

declare(strict_types=1);

namespace WpsMicro\Core;

use WpsMicro\Core\Exceptions\ValidationException;

class Validator
{
    /**
     * Built-in validation rule names.
     *
     * @var list<string>
     */
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
     * Application-defined validation rules.
     *
     * @var array<string, callable(mixed, string, array<array-key, mixed>, ?string): mixed>
     */
    private array $customRules = [];

    /**
     * Register an application-defined validation rule.
     *
     * The callback receives value, field name, complete input, and an optional
     * rule parameter. It returns true or null when valid, false for the default
     * error, or a string containing a custom error message.
     *
     * @param callable(mixed, string, array<array-key, mixed>, ?string): mixed $rule
     */
    public function addRule(string $name, callable $rule): self
    {
        $name = trim($name);

        if ($name === '' || preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('Custom validation rule names must use snake_case.');
        }

        if (in_array($name, self::RULES, true)) {
            throw new \InvalidArgumentException('Built-in validation rules cannot be replaced: '.$name);
        }

        $this->customRules[$name] = $rule;

        return $this;
    }

    /**
     * Validate data with pipe-separated, array, or callable rules.
     *
     * @param array<array-key, mixed>                                   $data
     * @param array<string, mixed> $rules
     *
     * @return array<string, mixed>
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
            $fieldRules = $this->normalizeRules($definition);
            $numericComparison = $this->usesNumericComparison($fieldRules);

            foreach ($fieldRules as $rule) {
                if (!is_string($rule)) {
                    $message = $this->validateCustomRule($rule, (string) $field, $value, $data);
                } else {
                    $message = $this->validateRule(
                        (string) $field,
                        $value,
                        $rule,
                        $data,
                        $numericComparison,
                    );
                }

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
     * Normalize one field's validation rule definition.
     *
     * @param array<int, mixed>|callable|string $definition
     *
     * @return list<callable|string>
     */
    private function normalizeRules(array|callable|string $definition): array
    {
        if (is_string($definition)) {
            return explode('|', $definition);
        }

        if (is_callable($definition)) {
            return [$definition];
        }

        foreach ($definition as $rule) {
            if (!is_string($rule) && !is_callable($rule)) {
                throw new \InvalidArgumentException('Validation rules must be strings or callables.');
            }
        }

        return array_values($definition);
    }

    /**
     * Validate one named rule and return an error message when it fails.
     *
     * @param array<array-key, mixed> $data
     */
    private function validateRule(
        string $field,
        mixed $value,
        string $rule,
        array $data,
        bool $numericComparison,
    ): ?string {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
        $customRule = $this->customRules[$name] ?? null;

        if (!in_array($name, self::RULES, true) && $customRule === null) {
            throw new \InvalidArgumentException('Unknown validation rule: '.$name);
        }

        if ($name === 'nullable' && ($value === null || $value === '')) {
            return null;
        }

        if ($name !== 'required' && ($value === null || $value === '')) {
            return null;
        }

        if ($customRule !== null) {
            return $this->validateCustomRule($customRule, $field, $value, $data, $parameter);
        }

        switch ($name) {
            case 'required':
                return in_array($value, [null, '', []], true) ? $field.' is required.' : null;
            case 'string':
                return !is_string($value) ? $field.' must be a string.' : null;
            case 'array':
                return !is_array($value) ? $field.' must be an array.' : null;
            case 'boolean':
                return !$this->isBoolean($value) ? $field.' must be true or false.' : null;
            case 'email':
                return !is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false
                    ? $field.' must be a valid email.'
                    : null;
            case 'integer':
                return (
                    (!is_int($value) && !is_string($value))
                    || filter_var($value, FILTER_VALIDATE_INT) === false
                )
                    ? $field.' must be an integer.'
                    : null;
            case 'numeric':
                return !$this->isFiniteNumber($value) ? $field.' must be numeric.' : null;
            case 'url':
                return !is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false
                    ? $field.' must be a valid URL.'
                    : null;
            case 'min':
                return $this->validateMinimum($field, $value, $parameter, $numericComparison);
            case 'max':
                return $this->validateMaximum($field, $value, $parameter, $numericComparison);
            case 'in':
                $allowed = $parameter === null ? [] : explode(',', $parameter);

                if (!is_scalar($value)) {
                    return $field.' is invalid.';
                }

                return !in_array((string) $value, $allowed, true) ? $field.' is invalid.' : null;
            case 'confirmed':
                $confirmation = $data[$field.'_confirmation'] ?? null;
                $confirmation = is_string($confirmation) ? trim($confirmation) : $confirmation;

                return $value !== $confirmation ? $field.' confirmation does not match.' : null;
            case 'nullable':
                return null;
        }

    }

    /**
     * Execute a custom validation callback.
     *
     * @param callable(mixed, string, array<array-key, mixed>, ?string): mixed $rule
     * @param array<array-key, mixed>                                          $data
     */
    private function validateCustomRule(
        callable $rule,
        string $field,
        mixed $value,
        array $data,
        ?string $parameter = null,
    ): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        $result = $rule($value, $field, $data, $parameter);

        if ($result === true || $result === null) {
            return null;
        }

        if ($result === false || $result === '') {
            return $field.' is invalid.';
        }

        if (is_string($result)) {
            return $result;
        }

        throw new \UnexpectedValueException(
            'Custom validation rules must return bool, string, or null.',
        );
    }

    /**
     * Validate a minimum numeric value or string length.
     */
    private function validateMinimum(
        string $field,
        mixed $value,
        ?string $parameter,
        bool $numericComparison,
    ): ?string {
        $limit = $this->limit($parameter, 'min');

        if ($numericComparison) {
            if (!$this->isFiniteNumber($value)) {
                return null;
            }

            return $this->compareNumbers($value, $limit) < 0
                ? $field.' must be at least '.$parameter.'.'
                : null;
        }

        if (!is_string($value)) {
            return $field.' must be a string.';
        }

        return $this->length($value) < $limit
            ? $field.' must be at least '.$parameter.' characters.'
            : null;
    }

    /**
     * Validate a maximum numeric value or string length.
     */
    private function validateMaximum(
        string $field,
        mixed $value,
        ?string $parameter,
        bool $numericComparison,
    ): ?string {
        $limit = $this->limit($parameter, 'max');

        if ($numericComparison) {
            if (!$this->isFiniteNumber($value)) {
                return null;
            }

            return $this->compareNumbers($value, $limit) > 0
                ? $field.' may not be greater than '.$parameter.'.'
                : null;
        }

        if (!is_string($value)) {
            return $field.' must be a string.';
        }

        return $this->length($value) > $limit
            ? $field.' may not be greater than '.$parameter.' characters.'
            : null;
    }

    /**
     * Parse and validate a numeric min or max parameter.
     */
    private function limit(?string $parameter, string $rule): string
    {
        if ($parameter === null || !$this->isFiniteNumber($parameter)) {
            throw new \InvalidArgumentException($rule.' validation rule requires a numeric parameter.');
        }

        return trim($parameter);
    }

    /**
     * Accept finite numeric values without coercing booleans.
     *
     * @phpstan-assert-if-true int|float|numeric-string $value
     */
    private function isFiniteNumber(mixed $value): bool
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return false;
        }

        $number = trim((string) $value);

        if (preg_match('/[eE]([+-]?)([0-9]+)$/D', $number, $matches) === 1) {
            $exponent = filter_var($matches[1].(ltrim($matches[2], '0') ?: '0'), FILTER_VALIDATE_INT);

            return $exponent !== false
                && $exponent <= PHP_INT_MAX - strlen($number)
                && $exponent >= PHP_INT_MIN + strlen($number);
        }

        return true;
    }

    /**
     * Compare decimal coefficients and orders without expanding exponent notation.
     */
    private function compareNumbers(int|float|string $value, string $limit): int
    {
        $number = is_float($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (string) $value;
        [$leftSign, $leftDigits, $leftOrder] = $this->decimalParts($number);
        [$rightSign, $rightDigits, $rightOrder] = $this->decimalParts($limit);

        if ($leftSign !== $rightSign) {
            return $leftSign <=> $rightSign;
        }

        if ($leftSign === 0 || $leftOrder !== $rightOrder) {
            return $leftSign * ($leftOrder <=> $rightOrder);
        }

        $length = max(strlen($leftDigits), strlen($rightDigits));

        return $leftSign * (strcmp(
            str_pad($leftDigits, $length, '0'),
            str_pad($rightDigits, $length, '0'),
        ) <=> 0);
    }

    /**
     * Normalize a validated number to sign, significant digits, and decimal order.
     *
     * @return array{int, string, int}
     */
    private function decimalParts(string $number): array
    {
        [$mantissa, $exponent] = array_pad(preg_split('/[eE]/', $number) ?: [], 2, '0');
        $negative = str_starts_with($mantissa, '-');
        $mantissa = ltrim($mantissa, '+-');
        $point = strpos($mantissa, '.');
        $fractionLength = $point === false ? 0 : strlen($mantissa) - $point - 1;
        $digits = ltrim(str_replace('.', '', $mantissa), '0');

        if ($digits === '') {
            return [0, '0', 0];
        }

        $order = (int) $exponent + (strlen($digits) - $fractionLength);

        return [$negative ? -1 : 1, rtrim($digits, '0'), $order];
    }

    /**
     * Check whether min and max should compare numeric values.
     *
     * @param list<callable|string> $rules
     */
    private function usesNumericComparison(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (!is_string($rule)) {
                continue;
            }

            $name = explode(':', $rule, 2)[0];

            if (in_array($name, ['integer', 'numeric'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a UTF-8 string length.
     */
    private function length(string $value): int
    {
        return mb_strlen($value);
    }

    /**
     * Check whether a value is a supported boolean representation.
     */
    private function isBoolean(mixed $value): bool
    {
        return in_array($value, [true, false, 0, 1, '0', '1'], true);
    }
}
