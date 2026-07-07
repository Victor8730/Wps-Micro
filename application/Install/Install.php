<?php

declare(strict_types=1);

namespace Install;

use Core\Validator;

class Install extends Validator
{
    /**
     * Recursively copy missing files and directories.
     */
    public function copyFileAndDirectory(string $dirSource, string $dirDestination): void
    {
        if (!is_dir($dirSource)) {
            return;
        }

        $dir = opendir($dirSource);

        if ($dir === false) {
            return;
        }

        while (($file = readdir($dir)) !== false) {
            if (is_dir($dirSource . "/" . $file) && $file !== "." && $file !== "..") {
                if (!file_exists($dirDestination . "/" . $file)) {
                    $this->checkMakeDir($dirDestination . "/" . $file);
                }

                $this->copyFileAndDirectory($dirSource . "/" . $file, $dirDestination . "/" . $file);
            }

            if (is_file($dirSource . "/" . $file) && !file_exists($dirDestination . "/" . $file)) {
                $this->checkCopyFile($dirSource . "/" . $file, $dirDestination . "/" . $file);
            }
        }

        closedir($dir);
    }

    /**
     * Remove generated Twig cache files.
     */
    public function clearCache(string $address): void
    {
        if (file_exists($address . '/Cache/')) {
            foreach (glob($address . '/Cache/*') ?: [] as $folder) {
                foreach (glob($folder . '/*') ?: [] as $file2) {
                    unlink($file2);
                }
                rmdir($folder);
            }
        }
    }
}
