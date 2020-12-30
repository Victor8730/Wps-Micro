<?php

declare(strict_types=1);

namespace Core;

use Exceptions\{FailedCopyException,
    FailedCreateDirException,
    NotExistFileException,
    NotExistFileFromUrlException,
    NotExistMethodException,
    NotExistClassException,
    NotValidInputException
};

class  Validator
{
    /**
     * Variable showing if there are errors
     * @var bool
     */
    private bool $errors;

    /**
     * Input constructor.
     * We say that initially there are no mistakes
     */
    public function __construct()
    {
        $this->errors = false;
    }

    /**
     * Checking for errors
     * @return bool
     */
    public function isErrors(): bool
    {
        return $this->errors;
    }

    /**
     * Writing down errors
     * @param bool $errors
     */
    private function setErrors(bool $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * Check is the variable empty
     * @param $val
     * @return bool
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
     * String validation
     * @param $val
     * @return string
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
     * Email validation
     * @param string|null $val
     * @return string
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
     * url validation
     * @param string|null $val
     * @return string
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
     * Int validate
     * @param int $val
     * @return int
     * @throws NotValidInputException
     */
    public function checkInt(int $val)
    {
        $check = filter_var($val, FILTER_VALIDATE_INT);
        if ($check !== false) {
            return $val;
        } else {
            $this->setErrors(true);
            throw new NotValidInputException(strval($val));
        }
    }

    /**
     * Checks the existence of a file
     * @param string $file
     * @return false|string
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
     * Checking if a method exists in the class
     * @param object|null $controller
     * @param string $actionName
     * @throws NotExistMethodException
     */
    public function checkMethodExist(?object $controller, string $actionName): void
    {
        if (!method_exists($controller, $actionName)) {
            throw new NotExistMethodException($controller, $actionName);
        }
    }


    /**
     * Check if a file exists on another site
     * @param string $url
     * @return void
     * @throws NotExistFileFromUrlException
     */
    public function checkFileExistFromUrl(string $url): void
    {
        try {
            $url = $this->checkUrl($url);
            $urlHeaders = @get_headers($url);

            if (!is_array($urlHeaders) || !strpos($urlHeaders[0], '200')) {
                throw new NotExistFileFromUrlException($url);
            }
        } catch (NotValidInputException $e) {
            throw new NotExistFileFromUrlException($url);
        }
    }

    /**
     * Checking if a class exists
     * @param string $className
     * @throws NotExistClassException
     */
    public function checkClassExist(string $className): void
    {
        if (!class_exists($className)) {
            throw new NotExistClassException($className);
        }
    }

    /**
     * Try copy file from one location to another,
     * if not luck then throw an exception FailedCopyException
     * @param string $dirSource
     * @param string $dirDest
     * @throws FailedCopyException
     */
    protected function checkCopyFile(string $dirSource, string $dirDest): void
    {
        if (!copy($dirSource, $dirDest)) {
            throw new FailedCopyException($dirSource);
        }
    }

    /**
     * We try to create a directory,
     * if not luck then throw an exception FailedCreateDirException
     * @param string $dirSource
     * @throws FailedCreateDirException
     */
    protected function checkMakeDir(string $dirSource): void
    {
        if (!mkdir($dirSource)) {
            throw new FailedCreateDirException($dirSource);
        }
    }
}