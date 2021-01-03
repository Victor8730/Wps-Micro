<?php

declare(strict_types=1);

namespace Install;

use Core\Validator;
use Exceptions\FailedCopyException;
use Exceptions\FailedCreateDirException;

class Install extends Validator
{
    public function copyFileAndDirectory(string $dirSource, string $dirDestination): void
    {
        $validator = new Validator();
        $dir = opendir($dirSource);

        while (($file = readdir($dir)) !== false) {
            if (is_dir($dirSource . "/" . $file) && $file != "." && $file != "..") {
                try {
                    if (!file_exists($dirDestination . "/" . $file)) {
                        $validator->checkMakeDir($dirDestination . "/" . $file);
                    }
                } catch (FailedCreateDirException $e) {
                }
                $this->copyFileAndDirectory($dirSource . "/" . $file, $dirDestination . "/" . $file);
            }
            if (is_file($dirSource . "/" . $file) && !file_exists($dirDestination . "/" . $file)) {
                try {
                    $validator->checkCopyFile($dirSource . "/" . $file, $dirDestination . "/" . $file);
                } catch (FailedCopyException $e) {
                }
            }
        }
        closedir($dir);
    }

    public function clearCache($address)
    {
        if (file_exists($address . '/Cache/')) {
            foreach (glob($address . '/Cache/*') as $folder) {
                foreach (glob($folder . '/*') as $file2) {
                    unlink($file2);
                }
                rmdir($folder);
            }
        }
    }
}