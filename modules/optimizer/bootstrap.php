<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

if (!defined('OPTIMIZER_AUTOLOAD_REGISTERED')) {
    define('OPTIMIZER_AUTOLOAD_REGISTERED', true);

    spl_autoload_register(function ($className) {
        if ($className !== 'Optimizer' && strpos($className, 'Optimizer_') !== 0) {
            return;
        }

        $paths = array(
            __DIR__ . '/classes/' . $className . '.php',
            __DIR__ . '/' . $className . '.php'
        );

        foreach ($paths as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    });
}
