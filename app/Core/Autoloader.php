<?php

namespace App\Core;

/**
 * Autoloader PSR-4 nativo e autossuficiente para o StoqueOnline v2.
 * Permite que a aplicação rode em qualquer hospedagem PHP sem depender
 * de compilação ou do comando `composer dump-autoload`.
 */
class Autoloader
{
    private static string $prefix = 'App\\';
    private static string $baseDir = '';

    public static function register(string $baseDir): void
    {
        self::$baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(function (string $class) {
            $len = strlen(self::$prefix);
            if (strncmp(self::$prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = self::$baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}
