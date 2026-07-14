<?php

declare(strict_types=1);

namespace Core;

use Exceptions\{
    FailedCopyException,
    FailedCreateDirException,
    NotExistFileException,
    NotExistFileFromUrlException,
    NotExistMethodException,
    NotExistClassException,
    NotValidInputException,
    ValidationException
};

class Validator
{
    /**
     * Indicates whether validation has failed.
     */
    private bool $errors;

    /**
     * Initialize the validator state.
     */
    public function __construct()
    {
        $this->errors = false;
    }

    /**
     * Check whether any validation errors were recorded.
     */
    public function isErrors(): bool
    {
        return $this->errors;
    }

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
            $fieldRules = is_array($definition) ? $definition : explode('|', (string) $definition);

            foreach ($fieldRules as $rule) {
                $message = $this->validateRule((string) $field, $value, (string) $rule);

                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }

            if (!array_key_exists($field, $errors) && array_key_exists($field, $data)) {
                $validated[$field] = is_string($value) ? trim($value) : $value;
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

    /**
     * Store the validation error state.
     */
    private function setErrors(bool $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * Check whether a value is not empty.
     *
     * @throws NotValidInputException
     */
    public function checkEmpty($val): bool
    {
        if (!empty($val)) {
            return true;
        } else {
            $this->setErrors(true);
            throw new NotValidInputException($val);
        }
    }

    /**
     * Validate and sanitize a string value.
     *
     * @throws NotValidInputException
     */
    public function checkStr($val): string
    {
        if (!is_string($val)) {
            $this->setErrors(true);
            throw new NotValidInputException($val);
        } else {
            return trim(strip_tags($val));
        }
    }

    /**
     * Validate an email address.
     *
     * @throws NotValidInputException
     */
    public function checkEmail(?string $val): string
    {
        $check = filter_var($val, FILTER_VALIDATE_EMAIL);
        if ($check !== false) {
            return $val;
        } else {
            $this->setErrors(true);
            throw new NotValidInputException($val);
        }
    }

    /**
     * Validate a URL.
     *
     * @throws NotValidInputException
     */
    public function checkUrl(?string $val): string
    {
        $check = filter_var($val, FILTER_VALIDATE_URL);
        if ($check !== false) {
            return $val;
        } else {
            $this->setErrors(true);
            throw new NotValidInputException($val);
        }
    }

    /**
     * Validate an integer value.
     *
     * @throws NotValidInputException
     */
    public function checkInt($val): int
    {
        $check = filter_var($val, FILTER_VALIDATE_INT);
        if ($check !== false) {
            return (int) $val;
        } else {
            $this->setErrors(true);
            throw new NotValidInputException(strval($val));
        }
    }

    /**
     * Check whether a file exists.
     *
     * @throws NotExistFileException
     */
    public function checkFileExist(string $file): bool
    {
        if (!file_exists($file)) {
            throw new NotExistFileException($file);
        } else {
            return true;
        }
    }

    /**
     * Check whether a method exists on a controller.
     *
     * @throws NotExistMethodException
     */
    public function checkMethodExist(?object $controller, string $actionName): void
    {
        if (!method_exists($controller, $actionName)) {
            throw new NotExistMethodException($controller, $actionName);
        }
    }


    /**
     * Check whether a remote URL returns a successful response.
     *
     * @throws NotExistFileFromUrlException
     */
    public function checkFileExistFromUrl(string $url): void
    {
        try {
            $url = $this->checkUrl($url);
            $urlHeaders = @get_headers($url);

            if (!is_array($urlHeaders) || !preg_match('/\s2\d\d\s/', $urlHeaders[0])) {
                throw new NotExistFileFromUrlException($url);
            }
        } catch (NotValidInputException $e) {
            throw new NotExistFileFromUrlException($url);
        }
    }

    /**
     * Check whether a class exists.
     *
     * @throws NotExistClassException
     */
    public function checkClassExist(string $className): void
    {
        if (!class_exists($className)) {
            throw new NotExistClassException($className);
        }
    }

    /**
     * Copy a file or throw an exception on failure.
     *
     * @throws FailedCopyException
     */
    protected function checkCopyFile(string $dirSource, string $dirDest): void
    {
        if (!copy($dirSource, $dirDest)) {
            throw new FailedCopyException($dirSource);
        }
    }

    /**
     * Create a directory or throw an exception on failure.
     *
     * @throws FailedCreateDirException
     */
    protected function checkMakeDir(string $dirSource): void
    {
        if (!mkdir($dirSource, 0775, true) && !is_dir($dirSource)) {
            throw new FailedCreateDirException($dirSource);
        }
    }
}
