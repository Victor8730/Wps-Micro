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
    NotValidInputException
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
